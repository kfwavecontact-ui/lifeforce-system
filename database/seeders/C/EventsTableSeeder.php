<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('events')->insert([
            [
                'id' => 1,
                'event_category_id' => 1,
                'event_status_id' => 3,
                'title' => '春の将棋大会',
                'description' => '将棋の実力を競う大会イベント',
                'organizer_type' => 'headquarters',
                'organizer_user_id' => 1,
                'is_paid' => true,
                'is_external_allowed' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
