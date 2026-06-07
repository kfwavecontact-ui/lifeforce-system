<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactAttachmentsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('contact_attachments')->insert([
            [
                'id' => 1,
                'contact_message_id' => 1,
                'file_name' => 'absence_note.jpg',
                'file_url' => '/contact/absence_note.jpg',
                'file_type' => 'image/jpeg',
                'file_size' => 245000,
                'uploaded_by_user_id' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
