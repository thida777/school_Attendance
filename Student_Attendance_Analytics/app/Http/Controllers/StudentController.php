<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentController extends Controller
{
    /** List students with search, filters and each student's attendance rate. */
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'class', 'gender', 'status']);

        $students = Student::query()
            ->with(['schoolClass', 'guardians'])
            ->withCount([
                'attendanceRecords as records_total',
                'attendanceRecords as records_present' => fn ($q) => $q->where('status', 'present'),
            ])
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name_latin', 'like', "%{$search}%")
                ->orWhere('name_km', 'like', "%{$search}%")
                ->orWhere('student_code', 'like', "%{$search}%")))
            ->when($filters['class'] ?? null, fn ($q, $classId) => $q->where('school_class_id', $classId))
            ->when($filters['gender'] ?? null, fn ($q, $gender) => $q->where('gender', $gender))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('student_code')
            ->paginate(15)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'classes' => SchoolClass::orderBy('name')->get(),
            'filters' => $filters,
            'total' => Student::count(),
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $student = Student::create($request->safe()->except(['guardian_name', 'guardian_phone', 'guardian_relation']));
            $this->saveGuardian($student, $request);
        });

        return redirect()->route('students.index')->with('status', __('Student saved.'));
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student) {
            $student->update($request->safe()->except(['guardian_name', 'guardian_phone', 'guardian_relation']));
            $this->saveGuardian($student, $request);
        });

        return back()->with('status', __('Student saved.'));
    }

    /** A student with attendance records is never deleted: the history would be lost. */
    public function destroy(Student $student): RedirectResponse
    {
        if ($student->attendanceRecords()->exists()) {
            return back()->with('error', __(':name has attendance records and cannot be deleted. Set the student to Inactive instead.', ['name' => $student->name_latin]));
        }

        $student->delete();

        return back()->with('status', __('Student deleted.'));
    }

    /**
     * The form has one parent. A parent is found by phone number, so two brothers share one contact.
     * The parent shown in the form is replaced; other parents linked on the Parents page stay.
     */
    private function saveGuardian(Student $student, StudentRequest $request): void
    {
        $guardian = Guardian::updateOrCreate(
            ['phone' => $request->validated('guardian_phone')],
            ['name' => $request->validated('guardian_name')],
        );

        $current = $student->guardians()->first();
        if ($current && $current->id !== $guardian->id) {
            $student->guardians()->detach($current->id);
        }

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => ['relation' => $request->validated('guardian_relation')],
        ]);
    }
}
