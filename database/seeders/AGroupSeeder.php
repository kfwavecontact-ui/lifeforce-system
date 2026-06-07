<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            \Database\Seeders\A\RolesTableSeeder::class,
            \Database\Seeders\A\PermissionsTableSeeder::class,
            \Database\Seeders\A\GradesTableSeeder::class,
            \Database\Seeders\A\EnrollmentStatusesTableSeeder::class,
            \Database\Seeders\A\EmploymentTypesTableSeeder::class,
            \Database\Seeders\A\TeacherStatusesTableSeeder::class,
            \Database\Seeders\A\LessonTypesTableSeeder::class,
            \Database\Seeders\A\NoteTypesTableSeeder::class,
            \Database\Seeders\A\LearningPlanTypesTableSeeder::class,
            \Database\Seeders\A\LearningMaterialCategoriesTableSeeder::class,
            \Database\Seeders\A\RoutineTypesTableSeeder::class,
            \Database\Seeders\A\PaymentMethodsTableSeeder::class,
            \Database\Seeders\A\NotificationTypesTableSeeder::class,
            \Database\Seeders\A\ContactTypesTableSeeder::class,
            \Database\Seeders\A\ContactStatusesTableSeeder::class,
            \Database\Seeders\A\SystemSettingsTableSeeder::class,
            \Database\Seeders\A\SequenceNumbersTableSeeder::class,
            \Database\Seeders\A\RoutineCompletionTypesTableSeeder::class,
            \Database\Seeders\A\BadgeCategoriesTableSeeder::class,
            \Database\Seeders\A\EventCategoriesTableSeeder::class,
            \Database\Seeders\A\EventStatusesTableSeeder::class,
            \Database\Seeders\A\ShopCategoriesTableSeeder::class,
        ]);
    }
}
