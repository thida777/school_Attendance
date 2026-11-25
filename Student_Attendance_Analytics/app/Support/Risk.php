<?php

namespace App\Support;

/**
 * The risk rules of Plan v2.0, in one place so the website, the app and the reports agree.
 *
 *  Critical: rate under 70%, or absent 5+ school days in a row
 *  High:     rate 70–79%, or 3–4 days in a row, or down 10+ points
 *  Medium:   rate 80–89%, or down 5–9 points, or 3+ absences with no reason this month
 *  Low:      everything else
 * The highest matching level is used. The numbers come from App\Services\RiskAnalyzer.
 */
class Risk
{
    public const LEVELS = ['critical', 'high', 'medium', 'low'];

    public static function level(?float $rate, int $daysInARow = 0, float $trend = 0, int $unexplained = 0): ?string
    {
        if ($rate === null) {
            return null; // no attendance recorded yet
        }

        return match (true) {
            $rate < 70 || $daysInARow >= 5 => 'critical',
            $rate < 80 || $daysInARow >= 3 || $trend <= -10 => 'high',
            $rate < 90 || $trend <= -5 || $unexplained >= 3 => 'medium',
            default => 'low',
        };
    }

    /** What to do first for each level (At-Risk page). English key; translate with __(). */
    public static function action(?string $level): ?string
    {
        return match ($level) {
            'critical' => 'Admin review · meet parent',
            'high' => 'Follow up · message parent',
            'medium' => 'Watch · check reason',
            default => null,
        };
    }

    /**
     * The word people see for a level. It says what to do: Good (nothing), Watch (keep an eye),
     * High risk / Critical (act now). The levels themselves (low … critical) do not change.
     */
    public static function label(?string $level): string
    {
        return __(match ($level) {
            'critical' => 'Critical',
            'high' => 'High risk',
            'medium' => 'Watch',
            'low' => 'Good',
            default => '—',
        });
    }

    /** daisyUI badge colour for each level. */
    public static function badge(?string $level): string
    {
        return match ($level) {
            'critical' => 'badge-error',
            'high' => 'badge-warning',
            'medium' => 'badge-info',
            'low' => 'badge-success',
            default => 'badge-ghost',
        };
    }

    /**
     * One colour scale for every attendance rate, so a number never has two colours:
     * 90% and more = green, 80–89% = orange, under 80% = red.
     */
    public static function tone(?float $rate): string
    {
        return match (true) {
            $rate === null => 'neutral',
            $rate < 80 => 'error',
            $rate < 90 => 'warning',
            default => 'success',
        };
    }

    /** Progress bar colour for an attendance rate. */
    public static function progress(?float $rate): string
    {
        return $rate === null ? '' : 'progress-'.self::tone($rate);
    }

    /** Badge colour for an attendance rate (same scale as the bar). */
    public static function rateBadge(?float $rate): string
    {
        return $rate === null ? 'badge-ghost' : 'badge-'.self::tone($rate);
    }

    /** A student counts as "at risk" from level High (rate under 80%, or later: absent days in a row). */
    public static function isAtRisk(?string $level): bool
    {
        return in_array($level, ['critical', 'high'], true);
    }

    /** Rate = present ÷ all records, one decimal. */
    public static function rate(int $present, int $total): ?float
    {
        return $total > 0 ? round(100 * $present / $total, 1) : null;
    }
}
