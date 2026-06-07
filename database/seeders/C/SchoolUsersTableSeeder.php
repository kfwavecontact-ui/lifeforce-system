<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolUsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('school_users')->insert([
            [
                'id' => 1,
                'school_id' => 1,
                'user_id' => 1,
                'role_id' => 1,
                'is_primary' => true,
                'started_at' => '2026-04-01',
                'ended_at' => '2026-12-31',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'school_id' => 1,
                'user_id' => 3,
                'role_id' => 3,
                'is_primary' => true,
                'started_at' => '2026-04-01',
                'ended_at' => '2026-12-31',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'school_id' => 1,
                'user_id' => 4,
                'role_id' => 4,
                'is_primary' => true,
                'started_at' => '2026-04-01',
                'ended_at' => '2026-12-31',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
