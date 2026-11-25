<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Intervention;
use App\Models\ParentMessage;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\AttendanceRecorder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Parents answer an absence alert with a button in Telegram (webhook → ParentReplies). */
class ParentRepliesTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private const SECRET = 'test-webhook-secret-1234567890';

    private const CHAT = '555111';

    private User $admin;

    private SchoolClass $class;

    private Student $student;

    private TeachingAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 10:00:00'); // Saturday
        config(['services.telegram.token' => 'test-token', 'services.telegram.webhook_secret' => self::SECRET]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->class = SchoolClass::where('name', '10A')->firstOrFail();
        $this->student = $this->class->students()->orderBy('id')->firstOrFail();
        $this->assignment = $this->class->teachingAssignments()->firstOrFail();
        $this->student->guardians()->update(['telegram_chat_id' => null]);
        $this->student->guardians()->firstOrFail()->update(['telegram_chat_id' => self::CHAT]);
    }

    private function absentOn(string $date, ?string $reason = 'not_given', int $period = 1): AttendanceRecord
    {
        $session = AttendanceSession::create([
            'school_class_id' => $this->class->id, 'subject_id' => $this->assignment->subject_id,
            'teacher_id' => $this->assignment->teacher_id, 'recorded_by' => $this->assignment->teacher_id,
            'date' => $date, 'period' => $period,
        ]);

        return $session->records()->create(['student_id' => $this->student->id, 'status' => 'absent', 'absence_reason' => $reason]);
    }

    /** Three school days absent in a row → the alert goes to the parent's Telegram. */
    private function alert(): ParentMessage
    {
        $this->absentOn('2026-11-19');
        $this->absentOn('2026-11-20', 'school_activity'); // a reason the teacher gave: kept
        app(AttendanceRecorder::class)->save($this->admin, [
            'school_class_id' => $this->class->id, 'subject_id' => $this->assignment->subject_id,
            'date' => today()->toDateString(), 'period' => 1, 'statuses' => [$this->student->id => 'absent'],
        ]);

        return ParentMessage::where('type', 'absence_alert')->sole();
    }

    private function tap(ParentMessage $message, string $reply, string $chat = self::CHAT, string $secret = self::SECRET)
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', $secret)->postJson('/api/telegram/webhook', [
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cb1', 'from' => ['id' => (int) $chat],
                'message' => ['message_id' => 77, 'chat' => ['id' => (int) $chat]],
                'data' => "reply:{$message->id}:{$reply}",
            ],
        ]);
    }

    public function test_the_alert_on_telegram_has_three_answer_buttons(): void
    {
        $message = $this->alert();

        $this->assertSame('sent', $message->status);
        $this->assertStringContainsString('សូមចុចប៊ូតុងខាងក្រោម', $message->body);
        Http::assertSent(function (Request $request) use ($message) {
            if (! str_contains($request->url(), '/sendMessage') || $request['chat_id'] !== self::CHAT) {
                return false;
            }
            $buttons = json_decode($request['reply_markup'], true)['inline_keyboard'][0];

            return count($buttons) === 3
                && $buttons[0]['callback_data'] === "reply:{$message->id}:sick"
                && $buttons[2]['text'] === '📞 សូមគ្រូទូរស័ព្ទមក';
        });
    }

    public function test_sick_writes_the_reason_on_the_streak_and_thanks_the_parent(): void
    {
        $older = $this->absentOn('2026-11-17');           // before a present day: not part of this streak
        $present = AttendanceSession::create([
            'school_class_id' => $this->class->id, 'subject_id' => $this->assignment->subject_id,
            'teacher_id' => $this->assignment->teacher_id, 'recorded_by' => $this->assignment->teacher_id,
            'date' => '2026-11-18', 'period' => 1,
        ]);
        $present->records()->create(['student_id' => $this->student->id, 'status' => 'present']);
        $message = $this->alert();

        $this->tap($message, 'sick')->assertOk();

        $message->refresh();
        $this->assertSame('sick', $message->reply);
        $this->assertNotNull($message->replied_at);
        $reasons = AttendanceRecord::where('student_id', $this->student->id)->where('status', 'absent')
            ->join('attendance_sessions as s', 's.id', '=', 'attendance_records.attendance_session_id')
            ->pluck('absence_reason', 's.date')->mapWithKeys(fn ($r, $d) => [substr($d, 0, 10) => $r]);
        $this->assertSame('sick', $reasons['2026-11-19']);
        $this->assertSame('school_activity', $reasons['2026-11-20']); // the teacher's reason is kept
        $this->assertSame('sick', $reasons['2026-11-21']);
        $this->assertSame('not_given', $older->fresh()->absence_reason); // another streak: unchanged

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/answerCallbackQuery') && $r['callback_query_id'] === 'cb1');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/editMessageReplyMarkup') && (int) $r['message_id'] === 77);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/sendMessage') && str_contains((string) $r['text'], 'អរគុណ!'));

        // The website shows the answer
        $this->actingAs($this->admin)->withSession(['locale' => 'en'])->get('/messages')
            ->assertSee('Parent replied:')->assertSee('🤒 Sick');
        $this->actingAs($this->admin)->withSession(['locale' => 'en'])->get('/students/'.$this->student->id)
            ->assertSee('Parent replied:');
    }

    public function test_please_call_me_opens_a_follow_up_for_the_homeroom_teacher(): void
    {
        $message = $this->alert();

        $this->tap($message, 'call_me')->assertOk();

        $follow = Intervention::sole();
        $this->assertSame('phone_call', $follow->action);
        $this->assertSame('open', $follow->status);
        $this->assertSame($this->class->homeroom_teacher_id, $follow->created_by);
        $this->assertSame('2026-11-28', $follow->follow_up_date->toDateString());
        $this->assertSame('call_me', $message->fresh()->reply);
        $this->assertSame(0, AttendanceRecord::where('absence_reason', 'sick')->count()); // no reason written
    }

    public function test_only_the_first_answer_counts(): void
    {
        $message = $this->alert();
        $this->tap($message, 'sick')->assertOk();

        $this->tap($message, 'family_matter')->assertOk();

        $this->assertSame('sick', $message->fresh()->reply);
        $this->assertSame(0, AttendanceRecord::where('absence_reason', 'family_matter')->count());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/answerCallbackQuery') && str_contains((string) $r['text'], 'Already received'));
    }

    public function test_another_chat_or_a_wrong_secret_cannot_answer(): void
    {
        $message = $this->alert();

        $this->tap($message, 'sick', chat: '999999')->assertOk();     // someone else's Telegram
        $this->assertNull($message->fresh()->reply);

        $this->tap($message, 'sick', secret: 'wrong-secret')->assertForbidden();
        $this->postJson('/api/telegram/webhook', ['update_id' => 2])->assertForbidden(); // no secret at all
        $this->assertNull($message->fresh()->reply);
        $this->assertSame(0, AttendanceRecord::where('absence_reason', 'sick')->count());
    }

    public function test_start_tells_the_person_their_chat_id(): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', self::SECRET)->postJson('/api/telegram/webhook', [
            'update_id' => 3, 'message' => ['message_id' => 1, 'chat' => ['id' => 424242], 'text' => '/start'],
        ])->assertOk();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/sendMessage')
            && $r['chat_id'] === '424242' && str_contains((string) $r['text'], 'Your Telegram chat ID is: 424242'));
    }
}
