<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopPickupsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shop_pickups')->insert([
            [
                'id' => 1,
                'shop_order_id' => 1,
                'user_id' => 5,
                'pickup_school_id' => 1,
                'pickup_status' => 'ready',
                'pickup_scheduled_date' => '2026-06-02',
                'picked_up_at' => '2026-06-02 18:00:00',
                'handled_by' => 3,
                'note' => '教室で受け渡し予定',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
