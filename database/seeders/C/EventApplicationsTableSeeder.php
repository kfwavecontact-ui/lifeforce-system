<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventApplicationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_applications')->insert([
            [
                'id' => 1,
                'event_id' => 1,
                'event_schedule_id' => 1,
                'student_id' => 1,
                'applicant_user_id' => 6,
                'participant_type' => 'student',
                'application_status' => 'applied',
                'applied_at' => '2026-05-25 10:00:00',
                'cancelled_at' => '2026-06-01 10:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
