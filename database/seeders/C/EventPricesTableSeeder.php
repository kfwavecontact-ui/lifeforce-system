<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventPricesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_prices')->insert([
            [
                'id' => 1,
                'event_id' => 1,
                'participant_type' => 'student',
                'price' => 500,
                'currency' => 'JPY',
                'is_free' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
