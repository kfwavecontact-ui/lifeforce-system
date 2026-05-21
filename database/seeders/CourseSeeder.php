<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('courses')->insert([
            ['name' => '脳開発コース', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '脳開発＋将棋コース', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '脳開発＋資格取得コース', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '脳開発＋将棋＋資格取得コース', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
