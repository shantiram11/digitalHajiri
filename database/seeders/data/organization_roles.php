<?php

declare(strict_types=1);

/**
 * Organization-role TEMPLATES. Each new organization gets its own copy of
 * these — never shares a row. See docs/authorization.md §3.2 + §5.2.
 */
return [
    [
        'slug'        => 'organization-admin',
        'name'        => 'Organization Admin',
        'description' => 'Full control inside the organization.',
        'permissions' => [
            'organization.view', 'organization.manage',
            'user.view', 'user.invite', 'user.manage',
            'role.view', 'role.manage',
            'permission.view', 'permission.assign',
            'employee.view', 'employee.create', 'employee.edit', 'employee.delete', 'employee.import',
            'department.view', 'department.manage',
            'designation.view', 'designation.manage',
            'device.view', 'device.manage', 'device.sync', 'device.monitor',
            'attendance.view', 'attendance.create', 'attendance.edit', 'attendance.delete',
            'attendance.approve', 'attendance.correct', 'attendance.sync',
            'leave.view', 'leave.create', 'leave.edit', 'leave.delete',
            'leave.approve', 'leave.manage-balances',
            'holiday.view', 'holiday.manage',
            'correction.view', 'correction.approve',
            'report.view', 'report.export',
            'audit.view', 'audit.export',
        ],
    ],
    [
        'slug'        => 'attendance-manager',
        'name'        => 'Attendance Manager',
        'description' => 'Daily attendance operations and device sync.',
        'permissions' => [
            'organization.view',
            'user.view',
            'role.view', 'permission.view',
            'employee.view', 'department.view', 'designation.view',
            'device.view', 'device.sync', 'device.monitor',
            'attendance.view', 'attendance.create', 'attendance.edit',
            'attendance.approve', 'attendance.correct', 'attendance.sync',
            'leave.view',
            'holiday.view',
            'correction.view', 'correction.approve',
            'report.view', 'report.export',
        ],
    ],
    [
        'slug'        => 'hr-officer',
        'name'        => 'HR Officer',
        'description' => 'Employee records, leave, holidays.',
        'permissions' => [
            'organization.view',
            'user.view', 'user.invite', 'user.manage',
            'role.view', 'permission.view',
            'employee.view', 'employee.create', 'employee.edit', 'employee.delete', 'employee.import',
            'department.view', 'department.manage',
            'designation.view', 'designation.manage',
            'attendance.view',
            'leave.view', 'leave.create', 'leave.edit', 'leave.delete', 'leave.approve', 'leave.manage-balances',
            'holiday.view', 'holiday.manage',
            'correction.view',
            'report.view', 'report.export',
            'audit.view', 'audit.export',
        ],
    ],
    [
        'slug'        => 'department-head',
        'name'        => 'Department Head',
        'description' => 'Department-scoped visibility and approval. Record-level scoping enforced by policies.',
        'permissions' => [
            'organization.view',
            'user.view',
            'employee.view',
            'department.view',
            'designation.view',
            'attendance.view', 'attendance.approve',
            'leave.view', 'leave.approve',
            'holiday.view',
            'correction.view', 'correction.approve',
            'report.view',
        ],
    ],
    [
        'slug'        => 'employee',
        'name'        => 'Employee',
        'description' => 'Own attendance + own leave. Record-level scoping enforced by policies.',
        'permissions' => [
            'organization.view',
            'attendance.view', 'attendance.correct',
            'leave.view', 'leave.create', 'leave.edit', 'leave.delete',
            'holiday.view',
            'correction.view',
            'report.view',
        ],
    ],
];
