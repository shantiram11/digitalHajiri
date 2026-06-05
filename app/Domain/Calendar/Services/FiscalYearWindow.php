<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Services;

use Carbon\CarbonImmutable;

final readonly class FiscalYearWindow
{
    public function __construct(
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public string $label,
    ) {}

    public function contains(CarbonImmutable $date): bool
    {
        return $date->betweenIncluded($this->startDate, $this->endDate);
    }
}
