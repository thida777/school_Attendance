<?php

namespace Database\Seeders;

use App\Models\Intervention;
use App\Models\Student;
use App\Models\User;
use App\Services\InterventionTracker;
use App\Services\RiskAnalyzer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo follow-ups for the students the risk engine flags, so the Interventions page
 * shows every state: closed (with a measured result), due today, and still open.
 * The before / after rates are measured from the demo attendance, never typed in.
 */
class DemoInterventionSeeder extends Seeder
{
    /** [action, days ago, follow-up after (days), close it?, notes] for each student, most at risk first. */
    private const PLAN = [
        ['meeting', 30, 14, true, 'Parent says the student works at the family shop in the morning.'],
        ['telegram', 16, 14, false, 'Sent the attendance summary to the father.'],
        ['phone_call', 45, 14, true, 'Mother explained the student was sick; asked for a doctor note next time.'],
        ['talk', 20, 14, false, null],
        ['home_visit', 5, 14, false, 'Homeroom teacher will visit with the school office.'],
        ['sms', 50, 14, true, null],
        ['counselling', 9, 14, false, null],
    ];

    public function run(): void
    {
        Intervention::query()->delete();
        $tracker = app(InterventionTracker::class);
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        $students = Student::with('schoolClass')->where('status', 'active')->get()->keyBy('id');
        $order = ['critical' => 0, 'high' => 1, 'medium' => 2];
        $flagged = app(RiskAnalyzer::class)->forStudents($students->keys())
            ->filter(fn ($r) => isset($order[$r->level]))
            ->sortBy(fn ($r) => [$order[$r->level], $r->rate])
            ->values();

        foreach (self::PLAN as $i => [$action, $daysAgo, $followUp, $close, $notes]) {
            $risk = $flagged->get($i);
            if (! $risk) {
                break;
            }
            $student = $students[$risk->studentId];
            $date = today()->subDays($daysAgo);
            if ($date->isSunday()) {
                $date->subDay();
            }
            $item = Intervention::create([
                'student_id' => $student->id,
                // Half by the homeroom teacher, half by the admin
                'created_by' => $i % 2 ? $admin->id : ($student->schoolClass->homeroom_teacher_id ?? $admin->id),
                'action' => $action,
                'notes' => $notes,
                'action_date' => $date,
                'follow_up_date' => $date->copy()->addDays($followUp),
                'rate_before' => $tracker->rateBefore($student->id, $date),
                'status' => 'open',
            ]);
            if ($close) {
                $closedOn = Carbon::parse($item->follow_up_date)->min(today());
                $item->update([
                    'rate_after' => $tracker->rateAfter($student->id, $date, $closedOn),
                    'status' => 'closed',
                    'closed_at' => $closedOn->copy()->setTime(15, 0),
                ]);
            }
        }
    }
}
