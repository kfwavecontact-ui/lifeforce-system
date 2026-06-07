<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('schools')->insert([
            [
                'id' => 1,
                'code' => 'SCH001',
                'area_id' => 1,
                'name' => '柏の葉キャンパス校',
                'short_name' => '柏の葉校',
                'kana_name' => 'カシワノハコウ',
                'postal_code' => '277-0871',
                'prefecture' => '千葉県',
                'city' => '柏市',
                'address1' => '若柴1-1-1',
                'address2' => '○○ビル3F',
                'phone_number' => '04-1111-1111',
                'email' => 'kashiwanoha@example.com',
                'opened_at' => '2024-04-01',
                'closed_at' => '2026-06-02 10:00:00',
                'capacity' => 120,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'SCH002',
                'area_id' => 2,
                'name' => '流山おおたかの森校',
                'short_name' => 'おおたか校',
                'kana_name' => 'オオタカコウ',
                'postal_code' => '270-0128',
                'prefecture' => '千葉県',
                'city' => '流山市',
                'address1' => 'おおたかの森西1-2-3',
                'address2' => '△△ビル2F',
                'phone_number' => '04-2222-2222',
                'email' => 'otaka@example.com',
                'opened_at' => '2024-04-01',
                'closed_at' => '2026-06-02 10:00:00',
                'capacity' => 100,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
