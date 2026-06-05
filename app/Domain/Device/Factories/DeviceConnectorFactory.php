<?php

declare(strict_types=1);

namespace App\Domain\Device\Factories;

use App\Domain\Device\Contracts\AttendanceDeviceInterface;
use App\Domain\Device\Contracts\DeviceConnectorFactoryInterface;
use App\Domain\Device\Drivers\Fake\FakeDevice;
use App\Domain\Device\Drivers\ZKTeco\ZKTecoDevice;
use App\Domain\Device\Exceptions\UnsupportedDeviceVendorException;
use App\Domain\Device\Models\Device;
use Illuminate\Contracts\Container\Container;

final class DeviceConnectorFactory implements DeviceConnectorFactoryInterface
{
    /** @var array<string, class-string<AttendanceDeviceInterface>> */
    private array $drivers = [
        'zkteco' => ZKTecoDevice::class,
        'fake'   => FakeDevice::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function for(Device $device): AttendanceDeviceInterface
    {
        $vendor = strtolower($device->vendor);

        if (! isset($this->drivers[$vendor])) {
            throw UnsupportedDeviceVendorException::for($vendor);
        }

        /** @var AttendanceDeviceInterface $driver */
        $driver = $this->container->make($this->drivers[$vendor]);

        return $driver->for($device);
    }

    /**
     * Register additional vendor drivers at runtime.
     *
     * @param class-string<AttendanceDeviceInterface> $driverClass
     */
    public function register(string $vendor, string $driverClass): void
    {
        $this->drivers[strtolower($vendor)] = $driverClass;
    }
}
