<?php

namespace Database\Seeders\B;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DiscountsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('discounts')->insert([
            [
                'id' => 1,
                'code' => 'SIBLING',
                'name' => '兄弟割引',
                'discount_type' => 'fixed',
                'discount_value' => 1000,
                'start_date' => '2026-01-01',
                'end_date' => null,
                'is_active' => true,
                'note' => '兄弟在籍時に適用',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'code' => 'CAMPAIGN10',
                'name' => '入会キャンペーン',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'start_date' => '2026-04-01',
                'end_date' => '2026-06-30',
                'is_active' => true,
                'note' => '期間限定',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
