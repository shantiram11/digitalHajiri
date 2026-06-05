<?php

declare(strict_types=1);

namespace App\Domain\Device\Exceptions;

use RuntimeException;

final class UnsupportedDeviceVendorException extends RuntimeException
{
    public static function for(string $vendor): self
    {
        return new self("No driver registered for device vendor [{$vendor}]");
    }
}
