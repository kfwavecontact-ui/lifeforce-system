<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChallengeCategory;

class ChallengeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'code' => 'brain',
                'name' => '脳開発',
                'sort_order' => 1,
            ],
            [
                'code' => 'shogi',
                'name' => '将棋',
                'sort_order' => 2,
            ],
            [
                'code' => 'qualification',
                'name' => '資格取得',
                'sort_order' => 3,
            ],
            [
                'code' => 'special',
                'name' => '特別認定',
                'sort_order' => 4,
            ],
        ];

        foreach ($categories as $category) {

            ChallengeCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]
            );

        }
    }
}