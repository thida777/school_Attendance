<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Services\RiskAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** My Classes (teacher): the classes I teach, and their students. Read only: the admin manages students. */
class MyClassesController extends Controller
{
    public function __invoke(Request $request): View
    {
        $teacher = $request->user();
        $assignments = $teacher->teachingAssignments()->with('subject')->get();
        $classIds = $assignments->pluck('school_class_id')
            ->merge($teacher->homeroomClasses()->pluck('id'))
            ->unique();

        $classes = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->withCount([
                'students' => fn ($q) => $q->where('status', 'active'),
                'students as girls_count' => fn ($q) => $q->where('status', 'active')->where('gender', 'female'),
                'attendanceRecords as records_total',
                'attendanceRecords as records_present' => fn ($q) => $q->where('attendance_records.status', 'present'),
            ])
            ->orderBy('name')
            ->get();

        // My lessons this month, and when I teach each class
        $sessionsThisMonth = AttendanceSession::where('teacher_id', $teacher->id)
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->selectRaw('school_class_id, count(*) as total')->groupBy('school_class_id')
            ->pluck('total', 'school_class_id');
        $slots = TimetableSlot::where('teacher_id', $teacher->id)->get()->groupBy('school_class_id');

        $filters = $request->only(['q', 'class']);
        $students = Student::query()
            ->whereIn('school_class_id', $classIds)
            ->where('status', 'active')
            ->with('schoolClass')
            ->withCount([
                'attendanceRecords as records_total',
                'attendanceRecords as records_present' => fn ($q) => $q->where('status', 'present'),
            ])
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name_latin', 'like', "%{$search}%")
                ->orWhere('name_km', 'like', "%{$search}%")
                ->orWhere('student_code', 'like', "%{$search}%")))
            ->when($filters['class'] ?? null, fn ($q, $id) => $q->where('school_class_id', $id))
            ->orderBy('student_code')
            ->paginate(20)
            ->withQueryString();

        return view('teacher.classes', [
            'classes' => $classes,
            'subjects' => $assignments->pluck('subject')->unique('id'),
            'homeroomIds' => $teacher->homeroomClasses()->pluck('id'),
            'sessionsThisMonth' => $sessionsThisMonth,
            // class id => [weekday => [periods]], shown as 6 day chips on each card
            'teachingDays' => $slots->map(fn ($classSlots) => $classSlots->groupBy('weekday')
                ->map(fn ($daySlots) => $daySlots->pluck('period')->sort()->values()->all())),
            'atRisk' => SchoolClass::atRiskCounts($classIds),
            'students' => $students,
            'risks' => app(RiskAnalyzer::class)->forStudents($students->getCollection()->pluck('id')),
            'filters' => $filters,
            'year' => AcademicYear::current(),
        ]);
    }
}
