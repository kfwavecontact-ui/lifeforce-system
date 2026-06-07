<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventLocationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_locations')->insert([
            [
                'id' => 1,
                'event_schedule_id' => 1,
                'location_type' => 'school',
                'school_id' => 1,
                'name' => '柏教室 Aルーム',
                'address' => '千葉県柏市若柴1-1-1',
                'online_url' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
