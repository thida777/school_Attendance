<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\AttendanceRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Take Attendance: 1 choose the lesson → 2 mark → 3 review → 4 done. */
class AttendanceController extends Controller
{
    public function __construct(private AttendanceRecorder $recorder) {}

    /** Step 1: today's lessons. A teacher sees only their own, the admin sees the whole school. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $schedule = $this->recorder->schedule($user, today());

        // For "Other class or date": a teacher can only choose their own classes and subjects
        $assignments = $user->isTeacher() ? $user->teachingAssignments()->get() : null;

        return view('attendance.index', [
            'schedule' => $schedule,
            'doneCount' => $schedule->whereNotNull('session')->count(),
            'classes' => SchoolClass::when($assignments, fn ($q) => $q->whereIn('id', $assignments->pluck('school_class_id')))->orderBy('name')->get(),
            'subjects' => Subject::when($assignments, fn ($q) => $q->whereIn('id', $assignments->pluck('subject_id')))->orderBy('name_en')->get(),
            'periods' => TimetableSlot::PERIODS,
        ]);
    }

    /** Step 2 and 3: mark the students, then review in a pop-up. */
    public function mark(Request $request): View|RedirectResponse
    {
        if ($request->filled('slot')) {
            $slot = TimetableSlot::findOrFail($request->integer('slot'));
            $lesson = ['school_class_id' => $slot->school_class_id, 'subject_id' => $slot->subject_id, 'date' => today()->toDateString(), 'period' => $slot->period];
        } else {
            $lesson = $request->validate([
                'school_class_id' => ['required', 'exists:school_classes,id'],
                'subject_id' => ['required', 'exists:subjects,id'],
                'date' => ['required', 'date', 'before_or_equal:today'],
                'period' => ['required', 'integer', Rule::in(array_keys(TimetableSlot::PERIODS))],
            ], [], ['school_class_id' => __('Class'), 'subject_id' => __('Subject'), 'date' => __('Date'), 'period' => __('Period')]);
        }

        $date = Carbon::parse($lesson['date']);
        $class = SchoolClass::findOrFail($lesson['school_class_id']);
        $subject = Subject::findOrFail($lesson['subject_id']);
        $session = $this->recorder->existing($class->id, $date, (int) $lesson['period']);

        $this->recorder->check($request->user(), $class->id, $subject->id, $date, $session);

        if ($session && $session->subject_id !== $subject->id) {
            return redirect()->route('attendance.take')->with('error', __('Period :period on this day is already recorded for :subject.', [
                'period' => $lesson['period'], 'subject' => $session->subject->name_en,
            ]));
        }

        return view('attendance.mark', [
            'lesson' => $lesson + ['date' => $date->toDateString()],
            'class' => $class,
            'subject' => $subject,
            'date' => $date,
            'time' => TimetableSlot::PERIODS[$lesson['period']] ?? null,
            'session' => $session,
            'isCorrection' => $this->recorder->needsReason($session),
            'students' => $this->recorder->students($class->id, $session),
            'saved' => $session?->records()->get()->keyBy('student_id') ?? collect(),
            'reasons' => AttendanceRecord::ABSENCE_REASONS,
        ]);
    }

    /** Save, then go to step 4. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'date' => ['required', 'date'],
            'period' => ['required', 'integer', Rule::in(array_keys(TimetableSlot::PERIODS))],
            'statuses' => ['array'],
            'statuses.*' => [Rule::in(AttendanceRecord::STATUSES)],
            'reasons' => ['array'],
            'reasons.*' => ['nullable', Rule::in(AttendanceRecord::ABSENCE_REASONS)],
            'correction_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $session = $this->recorder->save($request->user(), $data);

        return redirect()->route('attendance.done', $session);
    }

    /** Step 4: the result, and a button to the next lesson. */
    public function done(Request $request, AttendanceSession $session): View
    {
        $user = $request->user();
        abort_if($user->isTeacher() && $session->teacher_id !== $user->id, 403);

        $session->load(['schoolClass', 'subject', 'records.student']);
        $absent = $session->records->where('status', 'absent')->sortBy('student.name_latin');

        $next = $session->date->isToday()
            ? $this->recorder->schedule($user, today())->first(fn ($lesson) => $lesson->session === null)
            : null;

        return view('attendance.done', [
            'session' => $session,
            'present' => $session->records->where('status', 'present')->count(),
            'absent' => $absent,
            'next' => $next,
            'canEdit' => $user->isAdmin() || $this->recorder->isOpen($session),
        ]);
    }
}
