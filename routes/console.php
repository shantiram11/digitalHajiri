<?php

declare(strict_types=1);

use App\Domain\Device\Jobs\MonitorDeviceHealthJob;
use App\Domain\Device\Jobs\SyncDeviceLogsJob;
use App\Domain\Device\Models\Device;
use App\Support\Eloquent\Scopes\OrganizationScope;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler
|--------------------------------------------------------------------------
| Per-device sync every 5 minutes. Each device runs in its own job so a
| slow device cannot stall others. Health monitor runs every minute and
| is cheap (just a ping + a timestamp update).
*/

Schedule::call(function (): void {
    Device::withoutGlobalScope(OrganizationScope::class)
        ->where('status', '!=', 'disabled')
        ->each(function (Device $device): void {
            SyncDeviceLogsJob::dispatch($device->id);
        });
})->everyFiveMinutes()->name('dispatch-device-sync-jobs')->withoutOverlapping();

Schedule::job(new MonitorDeviceHealthJob)
    ->everyMinute()
    ->name('monitor-device-health')
    ->withoutOverlapping();
