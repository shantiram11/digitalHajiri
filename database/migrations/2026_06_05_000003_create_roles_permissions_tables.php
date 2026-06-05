<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC tables — Spatie laravel-permission shape, customised for tenant-awareness.
 *
 * Departures from Spatie's stock migration:
 *  - roles.organization_id (Spatie's team_foreign_key) is FK-constrained to organizations.
 *    Null = system role; value = org-scoped role.
 *  - roles gains `slug`, `description`, `is_system` for organization-level UI and seeding.
 *  - model_has_roles.organization_id and model_has_permissions.organization_id are
 *    also FK-constrained for referential integrity.
 *
 * See docs/authorization.md for the full reasoning.
 */
return new class extends Migration {
    public function up(): void
    {
        $tableNames  = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamFk      = $columnNames['team_foreign_key'] ?? 'organization_id';
        $pivotRole       = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        Schema::create($tableNames['permissions'], static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('module', 64)->nullable()->index();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teamFk): void {
            $table->id();
            $table->foreignId($teamFk)
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete()
                ->comment('Null = system role; value = organization-scoped role');
            $table->index($teamFk, 'roles_organization_id_index');
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('slug', 96);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique([$teamFk, 'name', 'guard_name'], 'roles_team_name_guard_unique');
            $table->unique([$teamFk, 'slug'], 'roles_team_slug_unique');
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teamFk): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')->on($tableNames['permissions'])
                ->cascadeOnDelete();

            $table->unsignedBigInteger($teamFk);
            $table->index($teamFk, 'model_has_permissions_team_foreign_key_index');
            // Direct user permissions are always tenant-scoped (system roles
            // grant their abilities through role_has_permissions, not direct
            // permissions) — so this FK is safe to enforce.
            $table->foreign($teamFk)
                ->references('id')->on('organizations')
                ->cascadeOnDelete();

            $table->primary(
                [$teamFk, $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                'model_has_permissions_permission_model_type_primary'
            );
        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teamFk): void {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')->on($tableNames['roles'])
                ->cascadeOnDelete();

            // Nullable so SYSTEM role assignments (team_id = null) can be stored.
            // Spatie's primary-key path requires a value, so when team is null we
            // store 0 by convention — see PermissionResolver. We model the FK
            // as nullable to keep referential integrity for org-scoped rows.
            // 0 = system-role assignment (no organization). Sentinel chosen over
            // NULL because composite primary keys reject NULL on MySQL 8+.
            $table->unsignedBigInteger($teamFk)->default(0);
            $table->index($teamFk, 'model_has_roles_team_foreign_key_index');

            $table->primary(
                [$teamFk, $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                'model_has_roles_role_model_type_primary'
            );
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')->on($tableNames['permissions'])
                ->cascadeOnDelete();
            $table->foreign($pivotRole)
                ->references('id')->on($tableNames['roles'])
                ->cascadeOnDelete();

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
