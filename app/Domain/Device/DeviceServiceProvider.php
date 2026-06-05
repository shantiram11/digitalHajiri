<?php

declare(strict_types=1);

namespace App\Domain\Device;

use App\Domain\Device\Contracts\DeviceConnectorFactoryInterface;
use App\Domain\Device\Factories\DeviceConnectorFactory;
use Illuminate\Support\ServiceProvider;

final class DeviceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            DeviceConnectorFactoryInterface::class,
            DeviceConnectorFactory::class,
        );
    }
}
