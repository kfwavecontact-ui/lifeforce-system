<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventWaitlistsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_waitlists')->insert([
            [
                'id' => 1,
                'event_schedule_id' => 1,
                'event_application_id' => 1,
                'student_id' => 1,
                'waitlist_order' => 1,
                'waitlist_status' => 'waiting',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
