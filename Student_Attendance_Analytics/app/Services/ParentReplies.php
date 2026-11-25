<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\Intervention;
use App\Models\ParentMessage;
use App\Models\User;
use App\Support\School;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What the school's Telegram bot does with what parents send (through the webhook).
 *
 *  - A tap on an answer button under an absence alert ("reply:<message id>:<code>"):
 *      sick / family_matter → the reason is written on the absences of that streak that have no reason yet
 *                              (a reason the teacher already gave is kept);
 *      call_me              → an open follow-up "Phone call" for the homeroom teacher.
 *    Only the parent the message was sent to can answer, and only once (the first tap counts).
 *    The parent sees a thank-you, and the buttons disappear from the message.
 *  - "/start" (or any text): the bot answers with the person's Telegram chat ID, so the school can
 *    link it to the parent (Parents → Edit → Telegram chat ID).
 */
class ParentReplies
{
    public function __construct(private Telegram $telegram, private InterventionTracker $tracker) {}

    /** One update from Telegram. */
    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->button($update['callback_query']);
        } elseif (isset($update['message']['chat']['id'])) {
            $this->text($update['message']);
        }
    }

    private function button(array $callback): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? $callback['from']['id'] ?? '');
        $result = $this->answer((string) ($callback['data'] ?? ''), $chatId);

        $this->telegram->answerButton((string) $callback['id'], $result['popup']);
        if ($result['done'] && isset($callback['message']['message_id'])) {
            $this->telegram->removeButtons($chatId, (int) $callback['message']['message_id']);
        }
        if ($result['thanks']) {
            $this->telegram->send($chatId, $result['thanks']);
        }
    }

    /**
     * Saves the parent's answer.
     *
     * @return array{done: bool, popup: string, thanks: ?string}  done = the buttons can go away
     */
    public function answer(string $data, string $chatId): array
    {
        $refused = ['done' => false, 'popup' => 'មិនអាចទទួលបានទេ។ / Not accepted.', 'thanks' => null];

        if (! preg_match('/^reply:(\d+):([a-z_]+)$/', $data, $m) || ! isset(ParentMessage::REPLIES[$m[2]])) {
            return $refused;
        }
        $message = ParentMessage::with(['guardian', 'student.schoolClass.homeroomTeacher'])->find((int) $m[1]);

        // Only the parent the alert was sent to may answer it
        if (! $message || $message->type !== 'absence_alert' || $chatId === ''
            || (string) $message->guardian?->telegram_chat_id !== $chatId) {
            return $refused;
        }

        if ($message->reply) {
            [$emoji, $km, $en] = ParentMessage::REPLIES[$message->reply];

            return ['done' => true, 'popup' => "បានទទួលរួចហើយ៖ {$emoji} {$km} / Already received: {$en}", 'thanks' => null];
        }

        $reply = $m[2];
        DB::transaction(function () use ($message, $reply) {
            $message->update(['reply' => $reply, 'replied_at' => now()]);

            if ($reply === 'call_me') {
                $this->askForCall($message);
            } else {
                $this->writeReason($message, $reply);
            }
        });

        [$emoji, $km, $en] = ParentMessage::REPLIES[$reply];
        $student = $message->student;
        $thanks = $reply === 'call_me'
            ? "អរគុណ! គ្រូបន្ទុកថ្នាក់នឹងទូរស័ព្ទទៅអ្នកឆាប់ៗ អំពី {$student->name_km}។\n\nThank you! The homeroom teacher will call you soon about {$student->name_latin}."
            : "អរគុណ! សាលាបានកត់ត្រាមូលហេតុ «{$km}» សម្រាប់ការអវត្តមានរបស់ {$student->name_km}។\n\nThank you! The school has noted the reason \"{$en}\" for {$student->name_latin}'s absence.";

        return ['done' => true, 'popup' => "{$emoji} {$km} ✓", 'thanks' => $thanks];
    }

    /** The reason goes on the absences of the streak the alert was about; reasons already given are kept. */
    private function writeReason(ParentMessage $message, string $reason): void
    {
        $ids = $this->streakRecordIds($message->student_id, $message->created_at);
        AttendanceRecord::whereIn('id', $ids)
            ->where(fn ($q) => $q->whereNull('absence_reason')->orWhere('absence_reason', 'not_given'))
            ->update(['absence_reason' => $reason]);
    }

    /**
     * Absent records of the school days in a row (with lessons) up to the alert day.
     * A day counts when the student missed every lesson recorded that day, the same rule as the alert.
     *
     * @return list<int>
     */
    private function streakRecordIds(int $studentId, Carbon $until): array
    {
        $rows = DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->where('r.student_id', $studentId)
            ->whereDate('s.date', '<=', $until->toDateString())
            ->orderByDesc('s.date')
            ->get(['r.id', 'r.status', DB::raw('substr(s.date, 1, 10) as day')]);

        $ids = [];
        foreach ($rows->groupBy('day') as $records) {     // newest day first
            if ($records->contains('status', 'present')) {
                break;                                     // the streak ends here
            }
            array_push($ids, ...$records->pluck('id')->all());
        }

        return $ids;
    }

    /** "Please call me" → an open follow-up for the homeroom teacher (or the admin when the class has none). */
    private function askForCall(ParentMessage $message): void
    {
        $student = $message->student;
        $owner = $student->schoolClass->homeroomTeacher ?? User::where('role', User::ROLE_ADMIN)->orderBy('id')->first();

        Intervention::create([
            'student_id' => $student->id,
            'created_by' => $owner->id,
            'action' => 'phone_call',
            'notes' => 'ឪពុកម្តាយសុំឲ្យគ្រូទូរស័ព្ទទៅ (ចុចប៊ូតុងក្នុង Telegram)។ The parent asked for a call (button in Telegram): '
                .($message->guardian->name).' '.($message->guardian->phone ?? ''),
            'action_date' => today(),
            'follow_up_date' => today()->addDays(7),
            'rate_before' => $this->tracker->rateBefore($student->id, today()),
            'status' => 'open',
        ]);
    }

    /** Links the parent of this invite code to the chat. The code then stops working. Returns the bot's answer. */
    public function join(string $code, string $chatId): string
    {
        $guardian = Guardian::with('students.schoolClass')->where('telegram_invite', $code)->first();
        if (! $guardian) {
            return "តំណភ្ជាប់នេះមិនអាចប្រើបានទៀតទេ។ សូមសុំតំណថ្មីពីសាលា។\n\nThis invite link can no longer be used. Please ask the school for a new one.";
        }

        $guardian->forceFill(['telegram_chat_id' => $chatId, 'telegram_invite' => null])->save();
        $children = $guardian->students->map(fn ($s) => "{$s->name_km} ({$s->schoolClass->name})")->join(', ');
        $childrenEn = $guardian->students->map(fn ($s) => "{$s->name_latin} ({$s->schoolClass->name})")->join(', ');

        return '✅ សួស្តី '.($guardian->name_km ?: $guardian->name)."! អ្នកបានភ្ជាប់ជាមួយសាលាហើយ សម្រាប់ {$children}។ "
            ."សាលានឹងផ្ញើព័ត៌មានអំពីវត្តមាននៅទីនេះ។\n\n"
            ."✅ Hello {$guardian->name}, you are now connected to the school for {$childrenEn}. "
            ."Attendance news will be sent here.\n\n".School::signature();
    }

    /**
     * Text sent to the bot.
     *  - "/start <invite code>" from a parent's invite link (QR code): link that parent to this chat, once.
     *  - Anything else (e.g. /start alone): tell the person their chat ID, to give to the school.
     */
    private function text(array $message): void
    {
        $chatId = (string) $message['chat']['id'];

        if (preg_match('/^\/start\s+([A-Za-z0-9]{24})$/', trim((string) ($message['text'] ?? '')), $m)) {
            $this->telegram->send($chatId, $this->join($m[1], $chatId));

            return;
        }
        $this->telegram->send($chatId,
            "សួស្តី! លេខ Telegram (Chat ID) របស់អ្នកគឺ៖ {$chatId}\nសូមផ្ញើលេខនេះទៅសាលា ដើម្បីទទួលព័ត៌មានអំពីវត្តមានកូនរបស់អ្នក។\n\n"
            ."Hello! Your Telegram chat ID is: {$chatId}\nPlease give this number to the school to receive attendance news about your child."
        );
    }
}
