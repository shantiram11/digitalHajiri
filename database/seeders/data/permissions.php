<?php

declare(strict_types=1);

/**
 * Complete permission catalog. Add new rows here; do not change the resolver.
 * See docs/authorization.md §4.3 for the full breakdown.
 *
 * Schema per entry:
 *   ['name' => string, 'module' => string, 'description' => string]
 */
return [
    // attendance
    ['name' => 'attendance.view',    'module' => 'attendance', 'description' => 'View attendance summaries and events.'],
    ['name' => 'attendance.create',  'module' => 'attendance', 'description' => 'Manually create an attendance event.'],
    ['name' => 'attendance.edit',    'module' => 'attendance', 'description' => 'Edit summary metadata.'],
    ['name' => 'attendance.delete',  'module' => 'attendance', 'description' => 'Soft-archive a summary.'],
    ['name' => 'attendance.approve', 'module' => 'attendance', 'description' => 'Approve overtime, corrections, ad-hoc rules.'],
    ['name' => 'attendance.correct', 'module' => 'attendance', 'description' => 'File an attendance correction.'],
    ['name' => 'attendance.sync',    'module' => 'attendance', 'description' => 'Trigger an on-demand device sync.'],

    // leave
    ['name' => 'leave.view',             'module' => 'leave', 'description' => 'View leave requests and balances.'],
    ['name' => 'leave.create',           'module' => 'leave', 'description' => 'Apply for leave.'],
    ['name' => 'leave.edit',             'module' => 'leave', 'description' => 'Edit pending leave requests.'],
    ['name' => 'leave.delete',           'module' => 'leave', 'description' => 'Cancel a leave request.'],
    ['name' => 'leave.approve',          'module' => 'leave', 'description' => 'Approve or reject leave requests.'],
    ['name' => 'leave.manage-balances',  'module' => 'leave', 'description' => 'Adjust leave balances.'],

    // device
    ['name' => 'device.view',    'module' => 'device', 'description' => 'View devices and sync history.'],
    ['name' => 'device.manage',  'module' => 'device', 'description' => 'Create, edit, delete devices and mappings.'],
    ['name' => 'device.sync',    'module' => 'device', 'description' => 'Trigger sync for any device in the organization.'],
    ['name' => 'device.monitor', 'module' => 'device', 'description' => 'Receive device health alerts.'],

    // employee
    ['name' => 'employee.view',   'module' => 'employee', 'description' => 'View employee directory.'],
    ['name' => 'employee.create', 'module' => 'employee', 'description' => 'Add an employee.'],
    ['name' => 'employee.edit',   'module' => 'employee', 'description' => 'Edit an employee profile.'],
    ['name' => 'employee.delete', 'module' => 'employee', 'description' => 'Soft-delete (resign) an employee.'],
    ['name' => 'employee.import', 'module' => 'employee', 'description' => 'Bulk import employees from Excel.'],

    // department
    ['name' => 'department.view',   'module' => 'department', 'description' => 'View department tree.'],
    ['name' => 'department.manage', 'module' => 'department', 'description' => 'Create, edit, reorganize departments.'],

    // designation
    ['name' => 'designation.view',   'module' => 'designation', 'description' => 'View designations.'],
    ['name' => 'designation.manage', 'module' => 'designation', 'description' => 'Manage designations.'],

    // holiday
    ['name' => 'holiday.view',   'module' => 'holiday', 'description' => 'View holiday calendar.'],
    ['name' => 'holiday.manage', 'module' => 'holiday', 'description' => 'Add or remove holidays for the organization.'],

    // correction
    ['name' => 'correction.view',    'module' => 'correction', 'description' => 'View attendance corrections.'],
    ['name' => 'correction.approve', 'module' => 'correction', 'description' => 'Decide on correction requests.'],

    // report
    ['name' => 'report.view',     'module' => 'report', 'description' => 'View reports in-app.'],
    ['name' => 'report.export',   'module' => 'report', 'description' => 'Download reports.'],
    ['name' => 'report.schedule', 'module' => 'report', 'description' => 'Schedule emailed reports (Phase 2).'],

    // organization
    ['name' => 'organization.view',   'module' => 'organization', 'description' => 'View own organization profile.'],
    ['name' => 'organization.manage', 'module' => 'organization', 'description' => 'Edit organization settings.'],

    // user
    ['name' => 'user.view',        'module' => 'user', 'description' => 'View users in the organization.'],
    ['name' => 'user.invite',      'module' => 'user', 'description' => 'Send invitations.'],
    ['name' => 'user.manage',      'module' => 'user', 'description' => 'Edit, suspend, or remove users from the organization.'],
    ['name' => 'user.impersonate', 'module' => 'user', 'description' => 'Impersonate another user.'],

    // role
    ['name' => 'role.view',   'module' => 'role', 'description' => 'View roles.'],
    ['name' => 'role.manage', 'module' => 'role', 'description' => 'Create, edit, delete organization roles.'],

    // permission
    ['name' => 'permission.view',   'module' => 'permission', 'description' => 'View permission catalog and assignments.'],
    ['name' => 'permission.assign', 'module' => 'permission', 'description' => 'Grant or revoke direct user permissions.'],

    // audit
    ['name' => 'audit.view',   'module' => 'audit', 'description' => 'View audit log.'],
    ['name' => 'audit.export', 'module' => 'audit', 'description' => 'Export audit log.'],

    // system (system roles only)
    ['name' => 'system.manage',          'module' => 'system', 'description' => 'Platform-wide settings.'],
    ['name' => 'system.tenants.manage',  'module' => 'system', 'description' => 'Create, suspend, hard-delete organizations.'],
    ['name' => 'system.impersonate',     'module' => 'system', 'description' => 'Impersonate anyone, anywhere.'],
    ['name' => 'system.audit.read',      'module' => 'system', 'description' => 'Cross-tenant audit log read.'],
];
