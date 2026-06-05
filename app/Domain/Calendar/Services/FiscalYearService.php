<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Services;

use App\Domain\Organization\Models\Organization;
use Carbon\CarbonImmutable;

/**
 * Resolves the fiscal-year window for an organization.
 *
 * Nepali fiscal year runs Shrawan 1 → Ashad end (mid-July to mid-July AD).
 * Organizations may override `fiscal_year_start` for non-standard calendars
 * (e.g., a school year), so this service reads from the org row instead of
 * hard-coding.
 */
final class FiscalYearService
{
    public function current(Organization $organization, ?CarbonImmutable $reference = null): FiscalYearWindow
    {
        $reference ??= CarbonImmutable::now($organization->timezone);

        $start = $this->resolveStart($organization, $reference);
        $end   = $start->addYear()->subDay();

        return new FiscalYearWindow(
            startDate: $start,
            endDate: $end,
            label: $start->format('Y') . '-' . $end->format('y'),
        );
    }

    private function resolveStart(Organization $organization, CarbonImmutable $reference): CarbonImmutable
    {
        // Default to mid-July (Shrawan 1) if the org has not configured a date.
        $configured = $organization->fiscal_year_start
            ? CarbonImmutable::parse($organization->fiscal_year_start)
            : CarbonImmutable::create($reference->year, 7, 16);

        $candidate = CarbonImmutable::create($reference->year, $configured->month, $configured->day);

        return $reference->lessThan($candidate)
            ? $candidate->subYear()
            : $candidate;
    }
}
