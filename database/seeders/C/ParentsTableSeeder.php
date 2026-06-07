<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('parents')->insert([
            [
                'id' => 1,
                'user_id' => 6,
                'parent_code' => 'PAR0001',
                'last_name' => '山田',
                'first_name' => '花子',
                'phone_number' => '090-1111-1111',
                'postal_code' => '277-0871',
                'address' => '千葉県柏市若柴1-1-1',
                'occupation' => '会社員',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
