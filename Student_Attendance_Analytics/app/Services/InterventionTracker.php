<?php

namespace App\Services;

use App\Support\Risk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Measures whether attendance changed after a follow-up (Detect → Act → Measure → Evaluate).
 *
 *  Before = the student's rate in the 10 school days before the action date.
 *  After  = the rate in the 10 school days after it (or fewer, if it is closed early).
 *  Improved = +5 points or more; Declined = −5 or less; anything between = No clear change.
 * A "school day" is a day when the student had at least one lesson recorded.
 */
class InterventionTracker
{
    public const SCHOOL_DAYS = 10;

    public const CHANGE_POINTS = 5;

    public function rateBefore(int $studentId, Carbon $actionDate): ?float
    {
        $days = $this->days($studentId)->filter(fn ($rows, $date) => $date < $actionDate->toDateString());

        return $this->rate($days->slice(-self::SCHOOL_DAYS));
    }

    public function rateAfter(int $studentId, Carbon $actionDate, ?Carbon $today = null): ?float
    {
        $today = ($today ?? today())->toDateString();
        $days = $this->days($studentId)
            ->filter(fn ($rows, $date) => $date > $actionDate->toDateString() && $date <= $today);

        return $this->rate($days->take(self::SCHOOL_DAYS));
    }

    /** improved, declined or none; null while a rate is missing. */
    public static function result(?float $before, ?float $after): ?string
    {
        if ($before === null || $after === null) {
            return null;
        }
        $change = round($after - $before, 1);

        return match (true) {
            $change >= self::CHANGE_POINTS => 'improved',
            $change <= -self::CHANGE_POINTS => 'declined',
            default => 'none',
        };
    }

    /** The student's records grouped by date, oldest first: ['2026-10-01' => [statuses...]]. */
    private function days(int $studentId): Collection
    {
        return DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->where('r.student_id', $studentId)
            ->get(['s.date', 'r.status'])
            ->groupBy(fn ($r) => substr($r->date, 0, 10))
            ->sortKeys();
    }

    private function rate(Collection $days): ?float
    {
        $records = $days->flatten(1);

        return Risk::rate($records->where('status', 'present')->count(), $records->count());
    }
}
