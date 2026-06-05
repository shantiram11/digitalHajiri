<?php

declare(strict_types=1);

namespace App\Domain\Device\DataTransferObjects;

/**
 * Employee record as represented on the device.
 *
 * Pushed to the device by createUser/updateUser. The actual fingerprint or
 * face template is NEVER part of this DTO — enrollment happens at the
 * physical device. We only describe identity, card number, and access level.
 */
final readonly class DeviceUserDto
{
    public function __construct(
        public string $deviceUserId,
        public string $name,
        public ?string $cardNumber,
        public string $privilege,
        public bool $isActive,
    ) {}
}
