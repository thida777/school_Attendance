<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Classes of the current academic year, one card per class. */
class SchoolClassController extends Controller
{
    public function index(): View
    {
        $year = AcademicYear::current();

        $classes = SchoolClass::query()
            ->where('academic_year_id', $year?->id)
            ->with('homeroomTeacher')
            ->withCount([
                'students' => fn ($q) => $q->where('status', 'active'),
                'students as girls_count' => fn ($q) => $q->where('status', 'active')->where('gender', 'female'),
                'attendanceRecords as records_total',
                'attendanceRecords as records_present' => fn ($q) => $q->where('attendance_records.status', 'present'),
            ])
            ->orderBy('grade')->orderBy('name')
            ->get();

        return view('classes.index', [
            'classes' => $classes,
            'year' => $year,
            'teachers' => User::where('role', User::ROLE_TEACHER)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $year = AcademicYear::current();
        SchoolClass::create($this->validated($request, $year->id) + ['academic_year_id' => $year->id]);

        return redirect()->route('classes.index')->with('status', __('Class saved.'));
    }

    public function update(Request $request, SchoolClass $class): RedirectResponse
    {
        $class->update($this->validated($request, $class->academic_year_id, $class));

        return back()->with('status', __('Class saved.'));
    }

    /** Students must be moved out first, so nobody loses their class. */
    public function destroy(SchoolClass $class): RedirectResponse
    {
        if ($class->students()->exists()) {
            return back()->with('error', __('Class :name still has students. Move them to another class first.', ['name' => $class->name]));
        }

        $class->delete();

        return back()->with('status', __('Class deleted.'));
    }

    private function validated(Request $request, int $yearId, ?SchoolClass $class = null): array
    {
        $request->merge(['name' => strtoupper(trim((string) $request->input('name')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:20',
                Rule::unique('school_classes')->where('academic_year_id', $yearId)->ignore($class)],
            'grade' => ['required', 'integer', 'between:7,12'],
            'room' => ['nullable', 'string', 'max:20'],
            'homeroom_teacher_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_TEACHER)],
        ], [], [
            'name' => __('Class name'),
            'grade' => __('Grade'),
            'room' => __('Room'),
            'homeroom_teacher_id' => __('Homeroom teacher'),
        ]);
    }
}
