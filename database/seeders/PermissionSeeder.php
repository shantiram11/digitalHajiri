<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $entries = require database_path('seeders/data/permissions.php');

        foreach ($entries as $entry) {
            Permission::query()->updateOrCreate(
                ['name' => $entry['name'], 'guard_name' => 'web'],
                [
                    'module'      => $entry['module'],
                    'description' => $entry['description'],
                ],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
