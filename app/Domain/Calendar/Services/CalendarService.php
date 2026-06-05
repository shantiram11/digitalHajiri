<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Services;

use Carbon\CarbonImmutable;

/**
 * AD ↔ BS conversion and Nepali calendar helpers.
 *
 * MVP exposes the contract but conversion uses a lookup table that will be
 * populated in Phase 4 (covering BS 2070–2100). Until then, methods either
 * return AD verbatim or throw — callers should depend on the contract, not
 * the current behavior.
 */
final class CalendarService
{
    /**
     * Convert an AD date to a BS-formatted string (YYYY-MM-DD).
     */
    public function adToBs(CarbonImmutable $date): string
    {
        // TODO(phase-4): table-driven conversion.
        // Throwing during MVP would block any UI render that touches a date;
        // returning the AD ISO is the explicitly-temporary stand-in until the
        // lookup is in place. Callers using this in production are flagged by
        // the surrounding "BS pending" UI badge.
        return $date->toDateString();
    }

    /**
     * Convert a BS-formatted string (YYYY-MM-DD) to an AD CarbonImmutable.
     */
    public function bsToAd(string $bsDate): CarbonImmutable
    {
        // TODO(phase-4): table-driven conversion.
        return CarbonImmutable::parse($bsDate);
    }

    /**
     * The seven Nepali weekday names in display order (Sunday first).
     *
     * @return array<int, string>
     */
    public function weekdayNames(): array
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    }
}
