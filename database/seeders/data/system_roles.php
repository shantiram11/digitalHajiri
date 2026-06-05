<?php

declare(strict_types=1);

/**
 * System roles (organization_id = NULL). Apply across every tenant.
 * Matrix in docs/authorization.md §5.1.
 */
return [
    [
        'slug'        => 'super-admin',
        'name'        => 'Super Admin',
        'description' => 'Unrestricted platform-wide access. Break-glass role.',
        'permissions' => '__ALL__', // wildcard handled by the seeder.
    ],
    [
        'slug'        => 'platform-support',
        'name'        => 'Platform Support',
        'description' => 'Reads every tenant, may impersonate to assist customers; cannot mutate.',
        'permissions' => [
            'system.tenants.manage',
            'system.impersonate',
            'system.audit.read',
            'audit.view',
            // Read-only across every module:
            'organization.view', 'user.view', 'role.view', 'permission.view',
            'employee.view', 'department.view', 'designation.view',
            'device.view', 'attendance.view', 'leave.view', 'holiday.view',
            'correction.view', 'report.view',
        ],
    ],
    [
        'slug'        => 'system-auditor',
        'name'        => 'System Auditor',
        'description' => 'Read-only cross-tenant access; can export audit log.',
        'permissions' => [
            'system.audit.read', 'audit.view', 'audit.export',
            'organization.view', 'user.view', 'role.view', 'permission.view',
            'employee.view', 'department.view', 'designation.view',
            'device.view', 'attendance.view', 'leave.view', 'holiday.view',
            'correction.view', 'report.view', 'report.export',
        ],
    ],
];
