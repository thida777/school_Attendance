<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Http\Resources\SubjectResource;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Services\AttendanceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Mark attendance screens (steps 1–3 and the success screen) of the app. */
class AttendanceController extends Controller
{
    public function __construct(private AttendanceRecorder $recorder) {}

    /**
     * The students of one lesson, with the saved status when it was already taken.
     * Send slot_id (a lesson from /today), or school_class_id + subject_id + date + period.
     */
    public function roster(Request $request): JsonResponse
    {
        $lesson = $this->lesson($request);
        $date = Carbon::parse($lesson['date']);
        $session = $this->recorder->existing($lesson['school_class_id'], $date, $lesson['period']);

        $this->recorder->check($request->user(), $lesson['school_class_id'], $lesson['subject_id'], $date, $session);

        $saved = $session?->records()->get()->keyBy('student_id') ?? collect();
        $students = $this->recorder->students($lesson['school_class_id'], $session)->each(function ($student) use ($saved) {
            $record = $saved->get($student->id);
            $student->mark = ['status' => $record?->status ?? 'present', 'absence_reason' => $record?->absence_reason];
        });
        $time = TimetableSlot::PERIODS[$lesson['period']] ?? null;

        return response()->json([
            'lesson' => [
                'class' => SchoolClass::find($lesson['school_class_id'])->only(['id', 'name']),
                'subject' => SubjectResource::make(Subject::find($lesson['subject_id'])),
                'date' => $date->toDateString(),
                'period' => $lesson['period'],
                'start_time' => $time[0] ?? null,
                'end_time' => $time[1] ?? null,
            ],
            'session_id' => $session?->id,
            'already_taken' => $session !== null,
            'needs_reason' => $this->recorder->needsReason($session),
            'absence_reasons' => AttendanceRecord::ABSENCE_REASONS,
            'students' => StudentResource::collection($students),
        ]);
    }

    /**
     * Submit a lesson. Body:
     * { "school_class_id": 1, "subject_id": 2, "date": "2026-11-09", "period": 1,
     *   "records": [ { "student_id": 5, "status": "absent", "absence_reason": "sick" } ] }
     * Students not in "records" are present.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'date' => ['required', 'date'],
            'period' => ['required', 'integer', Rule::in(array_keys(TimetableSlot::PERIODS))],
            'records' => ['array'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'records.*.absence_reason' => ['nullable', Rule::in(AttendanceRecord::ABSENCE_REASONS)],
            'correction_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $records = collect($data['records'] ?? []);
        $session = $this->recorder->save($request->user(), [
            ...$data,
            'statuses' => $records->pluck('status', 'student_id')->all(),
            'reasons' => $records->pluck('absence_reason', 'student_id')->filter()->all(),
        ]);

        $session->load('records.student');
        $absent = $session->records->where('status', 'absent');

        return response()->json([
            'message' => __('Attendance submitted'),
            'session_id' => $session->id,
            'present' => $session->records->count() - $absent->count(),
            'absent' => $absent->count(),
            'absent_students' => $absent->map(fn ($r) => [
                'id' => $r->student->id,
                'name_latin' => $r->student->name_latin,
                'name_km' => $r->student->name_km,
                'absence_reason' => $r->absence_reason,
            ])->values(),
        ], $session->wasRecentlyCreated ? 201 : 200);
    }

    /** @return array{school_class_id:int, subject_id:int, date:string, period:int} */
    private function lesson(Request $request): array
    {
        if ($request->filled('slot_id')) {
            $slot = TimetableSlot::findOrFail($request->integer('slot_id'));

            return ['school_class_id' => $slot->school_class_id, 'subject_id' => $slot->subject_id, 'date' => today()->toDateString(), 'period' => $slot->period];
        }

        $data = $request->validate([
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'date' => ['required', 'date'],
            'period' => ['required', 'integer', Rule::in(array_keys(TimetableSlot::PERIODS))],
        ]);

        return array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $data);
    }
}
