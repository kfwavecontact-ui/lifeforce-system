<?php

namespace Database\Seeders\D;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationRecipientsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('notification_recipients')->insert([
            [
                'id' => 1,
                'notification_id' => 1,
                'user_id' => 6,
                'channel' => 'email',
                'recipient_address' => 'parent001@example.com',
                'delivery_status' => 'pending',
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
