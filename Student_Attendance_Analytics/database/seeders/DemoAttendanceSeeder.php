<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\TimetableSlot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Demo attendance from the first day of the school year until yesterday, so the analytics pages
 * (charts, Insights, At-Risk, Reports) show something real at the defense.
 *
 * Every lesson on the timetable gets a record for every active student. The story:
 *  - most students miss a lesson now and then (about 2–3%);
 *  - Monday Period 1 has more absences (a pattern for Insights to find);
 *  - class 11B is a little weaker;
 *  - one "watch" student in each class;
 *  - a few clear cases: Critical (12B), trend falling in the last 4 weeks (11B),
 *    absent the last 4 school days in a row (10B), many absences with no reason (11A), Chea Rithy (10A).
 *
 * Fixed random seed, so it gives the same data every time. Today is never filled (it stays for the live demo),
 * and lessons already recorded are skipped, so it is safe to run again:
 *     php artisan db:seed --class=DemoAttendanceSeeder
 */
class DemoAttendanceSeeder extends Seeder
{
    // A whole day away: parents usually tell the school why
    private const REASONS = ['sick' => 50, 'not_given' => 20, 'family_matter' => 20, 'school_activity' => 5, 'other' => 5];

    public function run(): void
    {
        mt_srand(2026);
        $year = AcademicYear::current();
        $start = Carbon::parse($year->start_date)->startOfDay();
        $end = today()->subDay();
        $recentStart = today()->subDays(28);
        if ($end->lt($start)) {
            return;
        }

        $slots = TimetableSlot::orderBy('period')->get()->groupBy('weekday');
        $students = Student::where('status', 'active')->orderBy('student_code')->get()->groupBy('school_class_id');
        $profiles = $this->profiles($students);
        $streakDays = $this->lastSchoolDays($end, 4);           // the 10B student misses these 4 days completely
        $existing = DB::table('attendance_sessions')->get(['school_class_id', 'date', 'period'])
            ->map(fn ($s) => $s->school_class_id.'|'.substr($s->date, 0, 10).'|'.$s->period)->flip();

        DB::transaction(function () use ($start, $end, $recentStart, $slots, $students, $profiles, $streakDays, $existing) {
            $records = [];
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $daySlots = $slots->get($day->dayOfWeekIso);
                if (! $daySlots) {
                    continue; // Sunday
                }
                $date = $day->toDateString();
                $recent = $day->gte($recentStart);

                // Who misses the whole day (decided once per student per day)
                $awayToday = [];
                foreach ($profiles as $id => $p) {
                    $chance = $recent ? $p['recent'] : $p['before'];
                    $awayToday[$id] = in_array($date, $streakDays, true) && $p['streak'] ? 'family_matter'
                        : (mt_rand() / mt_getrandmax() < $chance ? $this->reason($p['reasons']) : null);
                }

                foreach ($daySlots as $slot) {
                    if (isset($existing[$slot->school_class_id.'|'.$date.'|'.$slot->period])) {
                        continue; // already recorded for real
                    }
                    [$startTime] = TimetableSlot::PERIODS[$slot->period] ?? ['07:00'];
                    $submittedAt = Carbon::parse($date.' '.$startTime)->addMinutes(mt_rand(5, 20));
                    $sessionId = DB::table('attendance_sessions')->insertGetId([
                        'school_class_id' => $slot->school_class_id,
                        'subject_id' => $slot->subject_id,
                        'teacher_id' => $slot->teacher_id,
                        'recorded_by' => $slot->teacher_id,
                        'date' => $date,
                        'period' => $slot->period,
                        'created_at' => $submittedAt,
                        'updated_at' => $submittedAt,
                    ]);

                    foreach ($students->get($slot->school_class_id, collect()) as $student) {
                        $reason = $awayToday[$student->id];
                        if ($reason === null) {
                            // Sometimes only one lesson is missed; more often on Monday Period 1
                            $lessonChance = 0.015 + ($day->isMonday() && $slot->period === 1 ? 0.07 : 0);
                            if (mt_rand() / mt_getrandmax() < $lessonChance) {
                                $reason = $this->reason(['not_given' => 60, 'sick' => 20, 'other' => 20]);
                            }
                        }
                        $records[] = [
                            'attendance_session_id' => $sessionId,
                            'student_id' => $student->id,
                            'status' => $reason === null ? 'present' : 'absent',
                            'absence_reason' => $reason,
                            'created_at' => $submittedAt,
                            'updated_at' => $submittedAt,
                        ];
                    }
                    if (count($records) >= 1000) {
                        DB::table('attendance_records')->insert($records);
                        $records = [];
                    }
                }
            }
            if ($records) {
                DB::table('attendance_records')->insert($records);
            }
        });
    }

    /**
     * Chance of missing a whole school day, before and during the last 4 weeks.
     *
     * @return array<int, array{before: float, recent: float, reasons: array<string,int>, streak: bool}>
     */
    private function profiles($studentsByClass): array
    {
        $profiles = [];
        foreach ($studentsByClass as $classStudents) {
            $className = optional($classStudents->first()->schoolClass)->name;
            foreach ($classStudents->values() as $i => $student) {
                $base = 0.008 + ($className === '11B' ? 0.012 : 0);
                $p = ['before' => $base, 'recent' => $base, 'reasons' => self::REASONS, 'streak' => false];

                if ($i === 5) {                                     // one "watch" student per class
                    $p['before'] = $p['recent'] = 0.09;
                }
                if ($student->name_latin === 'Chea Rithy') {        // 10A: often away
                    $p['before'] = $p['recent'] = 0.20;
                }
                if ($className === '12B' && $i === 1) {             // Critical
                    $p['before'] = $p['recent'] = 0.30;
                }
                if ($className === '11B' && $i === 4) {             // fine before, falling in the last 4 weeks
                    $p['before'] = 0.03;
                    $p['recent'] = 0.26;
                }
                if ($className === '10B' && $i === 2) {             // absent the last 4 school days in a row
                    $p['streak'] = true;
                }
                if ($className === '11A' && $i === 3) {             // many absences with no reason
                    $p['before'] = $p['recent'] = 0.10;
                    $p['reasons'] = ['not_given' => 90, 'sick' => 10];
                }
                $profiles[$student->id] = $p;
            }
        }

        return $profiles;
    }

    /** @return list<string> the last $count school days (Mon–Sat) up to $end */
    private function lastSchoolDays(Carbon $end, int $count): array
    {
        $days = [];
        for ($day = $end->copy(); count($days) < $count; $day->subDay()) {
            if (! $day->isSunday()) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }

    /** Pick a reason using the weights, e.g. ['sick' => 40, 'not_given' => 35, …]. */
    private function reason(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $reason => $weight) {
            if (($roll -= $weight) <= 0) {
                return $reason;
            }
        }

        return array_key_first($weights);
    }
}
