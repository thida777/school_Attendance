<?php

namespace App\Support;

/**
 * Everything the analytics pages need to know about one student, calculated by RiskAnalyzer.
 * Nothing here is stored: it is always worked out from the attendance records, so it is never out of date.
 */
class StudentRisk
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $total,            // lessons with a record
        public readonly int $present,
        public readonly int $absent,
        public readonly ?float $rate,          // % present, null before any record
        public readonly int $daysInARow,       // school days absent in a row, up to the latest one
        public readonly ?float $trend,         // points: last 4 weeks minus the 4 weeks before (null = not enough data)
        public readonly int $unexplained,      // school days this month with an absence and no reason given
        public readonly ?int $patternDay,      // 1 = Monday … 6 = Saturday: the day with the most absences
        public readonly ?int $patternPeriod,   // the period with the most absences
        public readonly ?string $level,        // critical, high, medium, low, or null
    ) {}

    public function isAtRisk(): bool
    {
        return Risk::isAtRisk($this->level);
    }

    /** "Admin review · meet parent" … (English key, translated in the view with __()). */
    public function action(): ?string
    {
        return Risk::action($this->level);
    }
}
