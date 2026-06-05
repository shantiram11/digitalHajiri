<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Strategies;

use App\Domain\Attendance\Models\AttendanceEvent;
use Illuminate\Support\Collection;

/**
 * Reduces a day's worth of attendance events to a (checkIn, checkOut, workedMinutes)
 * triple. Different organizations want different rules — first-in/last-out vs
 * pair-in/out — so this is a strategy interface, not a fixed function.
 */
interface PairingStrategy
{
    /**
     * @param Collection<int, AttendanceEvent> $events  Already date-filtered, time-sorted.
     * @return PairingResult
     */
    public function pair(Collection $events): PairingResult;
}
