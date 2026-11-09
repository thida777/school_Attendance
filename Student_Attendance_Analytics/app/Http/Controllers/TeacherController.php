<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\TimetableSlot;
use App\Models\User;
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
        ]);

        return $request->validate([
            'name_km' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits_between:8,12', Rule::unique('users')->ignore($teacher)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($teacher)],
            'password' => [$teacher ? 'nullable' : 'required', Password::min(8)],
        ], [], [
            'name_km' => __('Name (Khmer)'),
            'name' => __('Name (Latin)'),
            'phone' => __('Phone'),
            'email' => __('Email'),
            'password' => __('Password'),
        ]);
    }
}
