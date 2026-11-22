<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Reports (page, Excel file, printable PDF) and the Teacher details page. */
class ReportsAndTeacherDetailsTest extends TestCase
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
        Carbon::setTestNow('2026-11-21 10:00:00');
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->class = SchoolClass::where('name', '10A')->firstOrFail();
        $this->student = $this->class->students()->orderBy('id')->firstOrFail();
        $this->assignment = $this->class->teachingAssignments()->with('subject')->firstOrFail();

        // 2–20 Nov: one lesson a day for 10A; the first student is absent on 3 days (2 sick, 1 no reason)
        $absent = ['2026-11-03' => 'sick', '2026-11-10' => 'sick', '2026-11-17' => 'not_given'];
        for ($day = Carbon::parse('2026-11-02'); $day->lte(Carbon::parse('2026-11-20')); $day->addDay()) {
            if ($day->isSunday()) {
                continue;
            }
            $session = AttendanceSession::create([
                'school_class_id' => $this->class->id, 'subject_id' => $this->assignment->subject_id,
                'teacher_id' => $this->assignment->teacher_id, 'recorded_by' => $this->assignment->teacher_id,
                'date' => $day->toDateString(), 'period' => 1,
            ]);
            foreach ($this->class->students as $student) {
                $reason = $student->is($this->student) ? ($absent[$day->toDateString()] ?? null) : null;
                $session->records()->create(['student_id' => $student->id, 'status' => $reason ? 'absent' : 'present', 'absence_reason' => $reason]);
            }
        }
    }

    public function test_the_report_page_shows_the_numbers_for_this_month(): void
    {
        $lessons = 17 * $this->class->students()->count(); // 17 school days in 2–20 Nov
        $rate = round(100 * ($lessons - 3) / $lessons, 1);

        $this->actingAs($this->admin)->get('/reports')->assertOk()
            ->assertSee('Average attendance rate')
            ->assertSee($rate.'%')
            ->assertSee('Summary by class')
            ->assertSee('chart-trend', false)
            ->assertSee('most on Tuesday, period 1');
    }

    public function test_filters_are_checked(): void
    {
        $this->actingAs($this->admin)->get('/reports?from=2026-11-20&to=2026-11-10')->assertSessionHasErrors('from');
        $this->actingAs($this->admin)->get('/reports?to=2026-12-31')->assertSessionHasErrors('to');
        $this->actingAs($this->admin)->get('/reports?from=2026-10-01&to=2026-10-31')->assertOk()
            ->assertSee('No attendance was recorded in this period.');
    }

    public function test_the_excel_file_has_one_row_per_student_with_the_reasons(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports?export=csv&class='.$this->class->id);
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $this->assertSame('Student ID', $lines[1][0]);
        $this->assertCount(2 + $this->class->students()->where('status', 'active')->count(), $lines);

        $row = collect($lines)->firstWhere(0, $this->student->student_code);
        $this->assertSame($this->student->name_km, $row[2]);
        $this->assertSame(['17', '14', '3'], [$row[4], $row[5], $row[6]]); // lessons, present, absent
        $this->assertSame(['2', '0', '0', '0', '1'], array_slice($row, 8, 5)); // sick, family, school, other, no reason
    }

    public function test_the_printable_report_is_ready_to_save_as_pdf(): void
    {
        $this->actingAs($this->admin)->get('/reports/print?class='.$this->class->id)->assertOk()
            ->assertSee('Attendance report')
            ->assertSee('1 November 2026 – 21 November 2026')
            ->assertSee('10A')
            ->assertSee('By subject')
            ->assertSee('Prepared by')
            ->assertSee('window.print()', false);
    }

    public function test_the_teacher_details_page(): void
    {
        $teacher = User::findOrFail($this->assignment->teacher_id);

        $this->actingAs($this->admin)->get('/teachers')->assertOk()->assertSee(route('teachers.show', $teacher), false);
        $this->actingAs($this->admin)->get('/teachers/'.$teacher->id)->assertOk()
            ->assertSee($teacher->name)
            ->assertSee('Teaching assignments')
            ->assertSee($this->assignment->subject->name_en)
            ->assertSee('Recent lessons')
            ->assertSee('Fri 20 Nov 2026');

        // Only teachers have a details page, and teachers cannot open it
        $this->actingAs($this->admin)->get('/teachers/'.$this->admin->id)->assertNotFound();
        $this->actingAs($teacher)->get('/teachers/'.$teacher->id)->assertForbidden();
        $this->actingAs($teacher)->get('/reports')->assertForbidden();
    }
}
