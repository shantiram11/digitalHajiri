<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Strategies;

use App\Domain\Attendance\Models\AttendanceEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Earliest event of the day = check-in. Latest = check-out. Everything
 * in between is ignored for time accounting. This is the default policy
 * matching most Nepali government offices' practice.
 */
final class FirstInLastOutStrategy implements PairingStrategy
{
    public function pair(Collection $events): PairingResult
    {
        if ($events->isEmpty()) {
            return new PairingResult(null, null, 0, []);
        }

        /** @var AttendanceEvent $first */
        $first = $events->first();
        /** @var AttendanceEvent $last */
        $last  = $events->last();

        $checkIn  = CarbonImmutable::instance($first->event_timestamp);
        $checkOut = $first->id === $last->id
            ? null
            : CarbonImmutable::instance($last->event_timestamp);

        $worked = $checkOut === null
            ? 0
            : (int) $checkIn->diffInMinutes($checkOut);

        return new PairingResult(
            checkInAt: $checkIn,
            checkOutAt: $checkOut,
            workedMinutes: $worked,
            eventIds: $events->pluck('id')->all(),
        );
    }
}
