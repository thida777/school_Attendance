<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\AttendanceRecorder;
use App\Services\Insights;
use App\Services\RiskAnalyzer;
use App\Services\SchoolStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Admin: the whole school. */
    public function admin(): View
    {
        $today = now()->toDateString();

        $todayRecords = AttendanceRecord::whereHas('session', fn ($q) => $q->whereDate('date', $today))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $recordedToday = $todayRecords->sum();

        $year = AcademicYear::current();
        $insights = app(Insights::class)->build();
        $stats = app(SchoolStats::class);
        $yearRecords = $year ? $stats->records(Carbon::parse($year->start_date), today()) : collect();
        $lessonsToday = TimetableSlot::where('weekday', today()->dayOfWeekIso)->count();

        return view('dashboard.admin', [
            'year' => $year,
            'insights' => $insights,
            // Week by week since the first day of the school year
            'weekly' => $year ? $stats->weekly($yearRecords, Carbon::parse($year->start_date)) : collect(),
            'absencesByWeekday' => $stats->absencesByWeekday($insights->recent),
            'lessonsToday' => $lessonsToday,
            'notRecordedToday' => max(0, $lessonsToday - AttendanceSession::whereDate('date', $today)->count()),
            'watch' => $insights->atRisk()
                ->sortBy(fn ($r) => [$r->level === 'critical' ? 0 : 1, $r->rate])
                ->take(5)
                ->map(fn ($r) => ['student' => $insights->students[$r->studentId], 'risk' => $r]),
            'studentCount' => Student::count(),
            'teacherCount' => User::where('role', User::ROLE_TEACHER)->count(),
            'classCount' => SchoolClass::count(),
            'sessionsToday' => AttendanceSession::whereDate('date', $today)->count(),
            'recordedToday' => $recordedToday,
            // Rate = present / all records (only Present and Absent exist)
            'rateToday' => $recordedToday > 0
                ? round(100 * ($todayRecords['present'] ?? 0) / $recordedToday, 1)
                : null,
            'absentToday' => $todayRecords['absent'] ?? 0,
        ]);
    }

    /** Teacher: only their own classes. */
    public function teacher(Request $request): View
    {
        $teacher = $request->user();

        $assignments = $teacher->teachingAssignments()
            ->with(['schoolClass', 'subject'])
            ->get()
            ->sortBy(fn ($a) => $a->schoolClass->name.$a->subject->name_en);
        $year = AcademicYear::current();
        $stats = app(SchoolStats::class);

        // My classes: the ones I teach, plus my homeroom class
        $classIds = $assignments->pluck('school_class_id')->merge($teacher->homeroomClasses()->pluck('id'))->unique();
        $classes = SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();
        $students = Student::whereIn('school_class_id', $classIds)->where('status', 'active')->get()->keyBy('id');

        // Today's lessons (the same list as Take Attendance)
        $schedule = app(AttendanceRecorder::class)->schedule($teacher, today());
        $todaySessions = $schedule->pluck('session')->filter();
        $presentToday = $todaySessions->sum('present_count');
        $recordedToday = $todaySessions->sum('records_count');

        // My lessons only: week by week this year, and each class in the last 4 weeks
        $mine = $year ? $stats->records(Carbon::parse($year->start_date), today(), $teacher->id) : collect();
        [[$recentFrom, $to], [$beforeFrom, $beforeTo]] = $stats->windows();
        $recent = $mine->filter(fn ($r) => $r->date >= $recentFrom->toDateString());
        $before = $mine->filter(fn ($r) => $r->date >= $beforeFrom->toDateString() && $r->date <= $beforeTo->toDateString());

        // Students to watch: at risk first, then "Watch" (medium), lowest rate first
        $risks = app(RiskAnalyzer::class)->forStudents($students->keys());

        return view('dashboard.teacher', [
            'teacher' => $teacher,
            'year' => $year,
            'subjects' => $assignments->pluck('subject')->unique('id'),
            'classes' => $classes,
            'studentCount' => $students->count(),
            'schedule' => $schedule,
            'rateToday' => $recordedToday ? round(100 * $presentToday / $recordedToday, 1) : null,
            'absentToday' => $recordedToday - $presentToday,
            'absentWhere' => $todaySessions->filter(fn ($s) => $s->records_count > $s->present_count)
                ->map(fn ($s) => $classes->firstWhere('id', $s->school_class_id)?->name.' P'.$s->period)->join(', '),
            'weekly' => $year ? $stats->weekly($mine, Carbon::parse($year->start_date)) : collect(),
            'byClass' => $stats->groups($recent, $before, 'school_class_id'),
            'watch' => $risks->filter(fn ($r) => in_array($r->level, ['critical', 'high', 'medium'], true))
                ->sortBy(fn ($r) => [array_search($r->level, ['critical', 'high', 'medium'], true), $r->rate])
                ->take(5)
                ->map(fn ($r) => ['student' => $students[$r->studentId], 'risk' => $r]),
        ]);
    }
}
