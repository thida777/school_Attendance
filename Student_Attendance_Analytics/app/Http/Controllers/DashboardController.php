<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
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

        return view('dashboard.admin', [
            'year' => AcademicYear::current(),
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

        return view('dashboard.teacher', [
            'teacher' => $teacher,
            'year' => AcademicYear::current(),
            'assignmentsByClass' => $assignments->groupBy(fn ($a) => $a->schoolClass->name),
            'homeroomClass' => $teacher->homeroomClasses()->withCount('students')->first(),
            'sessionsToday' => $teacher->attendanceSessions()->whereDate('date', now()->toDateString())->count(),
        ]);
    }
}
