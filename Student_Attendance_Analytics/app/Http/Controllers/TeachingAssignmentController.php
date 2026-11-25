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
    /**
     * Grid (default): one grade at a time, classes × subjects with the teacher in each box,
     * and a box for each subject the grade teaches but this class has no teacher for yet.
     * Table: the plain list. Both have grade tabs (a big school has many classes) and the teachers' load.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['class', 'teacher']);
        $view = $request->input('view') === 'table' ? 'table' : 'grid';

        $classes = SchoolClass::with(['teachingAssignments.subject', 'teachingAssignments.teacher'])
            ->orderBy('grade')->orderBy('name')->get();
        $grades = $classes->groupBy('grade');
        $grade = $grades->has((int) $request->input('grade')) ? (int) $request->input('grade') : ($view === 'grid' ? $grades->keys()->first() : null);

        // Per grade: the subjects its classes teach, and how many class × subject boxes are still empty
        $gradeInfo = $grades->map(function ($group) {
            $subjects = $group->flatMap(fn ($c) => $c->teachingAssignments->pluck('subject'))->unique('id')->sortBy('code')->values();
            $missing = $group->sum(fn ($c) => $subjects->count() - $c->teachingAssignments->whereIn('subject_id', $subjects->pluck('id'))->count());

            return ['classes' => $group, 'subjects' => $subjects, 'missing' => $missing];
        });

        $assignments = TeachingAssignment::query()
            ->with(['schoolClass', 'subject', 'teacher'])
            ->when($grade, fn ($q, $g) => $q->whereHas('schoolClass', fn ($q) => $q->where('grade', $g)))
            ->when($filters['class'] ?? null, fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($filters['teacher'] ?? null, fn ($q, $id) => $q->where('teacher_id', $id))
            ->join('school_classes', 'school_classes.id', '=', 'teaching_assignments.school_class_id')
            ->join('subjects', 'subjects.id', '=', 'teaching_assignments.subject_id')
            ->orderBy('school_classes.name')->orderBy('subjects.code')
            ->select('teaching_assignments.*')
            ->paginate(20)
            ->withQueryString();

        $teachers = User::where('role', User::ROLE_TEACHER)->orderBy('name')->get();

        return view('assignments.index', [
            'view' => $view,
            'grade' => $grade,
            'gradeInfo' => $gradeInfo,
            'assignments' => $assignments,
            'filters' => $filters,
            'classes' => $classes,
            'subjects' => Subject::orderBy('name_en')->get(),
            'teachers' => $teachers,
            'loads' => $this->loads($teachers, $classes),
            'maxHours' => config('school.teacher_max_hours'),
            'total' => TeachingAssignment::count(),
        ]);
    }

    /**
     * Copy all subjects and teachers of one class to other classes (e.g. 10A → 10B, 10C …).
     * A subject the other class already has keeps its own teacher.
     */
    public function copy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'exists:school_classes,id'],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['integer', 'distinct', 'exists:school_classes,id', 'different:from'],
        ], [
            'to.required' => __('Choose at least one class to copy to.'),
        ], [
            'from' => __('Copy from'),
            'to' => __('Copy to'),
        ]);

        $source = TeachingAssignment::where('school_class_id', $data['from'])->get();
        $added = 0;
        DB::transaction(function () use ($source, $data, &$added) {
            foreach ($data['to'] as $classId) {
                foreach ($source as $assignment) {
                    $new = TeachingAssignment::firstOrCreate(
                        ['school_class_id' => $classId, 'subject_id' => $assignment->subject_id],
                        ['teacher_id' => $assignment->teacher_id],
                    );
                    $added += $new->wasRecentlyCreated ? 1 : 0;
                }
            }
        });

        return back()->with('status', trans_choice(':count assignment added to :classes class.|:count assignments added to :classes classes.', $added, [
            'count' => $added, 'classes' => count($data['to']),
        ]));
    }

    /**
     * Hours a week and classes of every teacher (from the subjects' hours), most hours first.
     * state: over (more than the limit), none (no class yet) or ok.
     */
    private function loads($teachers, $classes): \Illuminate\Support\Collection
    {
        $all = $classes->flatMap->teachingAssignments;
        $max = config('school.teacher_max_hours');

        return $teachers->map(function ($teacher) use ($all, $max) {
            $mine = $all->where('teacher_id', $teacher->id);
            $hours = (int) $mine->sum(fn ($a) => $a->subject->hours_per_week);

            return [
                'teacher' => $teacher,
                'hours' => $hours,
                'classes' => $mine->pluck('school_class_id')->unique()->count(),
                'state' => $hours > $max ? 'over' : ($hours === 0 ? 'none' : 'ok'),
            ];
        })->sortByDesc('hours')->values();
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
