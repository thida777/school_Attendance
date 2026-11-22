<?php

namespace App\Services;

use App\Support\Risk;
use App\Support\StudentRisk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Works out the risk of each student from the attendance records (Plan v2.0 rules, see App\Support\Risk).
 *
 *  - Rate:             present ÷ all records
 *  - Days in a row:    the latest school days on which the student missed every recorded lesson
 *                      (a day with at least one Present ends the count; days without records are skipped)
 *  - Trend:            rate of the last 4 weeks minus the rate of the 4 weeks before, in points
 *                      (only when both periods have at least 3 records)
 *  - Unexplained:      school days this month with an absence and no reason (empty or "not_given")
 *  - Pattern:          the weekday and the period with the most absences (at least 2)
 *
 * One query for all the students, then the counting is done in PHP.
 * Used by the website, the API for the app, the analytics pages and the reports, so they always agree.
 */
class RiskAnalyzer
{
    public const TREND_DAYS = 28;

    public const MIN_TREND_RECORDS = 3;

    /**
     * @param  iterable<int>  $studentIds
     * @return Collection<int, StudentRisk> keyed by student id (students without records are included, level null)
     */
    public function forStudents(iterable $studentIds, ?Carbon $today = null): Collection
    {
        $ids = collect($studentIds)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        $today = ($today ?? today())->copy()->startOfDay();

        $records = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->whereIn('r.student_id', $ids)
            ->whereDate('s.date', '<=', $today)
            ->get(['r.student_id', 'r.status', 'r.absence_reason', 's.date', 's.period'])
            ->groupBy('student_id');

        return $ids->mapWithKeys(fn (int $id) => [
            $id => $this->analyse($id, $records->get($id, collect()), $today),
        ]);
    }

    public function forStudent(int $studentId, ?Carbon $today = null): StudentRisk
    {
        return $this->forStudents([$studentId], $today)->get($studentId);
    }

    /** @param  Collection<int, object>  $records  one student's records */
    private function analyse(int $studentId, Collection $records, Carbon $today): StudentRisk
    {
        $total = $records->count();
        $absences = $records->where('status', 'absent');
        $absent = $absences->count();
        $rate = Risk::rate($total - $absent, $total);

        $daysInARow = $this->daysInARow($records);
        $trend = $this->trend($records, $today);
        // School days this month with an absence and no reason (a whole day away counts once, not once per lesson)
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $unexplained = $absences->filter(fn ($r) => substr($r->date, 0, 10) >= $monthStart
            && in_array($r->absence_reason, [null, '', 'not_given'], true))
            ->unique(fn ($r) => substr($r->date, 0, 10))->count();

        return new StudentRisk(
            studentId: $studentId,
            total: $total,
            present: $total - $absent,
            absent: $absent,
            rate: $rate,
            daysInARow: $daysInARow,
            trend: $trend,
            unexplained: $unexplained,
            patternDay: $this->mostCommon($absences->map(fn ($r) => Carbon::parse($r->date)->dayOfWeekIso)),
            patternPeriod: $this->mostCommon($absences->pluck('period')),
            level: Risk::level($rate, $daysInARow, $trend ?? 0, $unexplained),
        );
    }

    /** School days, newest first: count the days where every recorded lesson was Absent. */
    private function daysInARow(Collection $records): int
    {
        $days = $records->groupBy(fn ($r) => substr($r->date, 0, 10))->sortKeysDesc();
        $count = 0;
        foreach ($days as $dayRecords) {
            if ($dayRecords->every(fn ($r) => $r->status === 'absent')) {
                $count++;
            } else {
                break;
            }
        }

        return $count;
    }

    /** Last 4 weeks minus the 4 weeks before, in points; null when there are not enough records. */
    private function trend(Collection $records, Carbon $today): ?float
    {
        $recentStart = $today->copy()->subDays(self::TREND_DAYS - 1)->toDateString();
        $earlierStart = $today->copy()->subDays(2 * self::TREND_DAYS - 1)->toDateString();

        $recent = $records->filter(fn ($r) => substr($r->date, 0, 10) >= $recentStart);
        $earlier = $records->filter(fn ($r) => substr($r->date, 0, 10) >= $earlierStart && substr($r->date, 0, 10) < $recentStart);
        if ($recent->count() < self::MIN_TREND_RECORDS || $earlier->count() < self::MIN_TREND_RECORDS) {
            return null;
        }

        $rateOf = fn (Collection $part) => 100 * $part->where('status', 'present')->count() / $part->count();

        return round($rateOf($recent) - $rateOf($earlier), 1);
    }

    /** The value that appears most often, if it appears at least twice. */
    private function mostCommon(Collection $values): ?int
    {
        $counts = $values->countBy()->sortDesc();
        $top = $counts->keys()->first();

        return $top !== null && $counts->first() >= 2 ? (int) $top : null;
    }
}
