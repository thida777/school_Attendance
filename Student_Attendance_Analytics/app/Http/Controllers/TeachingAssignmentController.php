<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Who teaches which subject in which class. A teacher only sees their own classes. */
class TeachingAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['class', 'teacher']);

        $assignments = TeachingAssignment::query()
            ->with(['schoolClass', 'subject', 'teacher'])
            ->when($filters['class'] ?? null, fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($filters['teacher'] ?? null, fn ($q, $id) => $q->where('teacher_id', $id))
            ->join('school_classes', 'school_classes.id', '=', 'teaching_assignments.school_class_id')
            ->join('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
            ->orderBy('school_classes.name')->orderBy('subjects.code')
            ->select('teaching_assignments.*')
            ->paginate(20)
            ->withQueryString();

        return view('assignments.index', [
            'assignments' => $assignments,
            'filters' => $filters,
            'classes' => SchoolClass::orderBy('name')->get(),
            'subjects' => Subject::orderBy('name_en')->get(),
            'teachers' => User::where('role', User::ROLE_TEACHER)->orderBy('name')->get(),
            'total' => TeachingAssignment::count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        TeachingAssignment::create($this->validated($request));

        return redirect()->route('assignments.index')->with('status', __('Assignment saved.'));
    }

    /**
     * A new teacher also takes over the lessons of this subject in the timetable,
     * unless the new teacher is already busy in another class at one of those times.
     */
    public function update(Request $request, TeachingAssignment $assignment): RedirectResponse
    {
        $data = $this->validated($request, $assignment);

        DB::transaction(function () use ($data, $assignment) {
            $oldSlots = TimetableSlot::where('school_class_id', $assignment->school_class_id)
                ->where('subject_id', $assignment->subject_id);

            $sameLesson = (int) $data['school_class_id'] === $assignment->school_class_id
                && (int) $data['subject_id'] === $assignment->subject_id;

            if ($sameLesson && (int) $data['teacher_id'] !== $assignment->teacher_id) {
                // Same class and subject, new teacher: the new teacher takes over the lessons
                $this->ensureTeacherIsFree((int) $data['teacher_id'], (clone $oldSlots)->get());
                (clone $oldSlots)->update(['teacher_id' => $data['teacher_id']]);
            } elseif (! $sameLesson) {
                // Another class or subject: the old lessons no longer fit and are planned again
                (clone $oldSlots)->delete();
            }

            $assignment->update($data);
        });

        return back()->with('status', __('Assignment saved.'));
    }

    /** Removing an assignment also removes its lessons from the timetable. Attendance history stays. */
    public function destroy(TeachingAssignment $assignment): RedirectResponse
    {
        DB::transaction(function () use ($assignment) {
            TimetableSlot::where('school_class_id', $assignment->school_class_id)
                ->where('subject_id', $assignment->subject_id)
                ->delete();
            $assignment->delete();
        });

        return back()->with('status', __('Assignment deleted.'));
    }

    private function ensureTeacherIsFree(int $teacherId, $slots): void
    {
        $clash = $slots->first(fn ($slot) => TimetableSlot::where('teacher_id', $teacherId)
            ->where('weekday', $slot->weekday)->where('period', $slot->period)->exists());

        if ($clash) {
            throw ValidationException::withMessages([
                'teacher_id' => __('This teacher already teaches another class on :day, period :period.', [
                    'day' => __(now()->startOfWeek()->addDays($clash->weekday - 1)->format('l')),
                    'period' => $clash->period,
                ]),
            ]);
        }
    }

    private function validated(Request $request, ?TeachingAssignment $assignment = null): array
    {
        return $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id',
                Rule::unique('teaching_assignments')
                    ->where('school_class_id', $request->input('school_class_id'))
                    ->ignore($assignment)],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_TEACHER)],
        ], [
            'subject_id.unique' => __('This class already has a teacher for this subject. Edit that assignment instead.'),
        ], [
            'school_class_id' => __('Class'),
            'subject_id' => __('Subject'),
            'teacher_id' => __('Teacher'),
        ]);
    }
}
