<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Attendance Monitoring / History, correction requests, and the audit log. */
class CorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private TimetableSlot $slot;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 08:30:00'); // a Monday

        // The teacher submits a lesson with one absent student
        $this->slot = TimetableSlot::where('weekday', 1)->orderBy('period')->firstOrFail();
        $this->student = Student::where('school_class_id', $this->slot->school_class_id)->orderBy('id')->first();
        $this->actingAs($this->slot->teacher)->post('/attendance', [
            'school_class_id' => $this->slot->school_class_id,
            'subject_id' => $this->slot->subject_id,
            'date' => today()->toDateString(),
            'period' => $this->slot->period,
            'statuses' => [$this->student->id => 'absent'],
        ]);
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->first();
    }

    private function record(): AttendanceRecord
    {
        return AttendanceRecord::where('student_id', $this->student->id)->firstOrFail();
    }

    public function test_the_teacher_sees_their_records_and_the_admin_sees_all(): void
    {
        $this->get('/attendance/history')->assertOk()->assertSee($this->student->name_latin)->assertSee('Attendance History');

        $other = User::where('role', 'teacher')->where('id', '!=', $this->slot->teacher_id)->first();
        $this->actingAs($other)->get('/attendance/history')->assertOk()->assertDontSee($this->student->student_code);

        $this->actingAs($this->admin())->get('/attendance/history?status=absent')->assertOk()->assertSee($this->student->name_latin);
    }

    public function test_within_24_hours_the_teacher_changes_a_record_directly(): void
    {
        $this->post("/attendance/records/{$this->record()->id}/correct", ['new_status' => 'present'])
            ->assertSessionHas('status');

        $this->assertSame('present', $this->record()->status);
        $this->assertSame(AttendanceCorrection::APPLIED, AttendanceCorrection::sole()->status);
    }

    public function test_after_24_hours_the_teacher_sends_a_request_and_nothing_changes_yet(): void
    {
        $this->travel(25)->hours();

        // A reason is required for a request
        $this->post("/attendance/records/{$this->record()->id}/correct", ['new_status' => 'present'])->assertSessionHasErrors('reason');

        $this->post("/attendance/records/{$this->record()->id}/correct", ['new_status' => 'present', 'reason' => 'Was at the school office'])
            ->assertSessionHas('status');

        $this->assertSame('absent', $this->record()->status);
        $this->assertSame(AttendanceCorrection::PENDING, AttendanceCorrection::sole()->status);

        // Only one waiting request per record
        $this->post("/attendance/records/{$this->record()->id}/correct", ['new_status' => 'present', 'reason' => 'Again'])->assertSessionHasErrors('new_status');
    }

    public function test_the_admin_approves_a_request_and_the_record_changes(): void
    {
        $this->travel(25)->hours();
        $this->post("/attendance/records/{$this->record()->id}/correct", ['new_status' => 'present', 'reason' => 'Was at the school office']);
        $request = AttendanceCorrection::sole();

        $this->actingAs($this->admin())->get('/attendance/corrections')->assertOk()->assertSee('Was at the school office');
        $this->post("/attendance/corrections/{$request->id}/approve")->assertSessionHas('status');

        $this->assertSame('present', $this->record()->status);
        $this->assertNull($this->record()->absence_reason);
        $request->refresh();
        $this->assertSame([AttendanceCorrection::APPROVED, $this->admin()->id], [$request->status, $request->decided_by]);

        // A decided request cannot be decided again
        $this->post("/attendance/corrections/{$request->id}/reject")->assertSessionHasErrors('request');
    }

    public function test_the_admin_rejects_a_request_and_the_record_stays(): void
    {
        $this->travel(25)->hours();
        $this->post("/attendance/records/{$this->record()->id}/correct", ['new_status' => 'present', 'reason' => 'Please change']);
        $request = AttendanceCorrection::sole();

        $this->actingAs($this->admin())->post("/attendance/corrections/{$request->id}/reject", ['decision_note' => 'No evidence'])
            ->assertSessionHas('status');

        $this->assertSame('absent', $this->record()->status);
        $this->assertSame(['rejected', 'No evidence'], [$request->fresh()->status, $request->fresh()->decision_note]);
    }

    public function test_the_admin_corrects_after_24_hours_only_with_a_reason(): void
    {
        $this->travel(25)->hours();
        $this->actingAs($this->admin());

        $this->post("/attendance/records/{$this->record()->id}/correct", ['new_status' => 'present'])->assertSessionHasErrors('reason');
        $this->post("/attendance/records/{$this->record()->id}/correct", ['new_status' => 'present', 'reason' => 'Medical letter'])->assertSessionHas('status');

        $this->assertSame('present', $this->record()->status);
    }

    public function test_the_audit_log_shows_submissions_and_changes(): void
    {
        $this->post("/attendance/records/{$this->record()->id}/correct", ['new_status' => 'present']);

        $this->actingAs($this->admin())->get('/attendance/corrections')->assertOk()
            ->assertSee('Submitted')
            ->assertSee('Corrected')
            ->assertSee($this->slot->teacher->name);

        $this->get('/attendance/corrections?action=corrected')->assertOk()->assertDontSee('<span class="badge badge-soft badge-ghost">Submitted</span>', false);
    }

    public function test_teachers_cannot_open_the_corrections_page_or_decide(): void
    {
        $this->get('/attendance/corrections')->assertForbidden();
    }

    public function test_a_teacher_cannot_change_another_teachers_record(): void
    {
        $other = User::where('role', 'teacher')->where('id', '!=', $this->slot->teacher_id)->get()
            ->first(fn ($t) => ! $t->teachingAssignments()->where('school_class_id', $this->slot->school_class_id)->where('subject_id', $this->slot->subject_id)->exists());

        $this->actingAs($other)->post("/attendance/records/{$this->record()->id}/correct", ['new_status' => 'present', 'reason' => 'x'])
            ->assertForbidden();
    }
}
