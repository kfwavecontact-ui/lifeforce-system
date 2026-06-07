<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventResultRecordsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_result_records')->insert([
            [
                'id' => 1,
                'event_id' => 1,
                'event_schedule_id' => 1,
                'event_application_id' => 1,
                'student_id' => 1,
                'result_type' => 'tournament',
                'rank' => 1,
                'score' => 100,
                'win_count' => 5,
                'loss_count' => 0,
                'is_cleared' => true,
                'recorded_by' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
