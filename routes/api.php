<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Attendance\AttendanceEventController;
use App\Http\Controllers\Api\V1\Attendance\AttendanceRuleController;
use App\Http\Controllers\Api\V1\Attendance\AttendanceSummaryController;
use App\Http\Controllers\Api\V1\Audit\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Correction\AttendanceCorrectionController;
use App\Http\Controllers\Api\V1\Department\DepartmentController;
use App\Http\Controllers\Api\V1\Designation\DesignationController;
use App\Http\Controllers\Api\V1\Device\DeviceController;
use App\Http\Controllers\Api\V1\Device\DeviceMappingController;
use App\Http\Controllers\Api\V1\Employee\EmployeeController;
use App\Http\Controllers\Api\V1\Holiday\HolidayController;
use App\Http\Controllers\Api\V1\Invitation\InvitationController;
use App\Http\Controllers\Api\V1\Leave\LeaveBalanceController;
use App\Http\Controllers\Api\V1\Leave\LeaveRequestController;
use App\Http\Controllers\Api\V1\Leave\LeaveTypeController;
use App\Http\Controllers\Api\V1\Organization\OrganizationController;
use App\Http\Controllers\Api\V1\Permission\PermissionController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\Report\ReportController;
use App\Http\Controllers\Api\V1\Role\RoleController;
use App\Http\Controllers\Api\V1\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Public endpoints sit at the top. Everything below /v1 (except `auth/login`,
| `auth/forgot-password`, `auth/reset-password`, and the public invitation
| inspect/accept routes) requires Sanctum auth + the `organization` middleware
| which sets the per-request tenant context for the OrganizationScope.
*/

Route::prefix('v1')->group(function (): void {

    // ─── Public auth ────────────────────────────────────────────────────
    Route::post('auth/login',           [AuthController::class, 'login']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password',  [AuthController::class, 'resetPassword']);

    // ─── Public invitation inspect + accept ─────────────────────────────
    Route::get('invitations/{token}',   [InvitationController::class, 'show']);
    Route::post('invitations/accept',   [InvitationController::class, 'accept']);

    // ─── Authenticated (session OR bearer) ──────────────────────────────
    Route::middleware(['auth:sanctum', 'organization'])->group(function (): void {

        // Session lifecycle
        Route::post('auth/logout',              [AuthController::class, 'logout']);
        Route::get('auth/me',                   [AuthController::class, 'me']);
        Route::post('auth/select-organization', [AuthController::class, 'selectOrganization']);
        Route::post('auth/change-password',     [AuthController::class, 'changePassword']);

        // Bearer-token management
        Route::post('auth/token',          [AuthController::class, 'issueToken']);
        Route::get('auth/tokens',          [AuthController::class, 'listTokens']);
        Route::delete('auth/tokens/{id}',  [AuthController::class, 'revokeToken'])->whereNumber('id');

        // Profile
        Route::get('profile',  [ProfileController::class, 'show']);
        Route::patch('profile',[ProfileController::class, 'update']);

        // Invitations management (within org)
        Route::get('invitations',                       [InvitationController::class, 'index']);
        Route::post('invitations',                      [InvitationController::class, 'store']);
        Route::delete('invitations/{invitation}',       [InvitationController::class, 'revoke']);

        // Roles
        Route::apiResource('roles', RoleController::class);

        // Permissions
        Route::get('permissions',                      [PermissionController::class, 'index']);
        Route::get('users/{user}/permissions',         [PermissionController::class, 'userPermissions'])->whereNumber('user');
        Route::post('users/{user}/permissions',        [PermissionController::class, 'grant'])->whereNumber('user');
        Route::delete('users/{user}/permissions',      [PermissionController::class, 'revoke'])->whereNumber('user');

        // Users (org members)
        Route::get('users',                            [UserController::class, 'index']);
        Route::get('users/{user}',                     [UserController::class, 'show'])->whereNumber('user');
        Route::patch('users/{user}/status',            [UserController::class, 'setStatus'])->whereNumber('user');
        Route::delete('users/{user}/membership',       [UserController::class, 'removeFromOrganization'])->whereNumber('user');
        Route::post('users/{user}/roles',              [UserController::class, 'assignRole'])->whereNumber('user');
        Route::delete('users/{user}/roles/{role}',     [UserController::class, 'removeRole'])
            ->whereNumber('user')->whereNumber('role');

        // Organization (read-only at MVP)
        Route::apiResource('organizations', OrganizationController::class)->only(['index', 'show']);

        // Domain modules — read-only stubs from Phase 3
        Route::apiResource('employees',    EmployeeController::class)->only(['index', 'show']);
        Route::apiResource('departments',  DepartmentController::class)->only(['index', 'show']);
        Route::apiResource('designations', DesignationController::class)->only(['index', 'show']);

        Route::apiResource('devices',          DeviceController::class)->only(['index', 'show']);
        Route::apiResource('device-mappings',  DeviceMappingController::class)->only(['index', 'show']);

        Route::prefix('attendance')->group(function (): void {
            Route::apiResource('events',    AttendanceEventController::class)->only(['index', 'show']);
            Route::apiResource('summaries', AttendanceSummaryController::class)->only(['index', 'show']);
            Route::apiResource('rules',     AttendanceRuleController::class)->only(['index', 'show']);
        });

        Route::prefix('leave')->group(function (): void {
            Route::apiResource('types',    LeaveTypeController::class)->only(['index', 'show']);
            Route::apiResource('requests', LeaveRequestController::class)->only(['index', 'show']);
            Route::apiResource('balances', LeaveBalanceController::class)->only(['index', 'show']);
        });

        Route::apiResource('holidays',    HolidayController::class)->only(['index', 'show']);
        Route::apiResource('corrections', AttendanceCorrectionController::class)->only(['index', 'show']);
        Route::apiResource('audit-logs',  AuditLogController::class)->only(['index', 'show']);

        Route::get('reports/daily', [ReportController::class, 'index']);
        Route::get('reports/{id}',  [ReportController::class, 'show']);
    });
});
