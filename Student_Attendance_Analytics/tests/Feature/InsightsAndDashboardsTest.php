<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Insights page (findings, drill-down, heat map) and the admin and teacher dashboards with real numbers. */
class InsightsAndDashboardsTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private User $admin;

    private User $teacher;

    private Student $student;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 10:00:00');
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->class = SchoolClass::where('name', '12A')->firstOrFail();
        $this->student = $this->class->students()->orderBy('id')->firstOrFail();
        $assignment = $this->class->teachingAssignments()->firstOrFail();
        $this->teacher = User::findOrFail($assignment->teacher_id);

        // 8 weeks of lessons for the whole class; the first student misses the last 3 school days
        for ($day = Carbon::parse('2026-09-26'); $day->lte(Carbon::parse('2026-11-20')); $day->addDay()) {
            if ($day->isSunday()) continue;
            $session = AttendanceSession::create([
                'school_class_id' => $this->class->id, 'subject_id' => $assignment->subject_id,
                'teacher_id' => $assignment->teacher_id, 'recorded_by' => $assignment->teacher_id,
                'date' => $day->toDateString(), 'period' => 1,
            ]);
            foreach ($this->class->students as $student) {
                $absent = $student->is($this->student) && $day->gte(Carbon::parse('2026-11-18'));
                $session->records()->create(['student_id' => $student->id, 'status' => $absent ? 'absent' : 'present', 'absence_reason' => $absent ? 'sick' : null]);
            }
        }
    }

    public function test_insights_show_findings_and_the_heat_map(): void
    {
        $this->actingAs($this->admin)->get('/insights')->assertOk()
            ->assertSee('What the data shows this month')
            ->assertSee('Students at High or Critical risk: 1. Absent 3+ school days in a row: 1.')
            ->assertSee('Grade 12')
            ->assertSee('Hotspots: day × period');
    }

    public function test_the_drill_down_goes_from_grade_to_class(): void
    {
        $this->actingAs($this->admin);
        $this->get('/insights?grade=12')->assertOk()->assertSee('12A')->assertSee('12B');
        $this->get('/insights?class='.$this->class->id)->assertOk()
            ->assertSee('Rate by subject')
            ->assertSee('Students to watch')
            ->assertSee($this->student->name_latin);
    }

    public function test_the_admin_dashboard_shows_the_numbers_and_who_needs_attention(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertSee('Attendance this month')
            ->assertSee('High or Critical risk')
            ->assertSee('Students who need attention')
            ->assertSee($this->student->name_latin)
            ->assertSee('chart-trend', false);
    }

    public function test_the_teacher_dashboard_shows_my_classes_and_students_to_watch(): void
    {
        $this->actingAs($this->teacher)->get('/my-dashboard')->assertOk()
            ->assertSee('My classes')
            ->assertSee('Students to watch')
            ->assertSee($this->student->name_latin)
            ->assertSee('At risk')
            ->assertSee('My attendance trend');
    }

    public function test_an_empty_school_does_not_break_the_pages(): void
    {
        AttendanceSession::query()->delete();
        $this->actingAs($this->admin);
        $this->get('/insights')->assertOk()->assertSee('Not enough attendance yet.');
        $this->get('/dashboard')->assertOk()->assertSee('No attendance has been recorded yet.');
    }
}
