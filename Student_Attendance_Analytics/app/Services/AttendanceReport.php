<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers behind the Reports page, its Excel file and its printable PDF,
 * for one period and (optionally) one class and one subject. One place, so all three always agree.
 */
class AttendanceReport
{
    public function __construct(private SchoolStats $stats, private RiskAnalyzer $analyzer) {}

    /**
     * @param  array{from: string, to: string, class?: ?int, subject?: ?int}  $filters
     */
    public function build(array $filters): object
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->startOfDay();
        $classId = $filters['class'] ?? null;
        $subjectId = $filters['subject'] ?? null;

        $records = $this->filtered($this->stats->records($from, $to), $classId, $subjectId);

        // The period of the same length just before, for the "vs before" figure
        $days = $from->diffInDays($to) + 1;
        $before = $this->filtered($this->stats->records($from->copy()->subDays($days), $from->copy()->subDay()), $classId, $subjectId);

        $absent = $records->where('status', 'absent');
        $students = Student::with('schoolClass')->where('status', 'active')
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))->get();
        $risks = $this->analyzer->forStudents($students->pluck('id'));
        $classes = SchoolClass::orderBy('name')->when($classId, fn ($q) => $q->whereKey($classId))->get();
        $subjects = Subject::orderBy('name_en')->get()->keyBy('id');
        $rate = SchoolStats::rate($records);

        return (object) [
            'from' => $from,
            'to' => $to,
            'class' => $classId ? $classes->first() : null,
            'subject' => $subjectId ? $subjects->get($subjectId) : null,
            'rate' => $rate,
            'change' => SchoolStats::trend($rate, SchoolStats::rate($before)),
            'present' => $records->count() - $absent->count(),
            'absent' => $absent->count(),
            'total' => $records->count(),
            'studentsWithRecords' => $records->pluck('student_id')->unique()->count(),
            'recorded' => $this->recorded($from, $to, $classId, $subjectId),
            'planned' => $this->planned($from, $to, $classId, $subjectId),
            'peakDay' => $absent->countBy('weekday')->sortDesc()->keys()->first(),
            'peakPeriod' => $absent->countBy('period')->sortDesc()->keys()->first(),
            'byClass' => $classes->map(function (SchoolClass $class) use ($records, $students, $risks) {
                $rows = $records->where('school_class_id', $class->id);
                $ids = $students->where('school_class_id', $class->id)->pluck('id');

                return (object) [
                    'class' => $class,
                    'students' => $ids->count(),
                    'present' => $rows->where('status', 'present')->count(),
                    'absent' => $rows->where('status', 'absent')->count(),
                    'rate' => SchoolStats::rate($rows),
                    'atRisk' => $risks->only($ids->all())->filter->isAtRisk()->count(),
                ];
            }),
            'bySubject' => $records->groupBy('subject_id')->map(fn (Collection $rows, $id) => (object) [
                'subject' => $subjects->get($id),
                'absent' => $rows->where('status', 'absent')->count(),
                'rate' => SchoolStats::rate($rows),
            ])->sortByDesc('absent')->values(),
            'trend' => [
                'daily' => $records->groupBy('date')->sortKeys()
                    ->map(fn ($rows, $date) => ['label' => Carbon::parse($date)->translatedFormat('j M'), 'rate' => SchoolStats::rate($rows)])->values(),
                'weekly' => $this->stats->weekly($records, $from, $to),
                'monthly' => $records->groupBy(fn ($r) => substr($r->date, 0, 7))->sortKeys()
                    ->map(fn ($rows, $month) => ['label' => Carbon::parse($month.'-01')->translatedFormat('M Y'), 'rate' => SchoolStats::rate($rows)])->values(),
            ],
            'atRisk' => $students->filter(fn ($s) => $risks->get($s->id)?->isAtRisk())
                ->map(fn ($s) => (object) ['student' => $s, 'risk' => $risks->get($s->id)])
                ->sortBy(fn ($row) => [$row->risk->level === 'critical' ? 0 : 1, $row->risk->rate])->values(),
            'records' => $records,
            'studentList' => $students,
            'risks' => $risks,
        ];
    }

    /** One row per student for the Excel file: counts, rate, reasons, risk today. */
    public function perStudent(object $report): Collection
    {
        $byStudent = $report->records->groupBy('student_id');

        return $report->studentList
            ->sortBy(fn ($s) => $s->schoolClass->name.' '.$s->student_code)
            ->map(function (Student $s) use ($byStudent, $report) {
                $rows = $byStudent->get($s->id, collect());
                $reasons = $rows->where('status', 'absent')->countBy(fn ($r) => $r->absence_reason ?: 'not_given');

                return (object) [
                    'student' => $s,
                    'total' => $rows->count(),
                    'present' => $rows->where('status', 'present')->count(),
                    'absent' => $rows->where('status', 'absent')->count(),
                    'rate' => SchoolStats::rate($rows),
                    'reasons' => $reasons,
                    'risk' => $report->risks->get($s->id),
                ];
            })->values();
    }

    private function filtered(Collection $records, ?int $classId, ?int $subjectId): Collection
    {
        return $records
            ->when($classId, fn ($c) => $c->where('school_class_id', $classId))
            ->when($subjectId, fn ($c) => $c->where('subject_id', $subjectId))
            ->values();
    }

    private function recorded(Carbon $from, Carbon $to, ?int $classId, ?int $subjectId): int
    {
        return AttendanceSession::whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->count();
    }

    /** Lessons on the timetable in the period (school days of the current year, up to today). */
    private function planned(Carbon $from, Carbon $to, ?int $classId, ?int $subjectId): int
    {
        $perWeekday = TimetableSlot::query()
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->get()->countBy('weekday');
        $year = AcademicYear::current();
        $start = $year ? $from->copy()->max(Carbon::parse($year->start_date)) : $from->copy();
        $end = $to->copy()->min(today());

        $planned = 0;
        for ($day = $start; $day->lte($end); $day->addDay()) {
            $planned += $perWeekday->get($day->dayOfWeekIso, 0);
        }

        return $planned;
    }
}
