<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactMessagesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('contact_messages')->insert([
            [
                'id' => 1,
                'contact_id' => 1,
                'sender_user_id' => 6,
                'message_body' => '体調不良のため欠席します。',
                'is_internal' => false,
                'sent_at' => '2026-06-01 20:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
