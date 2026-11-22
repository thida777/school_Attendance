<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * "What the data shows this month": the last 4 weeks compared with the 4 weeks before.
 * Used by the Insights page (all findings, drill-down, heat map) and the admin dashboard (the headline).
 *
 * Findings are patterns to look into, never causes, and they never blame a teacher or a student.
 */
class Insights
{
    public array $findings = [];

    public ?float $schoolRate = null;

    public ?float $schoolTrend = null;

    /** @var Collection<int, object> records of the last 4 weeks */
    public Collection $recent;

    /** @var Collection<int, object> records of the 4 weeks before */
    public Collection $before;

    /** @var Collection<int, array> grade => rate, trend, total, absent */
    public Collection $byGrade;

    /** @var Collection<int, array> class id => rate, trend, total, absent */
    public Collection $byClass;

    /** @var Collection<int, \App\Support\StudentRisk> active students' risk, by student id */
    public Collection $risks;

    /** @var Collection<int, Student> active students, by id */
    public Collection $students;

    /** @var Collection<int, SchoolClass> by id */
    public Collection $classes;

    public function __construct(private SchoolStats $stats, private RiskAnalyzer $analyzer) {}

    public function build(?Carbon $today = null): static
    {
        [[$recentFrom, $to], [$beforeFrom, $beforeTo]] = $this->stats->windows($today);
        $this->recent = $this->stats->records($recentFrom, $to);
        $this->before = $this->stats->records($beforeFrom, $beforeTo);

        $this->schoolRate = SchoolStats::rate($this->recent);
        $this->schoolTrend = SchoolStats::trend($this->schoolRate, SchoolStats::rate($this->before));
        $this->byGrade = $this->stats->groups($this->recent, $this->before, 'grade')->sortKeys();
        $this->byClass = $this->stats->groups($this->recent, $this->before, 'school_class_id');
        $this->classes = SchoolClass::orderBy('name')->get()->keyBy('id');
        $this->students = Student::where('status', 'active')->get()->keyBy('id');
        $this->risks = $this->analyzer->forStudents($this->students->keys(), $today);
        $this->findings = $this->findings();

        return $this;
    }

    /** Students at High or Critical risk, optionally only in one class. */
    public function atRisk(?int $classId = null): Collection
    {
        return $this->risks->filter(fn ($r) => $r->isAtRisk()
            && ($classId === null || $this->students[$r->studentId]->school_class_id === $classId));
    }

    /** @return list<array{type: string, text: string}> type = decline | warning | info | good */
    private function findings(): array
    {
        if ($this->recent->isEmpty()) {
            return [['type' => 'info', 'text' => __('Not enough attendance yet. Findings appear after a few weeks of lessons.')]];
        }
        $out = [];

        // 1. Which grade fell most, and which class in it
        $grades = $this->byGrade->filter(fn ($g) => $g['trend'] !== null);
        $worstGrade = $grades->sortBy('trend')->keys()->first();
        if ($worstGrade !== null && $grades[$worstGrade]['trend'] <= -1) {
            $worstClass = $this->byClass
                ->filter(fn ($c, $id) => $this->classes[$id]->grade == $worstGrade && $c['trend'] !== null)
                ->sortBy('trend')->keys()->first();
            $out[] = ['type' => 'decline', 'text' => __('Grade :grade shows the largest decline (:points points), mostly in :class (:class_points).', [
                'grade' => $worstGrade,
                'points' => $grades[$worstGrade]['trend'],
                'class' => $worstClass ? $this->classes[$worstClass]->name : '—',
                'class_points' => $worstClass ? $this->byClass[$worstClass]['trend'] : '—',
            ])];
        } elseif ($grades->isNotEmpty()) {
            $out[] = ['type' => 'good', 'text' => __('Attendance is stable or rising in every grade.')];
        }

        // 2. The weakest time slot (weekday × period)
        $slots = $this->recent->groupBy(fn ($r) => $r->weekday.'-'.$r->period)->filter(fn ($g) => $g->count() >= 20);
        $slotRates = $slots->map(fn ($g) => SchoolStats::rate($g))->sort();
        $slot = $slotRates->keys()->first();
        if ($slot !== null && $this->schoolRate - $slotRates[$slot] >= 3) {
            [$day, $period] = explode('-', $slot);
            $out[] = ['type' => 'warning', 'text' => __(':day Period :period is the lowest time slot (:rate%), :points points below the school average.', [
                'day' => __(self::DAYS[(int) $day]),
                'period' => $period,
                'rate' => $slotRates[$slot],
                'points' => round($this->schoolRate - $slotRates[$slot], 1),
            ])];
        }

        // 3. The weakest subject in one class
        $pairs = $this->recent->groupBy(fn ($r) => $r->school_class_id.'-'.$r->subject_id)->filter(fn ($g) => $g->count() >= 15);
        $pairRates = $pairs->map(fn ($g) => SchoolStats::rate($g))->sort();
        $pair = $pairRates->keys()->first();
        if ($pair !== null && $this->schoolRate - $pairRates[$pair] >= 2) {
            [$classId, $subjectId] = explode('-', $pair);
            $subject = Subject::find($subjectId);
            $out[] = ['type' => 'warning', 'text' => __(':subject in :class has the lowest subject rate (:rate%).', [
                'subject' => app()->getLocale() === 'km' ? $subject->name_km : $subject->name_en,
                'class' => $this->classes[(int) $classId]->name,
                'rate' => $pairRates[$pair],
            ])];
        }

        // 4. Students who need help now
        $high = $this->atRisk()->count();
        $streak = $this->risks->filter(fn ($r) => $r->daysInARow >= 3)->count();
        $out[] = ['type' => 'info', 'text' => __('Students at High or Critical risk: :high. Absent 3+ school days in a row: :streak.', ['high' => $high, 'streak' => $streak])];

        // 5. Absences with no reason
        $absences = $this->recent->where('status', 'absent');
        $noReason = $absences->filter(fn ($r) => in_array($r->absence_reason, [null, '', 'not_given'], true))->count();
        if ($absences->count() >= 10 && $noReason / $absences->count() >= 0.3) {
            $out[] = ['type' => 'info', 'text' => __(':percent% of absences in the last 4 weeks have no reason. Asking parents can help.', [
                'percent' => round(100 * $noReason / $absences->count()),
            ])];
        }

        return $out;
    }

    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
}
