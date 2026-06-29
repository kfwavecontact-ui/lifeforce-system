<?php

namespace Database\Seeders\C;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleSchoolDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $students = [
            ['id' => 1001, 'last' => '佐藤', 'first' => '花',   'course_id' => 1, 'course_price_id' => 1],
            ['id' => 1002, 'last' => '鈴木', 'first' => '陽太', 'course_id' => 1, 'course_price_id' => 2],
            ['id' => 1003, 'last' => '高橋', 'first' => '結衣', 'course_id' => 1, 'course_price_id' => 3],
            ['id' => 1004, 'last' => '田中', 'first' => '湊',   'course_id' => 2, 'course_price_id' => 4],
            ['id' => 1005, 'last' => '伊藤', 'first' => '凛',   'course_id' => 2, 'course_price_id' => 5],
            ['id' => 1006, 'last' => '渡辺', 'first' => '悠真', 'course_id' => 2, 'course_price_id' => 6],
            ['id' => 1007, 'last' => '中村', 'first' => '葵',   'course_id' => 3, 'course_price_id' => 7],
            ['id' => 1008, 'last' => '小林', 'first' => '蓮',   'course_id' => 3, 'course_price_id' => 8],
            ['id' => 1009, 'last' => '加藤', 'first' => '美月', 'course_id' => 3, 'course_price_id' => 9],
            ['id' => 1010, 'last' => '吉田', 'first' => '大和', 'course_id' => 4, 'course_price_id' => 10],
            ['id' => 1011, 'last' => '山本', 'first' => '紬',   'course_id' => 4, 'course_price_id' => 11],
            ['id' => 1012, 'last' => '松本', 'first' => '蒼',   'course_id' => 4, 'course_price_id' => 12],
        ];

        DB::transaction(function () use ($students, $now) {
            foreach ($students as $index => $student) {
                $studentUserId = 1100 + ($student['id'] - 1000);
                $parentUserId = 1200 + ($student['id'] - 1000);
                $parentId = $student['id'];

                DB::table('users')->insert([
                    [
                        'id' => $studentUserId,
                        'name' => $student['last'] . $student['first'],
                        'password' => 'password',
                        'email' => 'sample-student' . $student['id'] . '@example.com',
                        'email_verified_at' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'id' => $parentUserId,
                        'name' => $student['last'] . ' 保護者',
                        'password' => 'password',
                        'email' => 'sample-parent' . $student['id'] . '@example.com',
                        'email_verified_at' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);

                DB::table('parents')->insert([
                    'id' => $parentId,
                    'user_id' => $parentUserId,
                    'parent_code' => 'PAR' . $student['id'],
                    'last_name' => $student['last'],
                    'first_name' => '保護者',
                    'phone_number' => '090-' . substr((string) $student['id'], -4) . '-' . substr((string) $student['id'], -4),
                    'postal_code' => '277-0871',
                    'address' => '千葉県柏市若柴1-1-' . ($index + 2),
                    'occupation' => '会社員',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('students')->insert([
                    'id' => $student['id'],
                    'user_id' => $studentUserId,
                    'school_id' => 1,
                    'grade_id' => 5,
                    'enrollment_status_id' => 2,
                    'student_code' => 'STU' . $student['id'],
                    'last_name' => $student['last'],
                    'first_name' => $student['first'],
                    'last_name_kana' => null,
                    'first_name_kana' => null,
                    'gender' => null,
                    'birthday' => null,
                    'school_name' => null,
                    'commute_days' => null,
                    'remarks' => null,
                    'image_path' => null,
                    'medical_notes' => null,
                    'admission_source' => null,
                    'enrolled_at' => '2026-04-01',
                    'withdrawn_at' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('parent_students')->insert([
                    'id' => $student['id'],
                    'parent_id' => $parentId,
                    'student_id' => $student['id'],
                    'relationship' => 'mother',
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $coursePrice = DB::table('course_prices')
                    ->where('id', $student['course_price_id'])
                    ->first();

                DB::table('student_course_contracts')->insert([
                    'id' => $student['id'],
                    'student_id' => $student['id'],
                    'course_id' => $student['course_id'],
                    'course_price_id' => $student['course_price_id'],
                    'contract_status' => 'active',
                    'started_at' => '2026-04-01',
                    'ended_at' => null,
                    'monthly_fee' => $coursePrice?->monthly_fee ?? 0,
                    'note' => '開発用サンプル契約',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}