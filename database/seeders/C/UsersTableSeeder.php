<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'id' => 1,
                'name' => '管理者',
                'password' => 'password',
                'email' => 'admin@example.com',
                'email_verified_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'エリアマネージャ',
                'password' => 'password',
                'email' => 'area@example.com',
                'email_verified_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => '教室長',
                'password' => 'password',
                'email' => 'manager@example.com',
                'email_verified_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => '田中 花子',
                'password' => 'password',
                'email' => 'teacher@example.com',
                'email_verified_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => '山田太郎',
                'password' => 'password',
                'email' => 'student001@example.com',
                'email_verified_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'name' => '山田花子',
                'password' => 'password',
                'email' => 'parent001@example.com',
                'email_verified_at' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
