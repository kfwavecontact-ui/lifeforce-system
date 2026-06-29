<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['sort_order' => 1, 'group_name' => 'Core', 'module' => 'ダッシュボード', 'module_key' => 'dashboard', 'display_name' => '閲覧', 'action' => 'view'],
            ['sort_order' => 2, 'group_name' => 'Core', 'module' => 'ダッシュボード', 'module_key' => 'dashboard', 'display_name' => '今日の教室閲覧', 'action' => 'today_view'],
            ['sort_order' => 3, 'group_name' => 'Core', 'module' => 'ダッシュボード', 'module_key' => 'dashboard', 'display_name' => '教室分析閲覧', 'action' => 'classroom_analysis_view'],

            // 続きが長いので、次で全文出します。
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['module_key'] . '.' . $permission['action']],
                [
                    'display_name' => $permission['display_name'],
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                    'description' => $permission['group_name'] . ' ＞ ' . $permission['module'] . ' ＞ ' . $permission['display_name'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}