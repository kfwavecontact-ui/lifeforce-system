<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('role_permissions')->delete();

        $roles = DB::table('roles')->pluck('id', 'display_name');
        $permissions = DB::table('permissions')->pluck('id', 'name');

        $adminRoleId = $roles['管理者'] ?? null;

        if (!$adminRoleId) {
            return;
        }

        foreach ($permissions as $permissionId) {
            DB::table('role_permissions')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}