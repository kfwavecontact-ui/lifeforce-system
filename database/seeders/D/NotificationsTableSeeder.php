<?php

namespace Database\Seeders\D;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('notifications')->insert([
            [
                'id' => 1,
                'notification_type_id' => 1,
                'created_by_user_id' => 3,
                'title' => '明日の授業のお知らせ',
                'body' => '明日18:00から授業があります。',
                'related_table' => 'lesson_sessions',
                'related_id' => 1,
                'scheduled_at' => '2026-06-01 18:00:00',
                'sent_at' => null,
                'notification_status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
