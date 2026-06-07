<?php

namespace Database\Seeders\B;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeRequirementsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('badge_requirements')->insert([
            [
                'id' => 1,
                'badge_id' => 1,
                'requirement_type' => 'teacher_approval',
                'requirement_value' => 'required',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'badge_id' => 2,
                'requirement_type' => 'minimum_points',
                'requirement_value' => '100',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
