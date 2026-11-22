<?php

namespace Tests\Feature;

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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Part 5.6: automatic absence alerts to parents, demo vs real Telegram, failures, and messages written by staff. */
class ParentMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private User $admin;

    private SchoolClass $class;

    private Student $student;

    private TeachingAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 10:00:00'); // Saturday
        config(['services.telegram.token' => null]); // demo mode unless a test turns it on
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->class = SchoolClass::where('name', '10A')->firstOrFail();
        $this->student = $this->class->students()->orderBy('id')->firstOrFail();
        $this->assignment = $this->class->teachingAssignments()->firstOrFail();
    }

    /** One lesson on a past day, with the student absent. */
    private function absentOn(string $date): void
    {
        $session = AttendanceSession::create([
            'school_class_id' => $this->class->id, 'subject_id' => $this->assignment->subject_id,
            'teacher_id' => $this->assignment->teacher_id, 'recorded_by' => $this->assignment->teacher_id,
            'date' => $date, 'period' => 1,
        ]);
        $session->records()->create(['student_id' => $this->student->id, 'status' => 'absent', 'absence_reason' => 'not_given']);
    }

    /** Today's lesson, saved the normal way (the same code the website and the app use). */
    private function takeToday(int $period, bool $absent = true): void
    {
        app(AttendanceRecorder::class)->save($this->admin, [
            'school_class_id' => $this->class->id, 'subject_id' => $this->assignment->subject_id,
            'date' => today()->toDateString(), 'period' => $period,
            'statuses' => [$this->student->id => $absent ? 'absent' : 'present'],
        ]);
    }

    public function test_the_third_day_absent_in_a_row_sends_one_alert_in_demo_mode(): void
    {
        $this->absentOn('2026-11-19');
        $this->absentOn('2026-11-20');

        $this->takeToday(1);
        $this->takeToday(2); // absent again the same day: no second message

        $message = ParentMessage::sole();
        $this->assertSame('absence_alert', $message->type);
        $this->assertSame('sent', $message->status);
        $this->assertTrue($message->simulated);
        $this->assertStringContainsString($this->student->name_km, $message->body);
        $this->assertStringContainsString('3 ថ្ងៃសិក្សាជាប់គ្នា', $message->body);
        $this->assertStringContainsString('absent 3 school days in a row', $message->body);
    }

    public function test_two_days_or_a_present_student_sends_nothing(): void
    {
        $this->absentOn('2026-11-20');
        $this->takeToday(1);                // only 2 days
        $this->assertSame(0, ParentMessage::count());

        $this->absentOn('2026-11-19');
        $this->takeToday(2, absent: false); // present today: the streak is broken
        $this->assertSame(0, ParentMessage::count());
    }

    public function test_with_a_bot_token_telegram_really_sends_and_failures_can_be_resent(): void
    {
        config(['services.telegram.token' => 'test-token']);
        $guardian = $this->student->guardians()->firstOrFail();
        $guardian->update(['telegram_chat_id' => '555111']);
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)
            ->push(['ok' => true, 'result' => []])]);
        $this->absentOn('2026-11-19');
        $this->absentOn('2026-11-20');

        $this->takeToday(1);
        $message = ParentMessage::sole();
        $this->assertSame('failed', $message->status);
        $this->assertSame('Bad Request: chat not found', $message->error);

        // The teacher's attendance was saved even though the message failed
        $this->assertSame(1, AttendanceSession::whereDate('date', '2026-11-21')->count());

        $this->actingAs($this->admin)->post("/messages/{$message->id}/resend")->assertSessionHas('status', 'Message delivered by Telegram.');
        $message->refresh();
        $this->assertSame('sent', $message->status);
        $this->assertFalse($message->simulated);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/bottest-token/sendMessage') && $request['chat_id'] === '555111');
    }

    public function test_staff_can_message_a_parent_and_add_a_follow_up(): void
    {
        $this->actingAs($this->admin)->get('/students/'.$this->student->id)->assertOk()
            ->assertSee('Message parent')
            ->assertSee('ខែនេះ '.$this->student->name_km, false);

        $this->actingAs($this->admin)->post("/students/{$this->student->id}/messages", [
            'body' => 'Hello, please call the school.', 'follow_up' => '1',
        ])->assertSessionHas('status', 'Message saved (demo: not really sent).');

        $message = ParentMessage::sole();
        $this->assertSame('manual', $message->type);
        $this->assertSame($this->admin->id, $message->sent_by);
        $follow = Intervention::sole();
        $this->assertSame($this->student->guardians()->first()->preferredChannel(), $follow->action); // telegram or sms
        $this->assertSame($message->channel, $follow->action);
        $this->assertSame('2026-12-05', $follow->follow_up_date->toDateString());
    }

    public function test_the_messages_page_shows_the_log_and_the_demo_warning(): void
    {
        $this->absentOn('2026-11-19');
        $this->absentOn('2026-11-20');
        $this->takeToday(1);

        $this->actingAs($this->admin)->get('/messages')->assertOk()
            ->assertSee('Demo mode: messages are written and saved here')
            ->assertSee('Absence alert')
            ->assertSee($this->student->name_latin)
            ->assertSee('Demo (not sent)');
        $this->actingAs($this->admin)->get('/messages?status=failed')->assertOk()->assertSee('No messages match this filter.');

        $teacher = User::findOrFail($this->assignment->teacher_id);
        $this->actingAs($teacher)->get('/messages')->assertForbidden();
    }
}
