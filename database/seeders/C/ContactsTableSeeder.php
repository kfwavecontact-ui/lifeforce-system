<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('contacts')->insert([
            [
                'id' => 1,
                'contact_type_id' => 1,
                'contact_status_id' => 1,
                'student_id' => 1,
                'created_by_user_id' => 6,
                'assigned_user_id' => 3,
                'title' => '欠席連絡',
                'priority' => 'normal',
                'related_table' => 'lesson_sessions',
                'related_id' => 1,
                'opened_at' => '2026-06-01 20:00:00',
                'closed_at' => '2026-06-02 10:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
