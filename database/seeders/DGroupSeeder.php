<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            \Database\Seeders\D\NotificationsTableSeeder::class,
            \Database\Seeders\D\AuditLogsTableSeeder::class,
            \Database\Seeders\D\LoginLogsTableSeeder::class,
            \Database\Seeders\D\OperationLogsTableSeeder::class,
            \Database\Seeders\D\FileUploadLogsTableSeeder::class,
            \Database\Seeders\D\NotificationRecipientsTableSeeder::class,
            \Database\Seeders\D\LineMessageLogsTableSeeder::class,
            \Database\Seeders\D\EmailLogsTableSeeder::class,
            \Database\Seeders\D\ShopPaymentWebhooksTableSeeder::class,
            \Database\Seeders\D\EventNotificationsTableSeeder::class,
        ]);
    }
}
