<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecordResource;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Services\AttendanceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Attendance History screen of the app: the teacher's own records, and changes to them. */
class HistoryController extends Controller
{
    public function __construct(private AttendanceRecorder $recorder) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'class_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(AttendanceRecord::STATUSES)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $records = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->where('attendance_sessions.teacher_id', $request->user()->id)
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('attendance_sessions.date', $date))
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('attendance_sessions.school_class_id', $id))
            ->when($filters['subject_id'] ?? null, fn ($q, $id) => $q->where('attendance_sessions.subject_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('attendance_records.status', $status))
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('students.name_latin', 'like', "%{$search}%")
                ->orWhere('students.name_km', 'like', "%{$search}%")
                ->orWhere('students.student_code', 'like', "%{$search}%")))
            ->select('attendance_records.*')
            ->with(['student', 'session.schoolClass', 'session.subject'])
            ->withExists(['corrections as has_pending_request' => fn ($q) => $q->where('status', AttendanceCorrection::PENDING)])
            ->orderByDesc('attendance_sessions.date')->orderByDesc('attendance_sessions.period')->orderBy('students.name_latin')
            ->paginate(30)
            ->withQueryString();

        $records->each(fn ($r) => $r->can_change_directly = $this->recorder->isOpen($r->session));

        return RecordResource::collection($records);
    }

    /**
     * Change a record. Within 24 hours it changes at once ("applied");
     * after that it becomes a request for the admin ("pending"), and a reason is required.
     */
    public function correct(Request $request, AttendanceRecord $record): JsonResponse
    {
        $data = $request->validate([
            'new_status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $correction = $this->recorder->correct($request->user(), $record, $data['new_status'], $data['reason'] ?? null);
        $pending = $correction->status === AttendanceCorrection::PENDING;

        return response()->json([
            'message' => $pending
                ? __('Request sent. The admin will approve or reject it.')
                : __('Attendance changed and saved in the audit log.'),
            'correction' => [
                'id' => $correction->id,
                'status' => $correction->status,
                'old_status' => $correction->old_status,
                'new_status' => $correction->new_status,
            ],
        ], $pending ? 202 : 200);
    }
}
