<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Challenge;
use App\Models\ChallengeCategory;
use App\Models\ChallengeReward;

class ChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $brainCategory = ChallengeCategory::where('code', 'brain')->first();

        if (!$brainCategory) {
            return;
        }

        $challenges = [
            [
                'code' => 'number_memory_star1',
                'name' => '数字瞬間記憶',
                'difficulty' => 1,
                'description' => '数字を短時間で記憶するチャレンジです。',
                'challenge_type' => 'score',
                'requirement_description' => '10問中8問以上正解',
                'max_score' => 10,
                'passing_score' => 8,
                'point_amount' => 100,
                'sort_order' => 1,
            ],
            [
                'code' => 'number_memory_star2',
                'name' => '数字瞬間記憶',
                'difficulty' => 2,
                'description' => '数字を短時間で記憶するチャレンジです。',
                'challenge_type' => 'score',
                'requirement_description' => '10問中8問以上正解',
                'max_score' => 10,
                'passing_score' => 8,
                'point_amount' => 150,
                'sort_order' => 2,
            ],
        ];

        foreach ($challenges as $item) {
            $challenge = Challenge::updateOrCreate(
                ['code' => $item['code']],
                [
                    'challenge_category_id' => $brainCategory->id,
                    'name' => $item['name'],
                    'difficulty' => $item['difficulty'],
                    'description' => $item['description'],
                    'icon_path' => null,
                    'challenge_type' => $item['challenge_type'],
                    'requirement_description' => $item['requirement_description'],
                    'max_score' => $item['max_score'],
                    'passing_score' => $item['passing_score'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ]
            );

            ChallengeReward::updateOrCreate(
                ['challenge_id' => $challenge->id],
                [
                    'badge_id' => null,
                    'title_id' => null,
                    'point_amount' => $item['point_amount'],
                    'sort_order' => 1,
                ]
            );
        }
    }
}