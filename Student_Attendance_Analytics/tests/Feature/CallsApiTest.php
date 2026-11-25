<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\ParentMessage;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Teacher app v1.4: parents asking for a call, and the parents' Telegram invite in Class details. */
class CallsApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private SchoolClass $class;

    private User $homeroom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->class = SchoolClass::where('name', '10A')->firstOrFail();
        $this->homeroom = $this->class->homeroomTeacher;
    }

    /** What happens when a parent taps 📞 in Telegram: a message with the answer and an open "Phone call" follow-up. */
    private function parentAskedForACall(): Intervention
    {
        $student = $this->class->students()->orderBy('id')->firstOrFail();
        $guardian = $student->guardians()->firstOrFail();
        ParentMessage::create([
            'guardian_id' => $guardian->id, 'student_id' => $student->id, 'type' => 'absence_alert', 'channel' => 'telegram',
            'body' => 'Alert', 'status' => 'sent', 'reply' => 'call_me', 'replied_at' => now(),
        ]);

        return Intervention::create([
            'student_id' => $student->id, 'created_by' => $this->homeroom->id, 'action' => 'phone_call',
            'action_date' => today(), 'follow_up_date' => today()->addWeek(), 'status' => 'open',
        ]);
    }

    public function test_the_homeroom_teacher_sees_the_call_with_the_parents_phone_and_marks_it_done(): void
    {
        $call = $this->parentAskedForACall();
        Sanctum::actingAs($this->homeroom);

        $this->getJson('/api/v1/calls')->assertOk()
            ->assertJsonPath('calls.0.id', $call->id)
            ->assertJsonPath('calls.0.student.class', '10A')
            ->assertJsonPath('calls.0.parent.phone', $call->student->guardians()->first()->phone);

        $this->postJson("/api/v1/calls/{$call->id}/done", ['note' => 'Sick, back on Monday'])->assertOk();

        $this->assertStringContainsString('Sick, back on Monday', $call->fresh()->outcome);
        $this->assertSame('open', $call->fresh()->status); // still measured on the website later
        $this->getJson('/api/v1/calls')->assertOk()->assertJsonCount(0, 'calls');
    }

    public function test_another_teacher_cannot_see_or_close_the_call(): void
    {
        $call = $this->parentAskedForACall();
        $other = User::where('role', User::ROLE_TEACHER)->where('id', '!=', $this->homeroom->id)->firstOrFail();
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/calls')->assertOk()->assertJsonCount(0, 'calls');
        $this->postJson("/api/v1/calls/{$call->id}/done")->assertNotFound();
        $this->assertNull($call->fresh()->outcome);
    }

    public function test_class_details_give_a_qr_invite_only_for_parents_not_on_telegram(): void
    {
        config(['services.telegram.token' => 'test-token', 'services.telegram.username' => 'school_test_bot']);
        Http::fake();
        $students = $this->class->students()->orderBy('id')->get();
        $students[0]->guardians()->update(['telegram_chat_id' => '555']);
        $students[1]->guardians()->update(['telegram_chat_id' => null]);
        Sanctum::actingAs($this->homeroom);

        $json = collect($this->getJson("/api/v1/classes/{$this->class->id}/students")->assertOk()->json('data'));

        $this->assertNull($json->firstWhere('id', $students[0]->id)['parent']['invite_link']);
        $this->assertStringStartsWith('https://t.me/school_test_bot?start=', $json->firstWhere('id', $students[1]->id)['parent']['invite_link']);
    }
}
