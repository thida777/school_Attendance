<?php

namespace App\Services;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The attendance rules, in one place. The website and the Android app (API) both use this class.
 *
 *  1. A teacher records only their own class and subject (teaching assignment), and only for today.
 *  2. For 24 hours after a lesson is submitted, its teacher may change it directly.
 *     After 24 hours the teacher sends a correction request, and the admin approves or rejects it.
 *  3. The admin may record any class, today or a past day, and change any record.
 *     After 24 hours, the admin must give a reason.
 *  4. Every change to a saved record is written to the audit log (attendance_corrections). Nothing is deleted.
 *  5. No future dates. One lesson per class per period per day. Only active students are listed.
 */
class AttendanceRecorder
{
    public const EDIT_WINDOW_HOURS = 24;

    // ------------------------------------------------------------------ checks

    /** Throws an error when this user may not record or change this lesson. */
    public function check(User $user, int $classId, int $subjectId, Carbon $date, ?AttendanceSession $session = null): void
    {
        if ($date->isAfter(today())) {
            throw ValidationException::withMessages(['date' => __('Attendance cannot be taken for a future date.')]);
        }

        if (! $user->isTeacher()) {
            return;
        }

        if (! $this->teaches($user, $classId, $subjectId)) {
            throw new AuthorizationException(__('You do not teach this subject in this class.'));
        }
        if ($session && ! $this->isOpen($session)) {
            throw ValidationException::withMessages(['date' => __('This lesson was submitted more than 24 hours ago. Send a correction request from Attendance History.')]);
        }
        if (! $session && ! $date->isToday()) {
            throw ValidationException::withMessages(['date' => __('Teachers can only take attendance for today. Ask the admin to correct a past day.')]);
        }
    }

    public function teaches(User $user, int $classId, int $subjectId): bool
    {
        return TeachingAssignment::where('teacher_id', $user->id)
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    /** Within 24 hours of the first submission, the teacher may still change the lesson. */
    public function isOpen(AttendanceSession $session): bool
    {
        return $session->created_at->greaterThan(now()->subHours(self::EDIT_WINDOW_HOURS));
    }

    /** After 24 hours, every change needs a reason. */
    public function needsReason(?AttendanceSession $session): bool
    {
        return $session !== null && ! $this->isOpen($session);
    }

    // ------------------------------------------------------------------ taking attendance

    /**
     * The timetable of one day: a teacher gets their own lessons, the admin gets the whole school.
     * Each lesson has ->session (saved lesson or null) and ->size (active students).
     */
    public function schedule(User $user, Carbon $day): Collection
    {
        $slots = TimetableSlot::query()
            ->with(['schoolClass', 'subject', 'teacher'])
            ->where('weekday', $day->dayOfWeekIso)
            ->when($user->isTeacher(), fn ($q) => $q->where('teacher_id', $user->id))
            ->orderBy('period')
            ->get();

        $sessions = AttendanceSession::whereDate('date', $day->toDateString())
            ->withCount(['records', 'records as present_count' => fn ($q) => $q->where('status', 'present')])
            ->get()
            ->keyBy(fn ($s) => $s->school_class_id.'-'.$s->period);

        $sizes = Student::where('status', 'active')->selectRaw('school_class_id, count(*) as total')
            ->groupBy('school_class_id')->pluck('total', 'school_class_id');

        return $slots->map(function ($slot) use ($sessions, $sizes) {
            $slot->session = $sessions->get($slot->school_class_id.'-'.$slot->period);
            $slot->size = $sizes[$slot->school_class_id] ?? 0;

            return $slot;
        });
    }

    /** The lesson already saved for this class, day and period, if any. */
    public function existing(int $classId, Carbon $date, int $period): ?AttendanceSession
    {
        return AttendanceSession::where('school_class_id', $classId)
            ->whereDate('date', $date->toDateString())
            ->where('period', $period)
            ->first();
    }

    /** Active students of the class, plus anyone already recorded in this lesson. */
    public function students(int $classId, ?AttendanceSession $session = null): Collection
    {
        return Student::query()
            ->where('school_class_id', $classId)
            ->where(fn ($q) => $q->where('status', 'active')
                ->when($session, fn ($q) => $q->orWhereIn('id', $session->records()->select('student_id'))))
            ->orderBy('name_latin')
            ->get();
    }

    /**
     * Save one lesson (first submission, or a change to it).
     *
     * @param  array{school_class_id:int, subject_id:int, date:string, period:int, statuses?:array<int,string>, reasons?:array<int,string>, correction_reason?:string|null}  $data
     *         statuses: student id => present / absent (a student who is not sent is present)
     */
    public function save(User $user, array $data): AttendanceSession
    {
        $classId = (int) $data['school_class_id'];
        $subjectId = (int) $data['subject_id'];
        $period = (int) $data['period'];
        $date = Carbon::parse($data['date'])->startOfDay();
        $session = $this->existing($classId, $date, $period);

        $this->check($user, $classId, $subjectId, $date, $session);

        if ($session && $session->subject_id !== $subjectId) {
            throw ValidationException::withMessages(['period' => __('Period :period on this day is already recorded for :subject.', [
                'period' => $period,
                'subject' => $session->subject->name_en,
            ])]);
        }

        $reason = trim((string) ($data['correction_reason'] ?? '')) ?: null;
        $students = $this->students($classId, $session);
        $statuses = $data['statuses'] ?? [];
        if (array_diff(array_keys($statuses), $students->pluck('id')->all())) {
            throw ValidationException::withMessages(['statuses' => __('Some students are not in this class.')]);
        }

        $records = $session?->records()->get()->keyBy('student_id') ?? collect();
        $changes = $students->filter(fn ($s) => $records->has($s->id) && $records[$s->id]->status !== $this->statusOf($statuses, $s->id));
        if ($changes->isNotEmpty() && $this->needsReason($session) && ! $reason) {
            throw ValidationException::withMessages(['correction_reason' => __('Write why this past attendance is being changed.')]);
        }

        return DB::transaction(function () use ($user, $session, $classId, $subjectId, $period, $date, $students, $statuses, $records, $data, $reason) {
            $session ??= AttendanceSession::create([
                'school_class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => $this->teacherFor($user, $classId, $subjectId),
                'recorded_by' => $user->id,
                'date' => $date->toDateString(),
                'period' => $period,
            ]);

            foreach ($students as $student) {
                $status = $this->statusOf($statuses, $student->id);
                $absenceReason = $status === 'absent' ? $this->absenceReason($data['reasons'][$student->id] ?? null) : null;
                $record = $records->get($student->id);

                if (! $record) {
                    $session->records()->create(['student_id' => $student->id, 'status' => $status, 'absence_reason' => $absenceReason]);
                } elseif ($record->status !== $status) {
                    $this->apply($record, $user, $status, $reason, $absenceReason);
                } else {
                    $record->update(['absence_reason' => $absenceReason]); // a reason may be added later
                }
            }

            $session->touch(); // "last changed" time of the lesson

            return $session;
        });
    }

    // ------------------------------------------------------------------ corrections

    /**
     * Change one record from Attendance History.
     * Returns the log row: "applied" when the change was made, "pending" when it waits for the admin.
     */
    public function correct(User $user, AttendanceRecord $record, string $newStatus, ?string $reason): AttendanceCorrection
    {
        $reason = trim((string) $reason) ?: null;
        $session = $record->session;

        if ($record->status === $newStatus) {
            throw ValidationException::withMessages(['new_status' => __('The record already has this status.')]);
        }

        if ($user->isTeacher()) {
            if (! $this->teaches($user, $session->school_class_id, $session->subject_id)) {
                throw new AuthorizationException(__('You do not teach this subject in this class.'));
            }
            if (! $this->isOpen($session)) {
                return $this->request($user, $record, $newStatus, $reason);
            }
        }

        if ($this->needsReason($session) && ! $reason) {
            throw ValidationException::withMessages(['reason' => __('Write why this past attendance is being changed.')]);
        }

        return DB::transaction(fn () => $this->apply($record, $user, $newStatus, $reason));
    }

    /** After 24 hours a teacher asks; nothing changes until the admin approves. */
    private function request(User $teacher, AttendanceRecord $record, string $newStatus, ?string $reason): AttendanceCorrection
    {
        if (! $reason) {
            throw ValidationException::withMessages(['reason' => __('Write why this attendance should be changed.')]);
        }
        if ($record->corrections()->where('status', AttendanceCorrection::PENDING)->exists()) {
            throw ValidationException::withMessages(['new_status' => __('A request for this record is already waiting for the admin.')]);
        }

        return $record->corrections()->create([
            'changed_by' => $teacher->id,
            'old_status' => $record->status,
            'new_status' => $newStatus,
            'status' => AttendanceCorrection::PENDING,
            'reason' => $reason,
        ]);
    }

    public function approve(User $admin, AttendanceCorrection $request): void
    {
        $this->ensurePending($request);

        DB::transaction(function () use ($admin, $request) {
            $record = $request->record;
            $request->update([
                'old_status' => $record->status, // the status at the moment it really changed
                'status' => AttendanceCorrection::APPROVED,
                'decided_by' => $admin->id,
                'decided_at' => now(),
            ]);
            $record->update([
                'status' => $request->new_status,
                'absence_reason' => $request->new_status === 'absent' ? ($record->absence_reason ?? 'not_given') : null,
            ]);
        });
    }

    public function reject(User $admin, AttendanceCorrection $request, ?string $note): void
    {
        $this->ensurePending($request);

        $request->update([
            'status' => AttendanceCorrection::REJECTED,
            'decided_by' => $admin->id,
            'decided_at' => now(),
            'decision_note' => trim((string) $note) ?: null,
        ]);
    }

    // ------------------------------------------------------------------ helpers

    /** Change the record and write the change to the audit log. */
    private function apply(AttendanceRecord $record, User $user, string $status, ?string $reason, ?string $absenceReason = null): AttendanceCorrection
    {
        $correction = $record->corrections()->create([
            'changed_by' => $user->id,
            'old_status' => $record->status,
            'new_status' => $status,
            'status' => AttendanceCorrection::APPLIED,
            'reason' => $reason,
        ]);

        $record->update([
            'status' => $status,
            'absence_reason' => $status === 'absent' ? ($absenceReason ?? $record->absence_reason ?? 'not_given') : null,
        ]);

        return $correction;
    }

    private function ensurePending(AttendanceCorrection $request): void
    {
        if ($request->status !== AttendanceCorrection::PENDING) {
            throw ValidationException::withMessages(['request' => __('This request has already been decided.')]);
        }
    }

    private function statusOf(array $statuses, int $studentId): string
    {
        return ($statuses[$studentId] ?? 'present') === 'absent' ? 'absent' : 'present';
    }

    private function absenceReason(?string $reason): string
    {
        return in_array($reason, AttendanceRecord::ABSENCE_REASONS, true) ? $reason : 'not_given';
    }

    /** The lesson belongs to the subject teacher, also when the admin records it for them. */
    private function teacherFor(User $user, int $classId, int $subjectId): int
    {
        if ($user->isTeacher()) {
            return $user->id;
        }

        return TeachingAssignment::where('school_class_id', $classId)->where('subject_id', $subjectId)->value('teacher_id') ?? $user->id;
    }
}
