<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            \Database\Seeders\B\CoursesTableSeeder::class,
            \Database\Seeders\B\QualificationsTableSeeder::class,
            \Database\Seeders\B\DiscountsTableSeeder::class,
            \Database\Seeders\B\RewardCategoriesTableSeeder::class,
            \Database\Seeders\B\TitlesTableSeeder::class,
            \Database\Seeders\B\RolePermissionsTableSeeder::class,
            \Database\Seeders\B\CoursePricesTableSeeder::class,
            \Database\Seeders\B\LearningContentsTableSeeder::class,
            \Database\Seeders\B\RewardItemsTableSeeder::class,
            \Database\Seeders\B\BadgesTableSeeder::class,
            \Database\Seeders\B\BadgeRequirementsTableSeeder::class,
            \Database\Seeders\B\BadgeRewardsTableSeeder::class,
        ]);
    }
}
