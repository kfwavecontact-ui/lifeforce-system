<?php

namespace Database\Seeders\D;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventNotificationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('event_notifications')->insert([
            [
                'id' => 1,
                'event_id' => 1,
                'event_schedule_id' => 1,
                'event_application_id' => 1,
                'notification_type' => 'application',
                'send_to' => 'parent001@example.com',
                'subject' => 'イベント申込完了',
                'body' => '申込が完了しました。',
                'sent_at' => null,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
