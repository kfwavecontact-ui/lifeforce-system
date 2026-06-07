<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventStaffAssignmentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_staff_assignments')->insert([
            [
                'id' => 1,
                'event_schedule_id' => 1,
                'user_id' => 3,
                'staff_role' => 'manager',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'event_schedule_id' => 1,
                'user_id' => 4,
                'staff_role' => 'teacher',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
