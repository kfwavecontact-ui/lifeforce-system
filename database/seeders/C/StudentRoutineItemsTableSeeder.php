<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentRoutineItemsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('student_routine_items')->insert([
            [
                'id' => 1,
                'student_routine_id' => 1,
                'learning_content_id' => 1,
                'completion_type_id' => 1,
                'required_minutes' => 10,
                'required_count' => 0,
                'required_accuracy' => 0,
                'required_streak' => 0,
                'point_amount' => 30,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'student_routine_id' => 2,
                'learning_content_id' => 3,
                'completion_type_id' => 1,
                'required_minutes' => 15,
                'required_count' => 0,
                'required_accuracy' => 0,
                'required_streak' => 0,
                'point_amount' => 20,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 3,
                'student_routine_id' => 3,
                'learning_content_id' => 1,
                'completion_type_id' => 1,
                'required_minutes' => 10,
                'required_count' => 0,
                'required_accuracy' => 0,
                'required_streak' => 0,
                'point_amount' => 30,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'id' => 4,
                'student_routine_id' => 4,
                'learning_content_id' => 2,
                'completion_type_id' => 2,
                'required_minutes' => 0,
                'required_count' => 5,
                'required_accuracy' => 0,
                'required_streak' => 0,
                'point_amount' => 40,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
