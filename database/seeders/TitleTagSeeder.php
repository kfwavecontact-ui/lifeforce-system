<?php

namespace Database\Seeders;

use App\Models\TitleTag;
use Illuminate\Database\Seeder;

class TitleTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            '脳開発',
            '将棋',
            '資格取得',
            'イベント限定',
            '全国大会',
            '校舎限定',
            '期間限定',
            '特別称号',
        ];

        foreach ($tags as $index => $name) {
            TitleTag::updateOrCreate(
                ['name' => $name],
                [
                    'display_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}