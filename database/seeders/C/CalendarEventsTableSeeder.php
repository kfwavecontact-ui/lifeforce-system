<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CalendarEventsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('calendar_events')->insert([
            [
                'id' => 1,
                'school_id' => 1,
                'classroom_id' => 1,
                'lesson_type_id' => 1,
                'created_by_user_id' => 3,
                'title' => '脳開発 火曜18時クラス',
                'description' => '毎週定期授業',
                'start_at' => '2026-06-02 18:00:00',
                'end_at' => '2026-06-02 19:00:00',
                'is_all_day' => false,
                'color' => '#4CAF50',
                'is_private' => false,
                'is_cancelled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
