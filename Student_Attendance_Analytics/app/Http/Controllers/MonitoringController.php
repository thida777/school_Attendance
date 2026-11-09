<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\AttendanceRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Attendance Monitoring (admin) / Attendance History (teacher): every saved record, with filters.
 * A record can be changed from here; after 24 hours a teacher's change becomes a request.
 */
class MonitoringController extends Controller
{
    public function __construct(private AttendanceRecorder $recorder) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'class' => ['nullable', 'integer'],
            'subject' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(AttendanceRecord::STATUSES)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->when($user->isTeacher(), fn ($q) => $q->where('attendance_sessions.teacher_id', $user->id))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('attendance_sessions.date', $date))
            ->when($filters['class'] ?? null, fn ($q, $id) => $q->where('attendance_sessions.school_class_id', $id))
            ->when($filters['subject'] ?? null, fn ($q, $id) => $q->where('attendance_sessions.subject_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('students.name_latin', 'like', "%{$search}%")
                ->orWhere('students.name_km', 'like', "%{$search}%")
                ->orWhere('students.student_code', 'like', "%{$search}%")));

        // Totals ignore the status filter, so Present + Absent = Records
        $totals = (clone $query)
            ->selectRaw("count(*) as total, sum(case when attendance_records.status = 'present' then 1 else 0 end) as present")
            ->first();

        $records = $query
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('attendance_records.status', $status))
            ->select('attendance_records.*')
            ->with(['student', 'session.schoolClass', 'session.subject', 'session.teacher'])
            ->withExists(['corrections as has_pending_request' => fn ($q) => $q->where('status', AttendanceCorrection::PENDING)])
            ->orderByDesc('attendance_sessions.date')->orderByDesc('attendance_sessions.period')->orderBy('students.name_latin')
            ->paginate(25)
            ->withQueryString();

        // A teacher only filters by their own classes and subjects
        $assignments = $user->isTeacher() ? $user->teachingAssignments()->get() : null;

        return view('attendance.monitoring', [
            'records' => $records,
            'filters' => $filters,
            'total' => (int) $totals->total,
            'present' => (int) $totals->present,
            'classes' => SchoolClass::when($assignments, fn ($q) => $q->whereIn('id', $assignments->pluck('school_class_id')))->orderBy('name')->get(),
            'subjects' => Subject::when($assignments, fn ($q) => $q->whereIn('id', $assignments->pluck('subject_id')))->orderBy('name_en')->get(),
            'recorder' => $this->recorder,
        ]);
    }

    /** Change one record (or, for a teacher after 24 hours, ask the admin to change it). */
    public function correct(Request $request, AttendanceRecord $record): RedirectResponse
    {
        $data = $request->validate([
            'new_status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], ['new_status' => __('New status'), 'reason' => __('Reason')]);

        $correction = $this->recorder->correct($request->user(), $record, $data['new_status'], $data['reason'] ?? null);

        return back()->with('status', $correction->status === AttendanceCorrection::PENDING
            ? __('Request sent. The admin will approve or reject it.')
            : __('Attendance changed and saved in the audit log.'));
    }
}
