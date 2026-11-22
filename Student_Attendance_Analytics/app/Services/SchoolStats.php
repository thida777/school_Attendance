<?php

namespace App\Services;

use App\Support\Risk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * School-wide numbers for the dashboard and the Insights page, all worked out from the attendance records.
 *
 * Records are read once for a date range (one small row each: date, period, class, grade, subject, status, reason)
 * and then grouped in PHP. This works the same on MySQL and on the test database, and 4 weeks of a school
 * of this size is only a few thousand rows.
 */
class SchoolStats
{
    /** Default window: the last 4 weeks, the same as the risk trend. */
    public const WINDOW_DAYS = RiskAnalyzer::TREND_DAYS;

    /**
     * @param  int|null  $teacherId  only the lessons of this teacher (teacher dashboard)
     * @return Collection<int, object> records between the two dates (both included)
     */
    public function records(Carbon $from, Carbon $to, ?int $teacherId = null): Collection
    {
        return DB::table('attendance_records as r')
            ->join('attendance_sessions as s', 's.id', '=', 'r.attendance_session_id')
            ->join('school_classes as c', 'c.id', '=', 's.school_class_id')
            ->whereDate('s.date', '>=', $from->toDateString())
            ->whereDate('s.date', '<=', $to->toDateString())
            ->when($teacherId, fn ($q) => $q->where('s.teacher_id', $teacherId))
            ->get(['r.student_id', 'r.status', 'r.absence_reason', 's.date', 's.period',
                's.school_class_id', 's.subject_id', 'c.grade', 'c.name as class_name'])
            ->each(function ($r) {
                $r->date = substr($r->date, 0, 10);
                $r->weekday = Carbon::parse($r->date)->dayOfWeekIso; // 1 = Monday
            });
    }

    /** [last 4 weeks, the 4 weeks before], both ending today. */
    public function windows(?Carbon $today = null): array
    {
        $today = ($today ?? today())->copy();
        $recentFrom = $today->copy()->subDays(self::WINDOW_DAYS - 1);

        return [
            [$recentFrom, $today],
            [$recentFrom->copy()->subDays(self::WINDOW_DAYS), $recentFrom->copy()->subDay()],
        ];
    }

    /** Present ÷ all, one decimal; null for no records. */
    public static function rate(Collection $records): ?float
    {
        return Risk::rate($records->where('status', 'present')->count(), $records->count());
    }

    /** Points between two rates (recent − before); null when one is missing. */
    public static function trend(?float $recent, ?float $before): ?float
    {
        return $recent === null || $before === null ? null : round($recent - $before, 1);
    }

    /**
     * Rate and trend for each group, e.g. by class: groups($recent, $before, 'school_class_id').
     *
     * @return Collection<string|int, array{rate: ?float, trend: ?float, total: int, absent: int}>
     */
    public function groups(Collection $recent, Collection $before, string|callable $by): Collection
    {
        $before = $before->groupBy($by);

        return $recent->groupBy($by)->map(function (Collection $rows, $key) use ($before) {
            $rate = self::rate($rows);

            return [
                'rate' => $rate,
                'trend' => self::trend($rate, self::rate($before->get($key, collect()))),
                'total' => $rows->count(),
                'absent' => $rows->where('status', 'absent')->count(),
            ];
        });
    }

    /**
     * Rate for each weekday × period: [period][weekday] => rate (null when nothing was taught).
     *
     * @return array<int, array<int, ?float>>
     */
    public function heatmap(Collection $records, int $periods = 5): array
    {
        $cells = $records->groupBy(fn ($r) => $r->period.'-'.$r->weekday);
        $grid = [];
        foreach (range(1, $periods) as $period) {
            foreach (range(1, 6) as $day) {
                $grid[$period][$day] = self::rate($cells->get($period.'-'.$day, collect()));
            }
        }

        return $grid;
    }

    /**
     * Rate per week (Monday start) from $from to today, for the trend chart.
     *
     * @return Collection<int, array{label: string, rate: ?float}>
     */
    public function weekly(Collection $records, Carbon $from, ?Carbon $today = null): Collection
    {
        $today = $today ?? today();
        $byWeek = $records->groupBy(fn ($r) => Carbon::parse($r->date)->startOfWeek()->toDateString());
        $weeks = collect();
        for ($week = $from->copy()->startOfWeek(); $week->lte($today); $week->addWeek()) {
            $rows = $byWeek->get($week->toDateString(), collect());
            $weeks->push(['label' => $week->translatedFormat('j M'), 'rate' => self::rate($rows)]);
        }

        return $weeks->filter(fn ($w) => $w['rate'] !== null)->values();
    }

    /** Absences per weekday, Monday to Saturday. */
    public function absencesByWeekday(Collection $records): array
    {
        $absent = $records->where('status', 'absent')->countBy('weekday');

        return array_map(fn ($d) => $absent->get($d, 0), range(1, 6));
    }
}
