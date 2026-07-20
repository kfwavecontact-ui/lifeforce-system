<?php

namespace Database\Seeders\A;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 通知共通基盤で使用する通知種別の初期値を登録します。
 * 既存レコードは更新し、未登録レコードだけを追加するため、複数回実行しても重複しません。
 */
class NotificationTypesTableSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'lesson_reminder', 'name' => '授業リマインド', 'default_channel' => 'line', 'sort_order' => 1],
            ['code' => 'attendance_notice', 'name' => '入退室通知', 'default_channel' => 'line', 'sort_order' => 2],
            ['code' => 'invoice_notice', 'name' => '請求通知', 'default_channel' => 'email', 'sort_order' => 3],
            ['code' => 'system_notice', 'name' => 'システム通知', 'default_channel' => 'portal', 'sort_order' => 99],
        ];

        foreach ($types as $type) {
            $values = [
                'name' => $type['name'],
                'default_channel' => $type['default_channel'],
                'sort_order' => $type['sort_order'],
                'is_active' => true,
                'updated_at' => now(),
            ];

            $exists = DB::table('notification_types')
                ->where('code', $type['code'])
                ->exists();

            if ($exists) {
                DB::table('notification_types')
                    ->where('code', $type['code'])
                    ->update($values);

                continue;
            }

            DB::table('notification_types')->insert([
                'code' => $type['code'],
                ...$values,
                'created_at' => now(),
            ]);
        }
    }
}
