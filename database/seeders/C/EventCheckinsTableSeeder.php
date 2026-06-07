<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventCheckinsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_checkins')->insert([
            [
                'id' => 1,
                'event_participant_id' => 1,
                'checked_in_at' => '2026-06-07 09:50:00',
                'checked_in_by' => 3,
                'checkin_method' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
