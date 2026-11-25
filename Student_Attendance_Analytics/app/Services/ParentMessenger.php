<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\ParentMessage;
use App\Models\Student;
use App\Models\User;
use App\Support\Risk;
use App\Support\School;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sends a message to a student's parents and keeps a copy of every message (parent_messages).
 *
 *  - Telegram: really sent when TELEGRAM_BOT_TOKEN is set and the parent joined the school's bot.
 *  - Otherwise (no token, or SMS: the school has no SMS provider yet) the message is saved
 *    as "simulated", so the demo works without internet and nothing is pretended.
 * Every message is in Khmer first, then English.
 */
class ParentMessenger
{
    /** Absent this many school days in a row → the parents get an automatic message. */
    public const STREAK_DAYS = 3;

    public function __construct(private RiskAnalyzer $analyzer, private Telegram $telegram) {}

    public function isLive(): bool
    {
        return $this->telegram->isLive();
    }

    /**
     * After a lesson is saved: a student whose absence streak reached 3 school days gets one message per streak.
     * Only for today's lessons (a past day typed in later is not news for the parents).
     *
     * @param  iterable<int>  $absentIds
     */
    public function afterLesson(iterable $absentIds, Carbon $date): void
    {
        if (! $date->isToday()) {
            return;
        }

        $students = Student::with(['guardians', 'schoolClass.homeroomTeacher'])->whereIn('id', collect($absentIds))->get();
        foreach ($this->analyzer->forStudents($students->pluck('id')) as $risk) {
            if ($risk->daysInARow < self::STREAK_DAYS) {
                continue;
            }
            $student = $students->firstWhere('id', $risk->studentId);
            $streakStart = $this->streakStart($student->id, $risk->daysInARow);
            $alreadySent = ParentMessage::where('student_id', $student->id)->where('type', 'absence_alert')
                ->where('created_at', '>=', $streakStart)->exists();
            if ($alreadySent) {
                continue;
            }
            foreach ($student->guardians as $guardian) {
                $this->send($student, $guardian, 'absence_alert', $this->absenceText($student, $guardian, $risk->daysInARow));
            }
        }
    }

    /** Save the message, and deliver it when possible. Never throws: a failed delivery is saved as "failed". */
    public function send(Student $student, Guardian $guardian, string $type, string $body, ?User $by = null): ParentMessage
    {
        $channel = $guardian->preferredChannel();
        $message = ParentMessage::create([
            'guardian_id' => $guardian->id, 'student_id' => $student->id, 'sent_by' => $by?->id,
            'type' => $type, 'channel' => $channel, 'body' => $body, 'status' => 'pending',
        ]);

        return $this->deliver($message);
    }

    /** Try (again) to deliver a saved message. */
    public function deliver(ParentMessage $message): ParentMessage
    {
        $message->loadMissing('guardian');

        if ($message->channel !== 'telegram' || ! $this->isLive() || ! $message->guardian->telegram_chat_id) {
            $message->update(['status' => 'sent', 'simulated' => true, 'error' => null, 'sent_at' => now()]);

            return $message;
        }

        $error = $this->telegram->send($message->guardian->telegram_chat_id, $message->body);
        $message->update($error === null
            ? ['status' => 'sent', 'simulated' => false, 'error' => null, 'sent_at' => now()]
            : ['status' => 'failed', 'error' => $error]);

        return $message;
    }

    /** Automatic message: absent several school days in a row. */
    public function absenceText(Student $student, Guardian $guardian, int $days): string
    {
        [$teacher, $teacherKm, $phone] = $this->contact($student);
        $date = today();

        return 'សួស្តី '.($guardian->name_km ?: $guardian->name)."!\n"
            ."កូនរបស់អ្នក {$student->name_km} ថ្នាក់ {$student->schoolClass->name} បានអវត្តមាន {$days} ថ្ងៃសិក្សាជាប់គ្នា (ថ្ងៃចុងក្រោយ {$date->copy()->locale('km')->translatedFormat('j F Y')})។ "
            ."សូមទាក់ទងគ្រូបន្ទុកថ្នាក់ {$teacherKm} {$phone}។\n\n"
            ."Hello {$guardian->name}, your child {$student->name_latin} ({$student->schoolClass->name}) has been absent "
            ."{$days} school days in a row (latest: {$date->format('j M Y')}). Please contact the homeroom teacher {$teacher}, {$phone}.\n\n"
            .School::signature();
    }

    /** Starting text for a message the admin writes by hand: the attendance this month. */
    public function summaryText(Student $student, Guardian $guardian): string
    {
        [$teacher, $teacherKm, $phone] = $this->contact($student);
        $month = $student->attendanceRecords()
            ->whereHas('session', fn ($q) => $q->whereDate('date', '>=', today()->startOfMonth()->toDateString()))
            ->get(['status']);
        $absent = $month->where('status', 'absent')->count();
        $rate = Risk::rate($month->count() - $absent, $month->count()) ?? '—';

        return 'សួស្តី '.($guardian->name_km ?: $guardian->name)."!\n"
            ."ខែនេះ {$student->name_km} ថ្នាក់ {$student->schoolClass->name} មានវត្តមាន {$rate}% (អវត្តមាន {$absent} ម៉ោង)។ "
            ."សូមជួយលើកទឹកចិត្តកូនឲ្យមករៀនទៀងទាត់។ ចង់សួរព័ត៌មាន សូមទាក់ទងគ្រូបន្ទុកថ្នាក់ {$teacherKm} {$phone}។\n\n"
            ."Hello {$guardian->name}, this month {$student->name_latin} ({$student->schoolClass->name}) has an attendance rate of {$rate}% "
            ."({$absent} lessons missed). Please help your child come to school every day. For questions, contact the homeroom teacher {$teacher}, {$phone}.\n\n"
            .School::signature();
    }

    /** Homeroom teacher: [name, Khmer name, phone] (or the school office). */
    private function contact(Student $student): array
    {
        $teacher = $student->schoolClass->homeroomTeacher;

        return [$teacher?->name ?? 'the school office', $teacher?->name_km ?: ($teacher?->name ?? 'ការិយាល័យសាលា'), $teacher?->phone ? trim(chunk_split($teacher->phone, 3, ' ')) : ''];
    }

    /** The first day of the current absence streak (the streak counts school days with lessons). */
    private function streakStart(int $studentId, int $days): Carbon
    {
        $date = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->where('r.student_id', $studentId)
            ->selectRaw('distinct substr(s.date, 1, 10) as day')
            ->orderByDesc('day')
            ->skip($days - 1)->take(1)
            ->value('day');

        return Carbon::parse($date ?? today())->startOfDay();
    }
}
