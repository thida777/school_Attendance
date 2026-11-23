<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\SchoolStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Teacher accounts. Each teacher logs in to the website and the Android app. */
class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q'));
        $digits = preg_replace('/\D/', '', $search);

        $teachers = User::query()
            ->where('role', User::ROLE_TEACHER)
            ->with(['teachingAssignments.subject', 'teachingAssignments.schoolClass'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('name_km', 'like', "%{$search}%")
                ->when($digits !== '', fn ($q) => $q->orWhere('phone', 'like', "%{$digits}%"))))
            ->orderBy('name')
            ->get();

        // "Sessions this week": lessons in the timetable from Monday to today, and how many were recorded
        $today = now();
        $weekday = min($today->dayOfWeekIso, 6); // on Sunday the whole week counts
        $planned = TimetableSlot::where('weekday', '<=', $weekday)
            ->selectRaw('teacher_id, count(*) as total')->groupBy('teacher_id')->pluck('total', 'teacher_id');
        $recorded = AttendanceSession::whereBetween('date', [$today->copy()->startOfWeek()->toDateString(), $today->toDateString()])
            ->selectRaw('teacher_id, count(*) as total')->groupBy('teacher_id')->pluck('total', 'teacher_id');

        return view('teachers.index', [
            'teachers' => $teachers,
            'planned' => $planned,
            'recorded' => $recorded,
            'search' => $search,
            'total' => User::where('role', User::ROLE_TEACHER)->count(),
        ]);
    }

    /**
     * Teacher details: profile, classes and subjects, lessons recorded vs the timetable,
     * attendance in this teacher's lessons (last 4 weeks), and the latest lessons.
     */
    public function show(User $teacher, SchoolStats $stats): View
    {
        abort_unless($teacher->isTeacher(), 404);

        $assignments = $teacher->teachingAssignments()->with(['schoolClass', 'subject'])->get()
            ->sortBy(fn ($a) => $a->schoolClass->name.$a->subject->name_en)->values();
        $classIds = $assignments->pluck('school_class_id')->merge($teacher->homeroomClasses()->pluck('id'))->unique();
        $studentsPerClass = Student::whereIn('school_class_id', $classIds)->where('status', 'active')
            ->selectRaw('school_class_id, count(*) as total')->groupBy('school_class_id')->pluck('total', 'school_class_id');

        // Last 4 weeks of this teacher's lessons, and the 4 weeks before (for the trend)
        [[$from, $to], [$beforeFrom, $beforeTo]] = $stats->windows();
        $recent = $stats->records($from, $to, $teacher->id);
        $before = $stats->records($beforeFrom, $beforeTo, $teacher->id);
        $rate = SchoolStats::rate($recent);

        // Lessons in the timetable vs lessons recorded, in the same 4 weeks
        $slots = $teacher->timetableSlots()->get();
        $sessions = $teacher->attendanceSessions()
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->get();
        $plannedFor = function (int $classId, int $subjectId) use ($slots, $from, $to) {
            $perWeekday = $slots->where('school_class_id', $classId)->where('subject_id', $subjectId)->countBy('weekday');
            $total = 0;
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $total += $perWeekday->get($day->dayOfWeekIso, 0);
            }

            return $total;
        };

        // This week, Monday to today (the same rule as the Teachers list)
        $weekday = min(today()->dayOfWeekIso, 6);
        $weekPlanned = $slots->where('weekday', '<=', $weekday)->count();
        $weekRecorded = $teacher->attendanceSessions()
            ->whereBetween('date', [today()->startOfWeek()->toDateString(), today()->toDateString()])->count();

        $rows = $assignments->map(function ($a) use ($recent, $sessions, $studentsPerClass, $plannedFor) {
            $lessons = $recent->where('school_class_id', $a->school_class_id)->where('subject_id', $a->subject_id);

            return (object) [
                'assignment' => $a,
                'students' => $studentsPerClass->get($a->school_class_id, 0),
                'rate' => SchoolStats::rate($lessons),
                'recorded' => $sessions->where('school_class_id', $a->school_class_id)->where('subject_id', $a->subject_id)->count(),
                'planned' => $plannedFor($a->school_class_id, $a->subject_id),
            ];
        });

        return view('teachers.show', [
            'teacher' => $teacher,
            'rows' => $rows,
            'homeroom' => $teacher->homeroomClasses()->orderBy('name')->get(),
            'classCount' => $classIds->count(),
            'studentCount' => $studentsPerClass->sum(),
            'rate' => $rate,
            'trend' => SchoolStats::trend($rate, SchoolStats::rate($before)),
            'weekPlanned' => $weekPlanned,
            'weekRecorded' => $weekRecorded,
            'planned' => $rows->sum('planned'),
            'recorded' => $rows->sum('recorded'),
            'latest' => $teacher->attendanceSessions()
                ->with(['schoolClass', 'subject', 'recordedBy'])
                ->withCount(['records', 'records as present_count' => fn ($q) => $q->where('status', 'present')])
                ->orderByDesc('date')->orderByDesc('period')
                ->limit(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        User::create($this->validated($request) + ['role' => User::ROLE_TEACHER]);

        return redirect()->route('teachers.index')->with('status', __('Teacher saved.'));
    }

    public function update(Request $request, User $teacher): RedirectResponse
    {
        abort_unless($teacher->isTeacher(), 404);

        $data = $this->validated($request, $teacher);
        if (blank($data['password'] ?? null)) {
            unset($data['password']); // empty = keep the old password
        }
        $teacher->update($data);

        return back()->with('status', __('Teacher saved.'));
    }

    /** A teacher who still teaches, or who recorded attendance, stays: the history must show who took it. */
    public function destroy(User $teacher): RedirectResponse
    {
        abort_unless($teacher->isTeacher(), 404);

        if ($teacher->attendanceSessions()->exists()) {
            return back()->with('error', __(':name has recorded attendance and cannot be deleted.', ['name' => $teacher->name]));
        }
        if ($teacher->teachingAssignments()->exists()) {
            return back()->with('error', __(':name still has teaching assignments. Give them to another teacher first.', ['name' => $teacher->name]));
        }

        $teacher->delete();

        return back()->with('status', __('Teacher deleted.'));
    }

    private function validated(Request $request, ?User $teacher = null): array
    {
        $request->merge([
            'phone' => preg_replace('/\D/', '', (string) $request->input('phone')),
            'email' => Str::lower(trim((string) $request->input('email'))) ?: null,
            'telegram_chat_id' => trim((string) $request->input('telegram_chat_id')) ?: null,
        ]);

        return $request->validate([
            'name_km' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits_between:8,12', Rule::unique('users')->ignore($teacher)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($teacher)],
            'telegram_chat_id' => ['nullable', 'regex:/^-?\d{5,20}$/'],
            'password' => [$teacher ? 'nullable' : 'required', Password::min(8)],
        ], [], [
            'telegram_chat_id' => __('Telegram chat ID'),
            'name_km' => __('Name (Khmer)'),
            'name' => __('Name (Latin)'),
            'phone' => __('Phone'),
            'email' => __('Email'),
            'password' => __('Password'),
        ]);
    }
}
