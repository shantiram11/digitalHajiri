<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Strategies;

use Carbon\CarbonImmutable;

final readonly class PairingResult
{
    /**
     * @param array<int, int> $eventIds  Raw event ids that contributed to this pairing.
     */
    public function __construct(
        public ?CarbonImmutable $checkInAt,
        public ?CarbonImmutable $checkOutAt,
        public int $workedMinutes,
        public array $eventIds,
    ) {}
}
