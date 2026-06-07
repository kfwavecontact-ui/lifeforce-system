<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LessonNotesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('lesson_notes')->insert([
            [
                'id' => 1,
                'lesson_session_id' => 1,
                'note_type_id' => 1,
                'user_id' => 4,
                'title' => '脳開発授業記録',
                'body' => '図形トレーニングを実施。',
                'is_private' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
