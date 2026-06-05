<?php

declare(strict_types=1);

namespace App\Domain\Device\Contracts;

use App\Domain\Device\Models\Device;

interface DeviceConnectorFactoryInterface
{
    /**
     * Return a driver bound to the given device row.
     */
    public function for(Device $device): AttendanceDeviceInterface;
}
