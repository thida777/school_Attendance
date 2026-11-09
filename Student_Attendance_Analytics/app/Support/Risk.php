<?php

namespace App\Support;

/**
 * The risk rules of Plan v2.0, in one place so the website, the app and the reports agree.
 *
 *  Critical: rate under 70%, or absent 5+ school days in a row
 *  High:     rate 70–79%, or 3–4 days in a row, or down 10+ points
 *  Medium:   rate 80–89%, or down 5–9 points
 *  Low:      everything else
 */
class Risk
{
    public const LEVELS = ['critical', 'high', 'medium', 'low'];

    public static function level(?float $rate, int $daysInARow = 0, float $trend = 0): ?string
    {
        if ($rate === null) {
            return null; // no attendance recorded yet
        }

        return match (true) {
            $rate < 70 || $daysInARow >= 5 => 'critical',
            $rate < 80 || $daysInARow >= 3 || $trend <= -10 => 'high',
            $rate < 90 || $trend <= -5 => 'medium',
            default => 'low',
        };
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

    /** Progress bar colour for an attendance rate. */
    public static function progress(?float $rate): string
    {
        return match (true) {
            $rate === null => '',
            $rate < 80 => 'progress-error',
            $rate < 90 => 'progress-warning',
            default => 'progress-success',
        };
    }

    /** Rate = present ÷ all records, one decimal. */
    public static function rate(int $present, int $total): ?float
    {
        return $total > 0 ? round(100 * $present / $total, 1) : null;
    }
}
