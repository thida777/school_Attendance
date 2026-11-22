<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Intervention;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\InterventionTracker;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Interventions: add a follow-up (rate before), measure and close it (rate after, result), filters. */
class InterventionsTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private User $admin;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 10:00:00');
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->student = SchoolClass::where('name', '10A')->firstOrFail()->students()->orderBy('id')->firstOrFail();
    }

    /** One lesson a day for the student; $absent = the dates they missed. */
    private function lessons(string $from, string $to, array $absent = []): void
    {
        $assignment = $this->student->schoolClass->teachingAssignments()->firstOrFail();
        for ($day = Carbon::parse($from); $day->lte(Carbon::parse($to)); $day->addDay()) {
            if ($day->isSunday()) {
                continue;
            }
            $session = AttendanceSession::create([
                'school_class_id' => $this->student->school_class_id, 'subject_id' => $assignment->subject_id,
                'teacher_id' => $assignment->teacher_id, 'recorded_by' => $assignment->teacher_id,
                'date' => $day->toDateString(), 'period' => 1,
            ]);
            $isAbsent = in_array($day->toDateString(), $absent, true);
            $session->records()->create(['student_id' => $this->student->id, 'status' => $isAbsent ? 'absent' : 'present', 'absence_reason' => $isAbsent ? 'sick' : null]);
        }
    }

    private function make(string $action, string $date, ?string $followUp, string $status = 'open', ?float $before = null, ?float $after = null): Intervention
    {
        return Intervention::create([
            'student_id' => $this->student->id, 'created_by' => $this->admin->id, 'action' => $action,
            'action_date' => $date, 'follow_up_date' => $followUp, 'status' => $status,
            'rate_before' => $before, 'rate_after' => $after,
        ]);
    }

    public function test_the_rates_use_the_10_school_days_before_and_after(): void
    {
        // 2–13 Nov = 11 school days; 3 absences in the last 10 before the action on the 14th
        $this->lessons('2026-11-02', '2026-11-20', ['2026-11-02', '2026-11-05', '2026-11-10', '2026-11-12']);
        $tracker = app(InterventionTracker::class);

        $this->assertSame(70.0, $tracker->rateBefore($this->student->id, Carbon::parse('2026-11-14')));
        $this->assertSame(100.0, $tracker->rateAfter($this->student->id, Carbon::parse('2026-11-14')));
        $this->assertSame('improved', InterventionTracker::result(70.0, 75.0));
        $this->assertSame('none', InterventionTracker::result(70.0, 74.9));
        $this->assertSame('declined', InterventionTracker::result(80.0, 75.0));
    }

    public function test_adding_a_follow_up_saves_the_rate_before(): void
    {
        $this->lessons('2026-11-02', '2026-11-13', ['2026-11-10', '2026-11-12']);

        $this->actingAs($this->admin)->post('/interventions', [
            'student_id' => $this->student->id, 'action' => 'meeting',
            'action_date' => '2026-11-14', 'follow_up_date' => '2026-11-28', 'notes' => 'Met the mother.',
        ])->assertRedirect()->assertSessionHas('status');

        $item = Intervention::sole();
        $this->assertSame(80.0, $item->rate_before);
        $this->assertSame('open', $item->state());
        $this->assertSame($this->admin->id, $item->created_by);
    }

    public function test_the_form_checks_the_dates_and_the_action(): void
    {
        $this->actingAs($this->admin)->post('/interventions', [
            'student_id' => $this->student->id, 'action' => 'shouting',
            'action_date' => '2026-11-25', 'follow_up_date' => '2026-11-20',
        ])->assertSessionHasErrors(['action', 'action_date', 'follow_up_date']);
        $this->assertSame(0, Intervention::count());
    }

    public function test_measuring_closes_the_follow_up_with_the_result(): void
    {
        $this->lessons('2026-11-02', '2026-11-20', ['2026-11-02', '2026-11-05', '2026-11-10', '2026-11-12']);
        $item = $this->make('phone_call', '2026-11-14', '2026-11-20', 'open', 70.0);
        $this->assertSame('due', $item->state());

        $this->actingAs($this->admin)->post("/interventions/{$item->id}/close", ['outcome' => 'Back every day.'])
            ->assertSessionHas('status', 'Result measured: 70% → 100% (+30).');

        $item->refresh();
        $this->assertSame('closed', $item->status);
        $this->assertSame(100.0, $item->rate_after);
        $this->assertSame('improved', $item->result());
        $this->assertSame('Back every day.', $item->outcome);
    }

    public function test_it_cannot_be_measured_before_any_new_attendance(): void
    {
        $item = $this->make('sms', '2026-11-21', null);

        $this->actingAs($this->admin)->post("/interventions/{$item->id}/close")->assertSessionHas('error');
        $this->assertSame('open', $item->refresh()->status);
    }

    public function test_the_page_lists_filters_and_opens_the_form_for_a_student(): void
    {
        $this->make('meeting', '2026-11-01', '2026-11-15');                      // due
        $this->make('telegram', '2026-11-18', '2026-12-02');                     // open
        $this->make('home_visit', '2026-10-01', '2026-10-15', 'closed', 70, 82); // improved +12

        $this->actingAs($this->admin);
        $this->get('/interventions')->assertOk()
            ->assertSee('Showing 1–3 of 3')->assertSee('1 / 1')->assertSee('+12.0')->assertSee('Follow-up due');
        $this->get('/interventions?status=due')->assertOk()->assertSee('Showing 1–1 of 1')->assertSee('Measure the result: ');
        $this->get('/interventions?result=improved')->assertOk()->assertSee('Showing 1–1 of 1')->assertSee('70.0% →');
        $this->get('/interventions?result=declined')->assertOk()->assertSee('No follow-ups match this filter.');
        $this->get('/interventions?student='.$this->student->id.'&add=1')->assertOk()
            ->assertSee('Showing follow-ups for')
            ->assertSee('data-reopen', false);
        $this->get('/students/'.$this->student->id)->assertOk()->assertSee('Home visit')->assertSee('Improved');
    }

    public function test_teachers_cannot_open_interventions(): void
    {
        $teacher = User::where('role', User::ROLE_TEACHER)->firstOrFail();
        $this->actingAs($teacher)->get('/interventions')->assertForbidden();
        $this->actingAs($teacher)->post('/interventions', [])->assertForbidden();
    }
}
