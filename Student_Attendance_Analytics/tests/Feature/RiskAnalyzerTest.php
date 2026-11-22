<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\RiskAnalyzer;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The full risk rules: rate, days absent in a row, trend, absences with no reason, and the pattern. */
class RiskAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 10:00:00'); // a Saturday
        $class = SchoolClass::where('name', '12A')->firstOrFail();
        $this->student = $class->students()->orderBy('id')->firstOrFail();
    }

    /** One lesson of the student's class on that day and period, with this student's mark. */
    private function lesson(string $date, int $period, string $status, ?string $reason = null): void
    {
        $assignment = $this->student->schoolClass->teachingAssignments()->firstOrFail();
        $session = AttendanceSession::create([
            'school_class_id' => $this->student->school_class_id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'recorded_by' => $assignment->teacher_id,
            'date' => $date,
            'period' => $period,
        ]);
        $session->records()->create(['student_id' => $this->student->id, 'status' => $status, 'absence_reason' => $reason]);
    }

    /** Present in 2 lessons on each school day (Mon–Sat) from $from to $to. */
    private function presentDays(string $from, string $to): void
    {
        for ($day = Carbon::parse($from); $day->lte(Carbon::parse($to)); $day->addDay()) {
            if ($day->isSunday()) continue;
            $this->lesson($day->toDateString(), 1, 'present');
            $this->lesson($day->toDateString(), 2, 'present');
        }
    }

    private function risk()
    {
        return app(RiskAnalyzer::class)->forStudent($this->student->id);
    }

    public function test_a_student_without_records_has_no_level(): void
    {
        $risk = $this->risk();

        $this->assertNull($risk->rate);
        $this->assertNull($risk->level);
        $this->assertSame(0, $risk->daysInARow);
    }

    public function test_three_school_days_absent_in_a_row_is_high_even_with_a_good_rate(): void
    {
        $this->presentDays('2026-10-01', '2026-11-17');      // lots of Present: the rate stays above 90%
        foreach (['2026-11-18', '2026-11-19', '2026-11-20'] as $day) {
            $this->lesson($day, 1, 'absent', 'sick');
            $this->lesson($day, 2, 'absent', 'sick');
        }

        $risk = $this->risk();
        $this->assertGreaterThanOrEqual(90, $risk->rate);
        $this->assertSame(3, $risk->daysInARow);
        $this->assertSame('high', $risk->level);
        $this->assertSame('Follow up · message parent', $risk->action());
    }

    public function test_a_day_with_one_present_lesson_ends_the_count(): void
    {
        $this->presentDays('2026-10-01', '2026-11-17');
        $this->lesson('2026-11-18', 1, 'absent', 'sick');
        $this->lesson('2026-11-18', 2, 'absent', 'sick');
        $this->lesson('2026-11-19', 1, 'absent', 'sick');
        $this->lesson('2026-11-19', 2, 'present');            // came back for period 2
        $this->lesson('2026-11-20', 1, 'absent', 'sick');
        $this->lesson('2026-11-20', 2, 'absent', 'sick');

        $this->assertSame(1, $this->risk()->daysInARow);       // only 20 Nov counts
    }

    public function test_five_days_in_a_row_is_critical(): void
    {
        $this->presentDays('2026-10-01', '2026-11-13');
        foreach (['2026-11-14', '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19'] as $day) { // Sunday 15 is skipped
            $this->lesson($day, 1, 'absent');
        }

        $risk = $this->risk();
        $this->assertSame(5, $risk->daysInARow);
        $this->assertSame('critical', $risk->level);
    }

    public function test_a_falling_trend_raises_the_level(): void
    {
        // 4 weeks before: always present. Last 4 weeks: 1 absence every 6 lessons.
        $this->presentDays('2026-09-26', '2026-10-24');
        $count = 0;
        for ($day = Carbon::parse('2026-10-25'); $day->lte(Carbon::parse('2026-11-20')); $day->addDay()) {
            if ($day->isSunday()) continue;
            foreach ([1, 2] as $period) {
                $this->lesson($day->toDateString(), $period, ++$count % 6 === 0 ? 'absent' : 'present', 'sick');
            }
        }

        $risk = $this->risk();
        $this->assertLessThanOrEqual(-10, $risk->trend);       // about 100% → 83%
        $this->assertSame('high', $risk->level);
    }

    /** 3 absences in October too (with a reason), so the trend stays flat and only the reason decides. */
    private function sameAbsencesLastMonth(): void
    {
        foreach (['2026-10-06', '2026-10-13', '2026-10-15'] as $day) {
            $this->lesson($day, 3, 'absent', 'sick');
        }
    }

    public function test_absences_without_a_reason_this_month_make_it_medium(): void
    {
        $this->presentDays('2026-09-01', '2026-11-20');
        $this->sameAbsencesLastMonth();
        $this->lesson('2026-11-03', 3, 'absent');               // no reason
        $this->lesson('2026-11-10', 3, 'absent', 'not_given');
        $this->lesson('2026-11-12', 3, 'absent');

        $risk = $this->risk();
        $this->assertGreaterThanOrEqual(90, $risk->rate);
        $this->assertSame(3, $risk->unexplained);
        $this->assertSame('medium', $risk->level);
    }

    public function test_the_same_absences_with_a_reason_stay_low(): void
    {
        $this->presentDays('2026-09-01', '2026-11-20');
        $this->sameAbsencesLastMonth();
        foreach (['2026-11-03', '2026-11-10', '2026-11-12'] as $day) {
            $this->lesson($day, 3, 'absent', 'sick');
        }

        $this->assertSame('low', $this->risk()->level);
    }

    public function test_the_pattern_is_the_day_and_period_with_most_absences(): void
    {
        $this->presentDays('2026-10-01', '2026-11-20');
        foreach (['2026-10-05', '2026-10-12', '2026-10-19'] as $monday) {
            $this->lesson($monday, 3, 'absent', 'sick');
        }
        $this->lesson('2026-10-21', 4, 'absent', 'sick');       // one Wednesday

        $risk = $this->risk();
        $this->assertSame(1, $risk->patternDay);                // Monday
        $this->assertSame(3, $risk->patternPeriod);
    }

    public function test_class_cards_count_students_at_risk_with_the_full_rules(): void
    {
        $this->presentDays('2026-10-01', '2026-11-17');
        foreach (['2026-11-18', '2026-11-19', '2026-11-20'] as $day) {
            $this->lesson($day, 1, 'absent');
        }

        $counts = SchoolClass::atRiskCounts([$this->student->school_class_id]);
        $this->assertSame(1, $counts[$this->student->school_class_id]); // high because of 3 days in a row, not the rate
    }
}
