<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('Password@123');

        $organizations = [
            [
                'id'                => 1,
                'name'              => 'Kathmandu Organization',
                'slug'              => 'ktm',
                'contact_email'     => 'contact@ktm.test',
                'contact_phone'     => '9801000000',
                'address'           => 'Kathmandu, Nepal',
                'timezone'          => 'Asia/Kathmandu',
                'fiscal_year_start' => '2025-07-16',
                'settings'          => json_encode([]),
                'status'            => 'active',
            ],
            [
                'id'                => 2,
                'name'              => 'Lalitpur Organization',
                'slug'              => 'lalitpur',
                'contact_email'     => 'contact@lalitpur.test',
                'contact_phone'     => '9802000000',
                'address'           => 'Lalitpur, Nepal',
                'timezone'          => 'Asia/Kathmandu',
                'fiscal_year_start' => '2025-07-16',
                'settings'          => json_encode([]),
                'status'            => 'active',
            ],
            [
                'id'                => 3,
                'name'              => 'Demo Organization',
                'slug'              => 'demo',
                'contact_email'     => 'contact@demo.test',
                'contact_phone'     => '9803000000',
                'address'           => 'Bhaktapur, Nepal',
                'timezone'          => 'Asia/Kathmandu',
                'fiscal_year_start' => '2025-07-16',
                'settings'          => json_encode([]),
                'status'            => 'active',
            ],
        ];

        foreach ($organizations as $orgData) {
            Organization::updateOrCreate(
                ['id' => $orgData['id']],
                $orgData
            );
        }

        $users = [
            [
                'organization_id' => 1,
                'name'            => 'Super Admin',
                'email'           => 'superadmin@digitalhajiri.test',
                'phone'           => '9801000000',
                'role'            => 'Super Admin',
            ],

            // Kathmandu
            [
                'organization_id' => 1,
                'name'            => 'KTM Organization Admin',
                'email'           => 'admin@ktm.test',
                'phone'           => '9801000001',
                'role'            => 'Organization Admin',
            ],
            [
                'organization_id' => 1,
                'name'            => 'KTM Attendance Manager',
                'email'           => 'attendance@ktm.test',
                'phone'           => '9801000002',
                'role'            => 'Attendance Manager',
            ],
            [
                'organization_id' => 1,
                'name'            => 'KTM HR Officer',
                'email'           => 'hr@ktm.test',
                'phone'           => '9801000003',
                'role'            => 'HR Officer',
            ],
            [
                'organization_id' => 1,
                'name'            => 'KTM Employee',
                'email'           => 'employee@ktm.test',
                'phone'           => '9801000004',
                'role'            => 'Employee',
            ],

            // Lalitpur
            [
                'organization_id' => 2,
                'name'            => 'Lalitpur Organization Admin',
                'email'           => 'admin@lalitpur.test',
                'phone'           => '9802000001',
                'role'            => 'Organization Admin',
            ],
            [
                'organization_id' => 2,
                'name'            => 'Lalitpur Attendance Manager',
                'email'           => 'attendance@lalitpur.test',
                'phone'           => '9802000002',
                'role'            => 'Attendance Manager',
            ],
            [
                'organization_id' => 2,
                'name'            => 'Lalitpur Employee',
                'email'           => 'employee@lalitpur.test',
                'phone'           => '9802000003',
                'role'            => 'Employee',
            ],

            // Demo Organization
            [
                'organization_id' => 3,
                'name'            => 'Demo HR Officer',
                'email'           => 'hr@demo.test',
                'phone'           => '9803000001',
                'role'            => 'HR Officer',
            ],
            [
                'organization_id' => 3,
                'name'            => 'Demo Employee',
                'email'           => 'employee@demo.test',
                'phone'           => '9803000002',
                'role'            => 'Employee',
            ],
        ];

        foreach ($users as $data) {
            $roleName = $data['role'];
            $organizationId = $data['organization_id'];
            unset($data['role']);

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'organization_id'       => $data['organization_id'],
                    'name'                  => $data['name'],
                    'phone'                 => $data['phone'],
                    'email'                 => $data['email'],
                    'email_verified_at'     => now(),
                    'password'              => $password,
                    'status'                => 'active',
                    'last_login_at'         => null,
                    'last_login_ip'         => null,
                    'failed_login_attempts' => 0,
                    'locked_until'          => null,
                    'password_changed_at'   => now(),
                    'force_password_change'  => false,
                ]
            );

            $role = Role::query()
                ->where('name', $roleName)
                ->where(function ($query) use ($organizationId) {
                    $query->whereNull('organization_id')
                        ->orWhere('organization_id', $organizationId);
                })
                ->first();

            if ($role) {
                // Clear existing roles first
                DB::table('model_has_roles')
                    ->where('model_id', $user->id)
                    ->where('model_type', User::class)
                    ->delete();

                // Insert with organization_id explicitly
                DB::table('model_has_roles')->insert([
                    'role_id'         => $role->id,
                    'model_type'      => User::class,
                    'model_id'        => $user->id,
                    'organization_id' => $organizationId,
                ]);
            }
        }
    }
}
