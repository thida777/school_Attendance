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

/** At-Risk Students page and Student details page (admin). */
class AtRiskPagesTest extends TestCase
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
        $this->student = SchoolClass::where('name', '12A')->firstOrFail()->students()->orderBy('id')->firstOrFail();

        // 12A's first student: present for weeks, then absent the last 3 school days → High (3 in a row)
        $assignment = $this->student->schoolClass->teachingAssignments()->firstOrFail();
        for ($day = Carbon::parse('2026-10-01'); $day->lte(Carbon::parse('2026-11-20')); $day->addDay()) {
            if ($day->isSunday()) continue;
            $session = AttendanceSession::create([
                'school_class_id' => $this->student->school_class_id, 'subject_id' => $assignment->subject_id,
                'teacher_id' => $assignment->teacher_id, 'recorded_by' => $assignment->teacher_id,
                'date' => $day->toDateString(), 'period' => 1,
            ]);
            $absent = $day->gte(Carbon::parse('2026-11-18'));
            $session->records()->create(['student_id' => $this->student->id, 'status' => $absent ? 'absent' : 'present', 'absence_reason' => $absent ? 'sick' : null]);
        }
    }

    public function test_the_priority_list_shows_the_student_with_level_and_action(): void
    {
        $this->actingAs($this->admin)->get('/at-risk')->assertOk()
            ->assertSee('Priority list')
            ->assertSee($this->student->name_latin)
            ->assertSee('Follow up · message parent')     // High
            ->assertSee('How the risk level is decided');
    }

    public function test_filters_by_level_and_class(): void
    {
        $this->actingAs($this->admin);
        $this->get('/at-risk?level=critical')->assertOk()->assertDontSee($this->student->name_latin);
        $this->get('/at-risk?level=high&class='.$this->student->school_class_id)->assertOk()->assertSee($this->student->name_latin);
        $other = SchoolClass::where('name', '10A')->value('id');
        $this->get('/at-risk?class='.$other)->assertOk()->assertDontSee($this->student->name_latin);
    }

    public function test_the_list_downloads_as_a_csv_file_for_excel(): void
    {
        $response = $this->actingAs($this->admin)->get('/at-risk?export=csv');

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);              // so Excel shows Khmer correctly
        $this->assertStringContainsString($this->student->student_code, $csv);
        $this->assertStringContainsString('High', $csv);
    }

    public function test_student_details_explain_the_level(): void
    {
        $this->actingAs($this->admin)->get(route('students.show', $this->student))->assertOk()
            ->assertSee($this->student->name_latin)
            ->assertSee('High risk')
            ->assertSee('absent 3 school days in a row')
            ->assertSee('Weekly trend')
            ->assertSee('When absences happen')
            ->assertSee('Recent records');
    }

    public function test_teachers_cannot_open_the_admin_analytics(): void
    {
        $teacher = User::where('role', User::ROLE_TEACHER)->firstOrFail();
        $this->actingAs($teacher)->get('/at-risk')->assertForbidden();
        $this->actingAs($teacher)->get(route('students.show', $this->student))->assertForbidden();
    }
}
