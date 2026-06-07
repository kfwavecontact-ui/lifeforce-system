<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventSchedulesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_schedules')->insert([
            [
                'id' => 1,
                'event_id' => 1,
                'start_at' => '2026-06-07 10:00:00',
                'end_at' => '2026-06-07 12:00:00',
                'capacity' => 24,
                'min_participants' => 4,
                'waitlist_enabled' => true,
                'waitlist_capacity' => 5,
                'application_start_at' => '2026-05-20 00:00:00',
                'application_deadline_at' => '2026-06-05 23:59:59',
                'cancel_deadline_at' => '2026-06-06 23:59:59',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
