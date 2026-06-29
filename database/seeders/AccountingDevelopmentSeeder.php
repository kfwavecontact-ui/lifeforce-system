<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AccountingDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->truncateTables();

            $now = now();

            $this->insertRoles($now);
            $this->insertUsers($now);
            $this->insertAreas($now);
            $this->insertSchools($now);
            $this->insertGradeMasters($now);
            $this->insertParentsAndStudents($now);
            $this->insertCourses($now);
            $this->insertCoursePrices($now);
            $this->insertStudentContracts($now);
            $this->insertDiscounts($now);
            $this->insertStudentDiscounts($now);
            $this->insertPaymentMethods($now);
            $this->insertAccountCategories($now);
            $this->insertInvoicesAndTransactions($now);
        });
    }

    private function truncateTables(): void
    {
        foreach ([
            'account_transactions',
            'payments',
            'invoice_items',
            'invoices',
            'student_discounts',
            'student_course_contracts',
            'parent_students',
            'students',
            'parents',
            'school_users',
            'user_roles',
            'users',
            'schools',
            'areas',
            'course_prices',
            'courses',
            'discounts',
            'payment_methods',
            'account_categories',
            'grades',
            'enrollment_statuses',
            'roles',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::statement('TRUNCATE TABLE "' . $table . '" RESTART IDENTITY CASCADE');
            }
        }
    }

    private function insertRows(string $table, array $rows): void
    {
        if (!Schema::hasTable($table) || empty($rows)) {
            return;
        }

        $columns = Schema::getColumnListing($table);

        $filteredRows = collect($rows)
            ->map(fn ($row) => collect($row)->only($columns)->all())
            ->filter(fn ($row) => !empty($row))
            ->values()
            ->all();

        if (!empty($filteredRows)) {
            DB::table($table)->insert($filteredRows);
        }
    }

    private function insertRoles($now): void
    {
        $this->insertRows('roles', [
            ['id' => 1, 'name' => 'admin', 'display_name' => '管理者', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'area_manager', 'display_name' => 'エリアマネージャ', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'school_manager', 'display_name' => '教室長', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'teacher', 'display_name' => '講師', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'student', 'display_name' => '生徒', 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'parent', 'display_name' => '保護者', 'sort_order' => 6, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function insertUsers($now): void
    {
        $rows = [
            ['id' => 1, 'name' => '管理者', 'email' => 'admin@example.com', 'password' => Hash::make('password'), 'email_verified_at' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => '教室長', 'email' => 'manager@example.com', 'password' => Hash::make('password'), 'email_verified_at' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($this->students() as $student) {
            $rows[] = [
                'id' => $student['user_id'],
                'name' => $student['last_name'] . $student['first_name'],
                'email' => 'student' . $student['id'] . '@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $rows[] = [
                'id' => $student['parent_user_id'],
                'name' => $student['last_name'] . ' 保護者',
                'email' => 'parent' . $student['id'] . '@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertRows('users', $rows);

        $userRoles = [
            ['id' => 1, 'user_id' => 1, 'role_id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'user_id' => 2, 'role_id' => 3, 'created_at' => $now, 'updated_at' => $now],
        ];

        $id = 3;

        foreach ($this->students() as $student) {
            $userRoles[] = ['id' => $id++, 'user_id' => $student['user_id'], 'role_id' => 5, 'created_at' => $now, 'updated_at' => $now];
            $userRoles[] = ['id' => $id++, 'user_id' => $student['parent_user_id'], 'role_id' => 6, 'created_at' => $now, 'updated_at' => $now];
        }

        $this->insertRows('user_roles', $userRoles);
    }

    private function insertAreas($now): void
    {
        $this->insertRows('areas', [
            [
                'id' => 1,
                'code' => 'kashiwa',
                'name' => '柏エリア',
                'display_name' => '柏エリア',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    private function insertSchools($now): void
    {
        $this->insertRows('schools', [
            [
                'id' => 1,
                'code' => 'kashiwa',
                'area_id' => 1,
                'name' => '柏の葉教室',
                'short_name' => '柏の葉',
                'kana_name' => 'カシワノハ',
                'postal_code' => '277-0871',
                'prefecture' => '千葉県',
                'city' => '柏市',
                'address1' => '若柴1-1-1',
                'address2' => null,
                'phone_number' => '04-0000-0000',
                'email' => 'kashiwa@example.com',
                'opened_at' => '2026-04-01',
                'closed_at' => null,
                'capacity' => 80,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->insertRows('school_users', [
            ['id' => 1, 'school_id' => 1, 'user_id' => 1, 'role_id' => 1, 'is_primary' => true, 'started_at' => '2026-04-01', 'ended_at' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'school_id' => 1, 'user_id' => 2, 'role_id' => 3, 'is_primary' => true, 'started_at' => '2026-04-01', 'ended_at' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function insertGradeMasters($now): void
    {
        $this->insertRows('grades', [
            ['id' => 1, 'code' => 'kindergarten_middle', 'name' => '年中', 'sort_order' => 1, 'next_grade_id' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'code' => 'kindergarten_senior', 'name' => '年長', 'sort_order' => 2, 'next_grade_id' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'code' => 'elementary_1', 'name' => '小1', 'sort_order' => 3, 'next_grade_id' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'code' => 'elementary_2', 'name' => '小2', 'sort_order' => 4, 'next_grade_id' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'code' => 'elementary_3', 'name' => '小3', 'sort_order' => 5, 'next_grade_id' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->insertRows('enrollment_statuses', [
        [
            'id' => 1,
            'code' => 'prospect',
            'status' => '体験中',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => 2,
            'code' => 'enrolled',
            'status' => '在籍中',
            'sort_order' => 2,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => 3,
            'code' => 'withdrawn',
            'status' => '退会済',
            'sort_order' => 3,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);
    }

    private function insertParentsAndStudents($now): void
    {
        $parents = [];
        $students = [];
        $parentStudents = [];

        foreach ($this->students() as $student) {
            $parents[] = [
                'id' => $student['parent_id'],
                'user_id' => $student['parent_user_id'],
                'parent_code' => 'PAR' . $student['id'],
                'last_name' => $student['last_name'],
                'first_name' => '保護者',
                'phone_number' => '090-' . substr((string) $student['id'], -4) . '-' . substr((string) $student['id'], -4),
                'postal_code' => '277-0871',
                'address' => '千葉県柏市若柴1-1-' . ($student['id'] - 1000),
                'occupation' => '会社員',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $students[] = [
                'id' => $student['id'],
                'user_id' => $student['user_id'],
                'school_id' => 1,
                'grade_id' => $student['grade_id'],
                'enrollment_status_id' => 2,
                'student_code' => 'STU' . $student['id'],
                'last_name' => $student['last_name'],
                'first_name' => $student['first_name'],
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
            ];

            $parentStudents[] = [
                'id' => $student['id'],
                'parent_id' => $student['parent_id'],
                'student_id' => $student['id'],
                'relationship' => 'mother',
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertRows('parents', $parents);
        $this->insertRows('students', $students);
        $this->insertRows('parent_students', $parentStudents);
    }

    private function insertCourses($now): void
    {
        $this->insertRows('courses', [
            ['id' => 1, 'code' => 'brain', 'name' => '脳開発コース', 'sort_order' => 1, 'description' => '脳開発を中心に学ぶ基本コース', 'is_recommended' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'code' => 'brain_shogi', 'name' => '脳開発＋将棋コース', 'sort_order' => 2, 'description' => '脳開発と将棋を組み合わせたコース', 'is_recommended' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'code' => 'brain_certification', 'name' => '脳開発＋資格取得コース', 'sort_order' => 3, 'description' => '脳開発と資格取得を組み合わせたコース', 'is_recommended' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'code' => 'brain_shogi_certification', 'name' => '脳開発＋将棋＋資格取得コース', 'sort_order' => 4, 'description' => '脳開発・将棋・資格取得を組み合わせた推奨コース', 'is_recommended' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function insertCoursePrices($now): void
    {
        $rows = [];
        $id = 1;

        foreach ([
            1 => [['週2', 18000], ['週3', 26000], ['フリー', 35000]],
            2 => [['週2', 27000], ['週3', 30000], ['フリー', 33000]],
            3 => [['週2', 28000], ['週3', 31000], ['フリー', 34000]],
            4 => [['週2', 32000], ['週3', 35000], ['フリー', 38000]],
        ] as $courseId => $prices) {
            foreach ($prices as [$attendanceType, $monthlyFee]) {
                $rows[] = [
                    'id' => $id,
                    'course_id' => $courseId,
                    'attendance_type' => $attendanceType,
                    'monthly_fee' => $monthlyFee,
                    'sort_order' => $id,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $id++;
            }
        }

        $this->insertRows('course_prices', $rows);
    }

    private function insertStudentContracts($now): void
    {
        $rows = [];

        foreach ($this->students() as $student) {
            $rows[] = [
                'id' => $student['id'],
                'student_id' => $student['id'],
                'course_id' => $student['course_id'],
                'course_price_id' => $student['course_price_id'],
                'contract_status' => 'active',
                'started_at' => '2026-04-01',
                'ended_at' => null,
                'monthly_fee' => $student['monthly_fee'],
                'note' => '会計開発用サンプル契約',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertRows('student_course_contracts', $rows);
    }

    private function insertDiscounts($now): void
    {
        $this->insertRows('discounts', [
            ['id' => 1, 'code' => 'SIBLING', 'name' => '兄弟割引', 'is_active' => true, 'note' => '兄弟姉妹が在籍している場合の割引', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'code' => 'REFERRAL', 'name' => '紹介割引', 'is_active' => true, 'note' => '紹介による入会時の割引', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'code' => 'CAMPAIGN', 'name' => '入会キャンペーン', 'is_active' => true, 'note' => '期間限定キャンペーン', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'code' => 'FAMILY', 'name' => '家族割引', 'is_active' => true, 'note' => '家族向け割引', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'code' => 'SPECIAL', 'name' => '特別割引', 'is_active' => true, 'note' => '個別対応の特別割引', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'code' => 'STAFF', 'name' => 'スタッフ割引', 'is_active' => true, 'note' => '関係者向け割引', 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function insertStudentDiscounts($now): void
    {
        $rows = [];
        $id = 1;

        foreach ([1004, 1008, 1012] as $studentId) {
            $rows[] = [
                'id' => $id++,
                'student_id' => $studentId,
                'discount_id' => 1,
                'start_date' => '2026-04-01',
                'end_date' => null,
                'is_active' => true,
                'applied_reason' => '兄弟在籍',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertRows('student_discounts', $rows);
    }

    private function insertPaymentMethods($now): void
    {
        $this->insertRows('payment_methods', [
            ['id' => 1, 'code' => 'bank_transfer', 'name' => '銀行振込', 'is_auto_payment' => false, 'sort_order' => 1, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'code' => 'cash', 'name' => '現金', 'is_auto_payment' => false, 'sort_order' => 2, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'code' => 'credit_card', 'name' => 'クレジットカード', 'is_auto_payment' => true, 'sort_order' => 3, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'code' => 'stripe', 'name' => 'Stripe決済', 'is_auto_payment' => true, 'sort_order' => 4, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'code' => 'paypay', 'name' => 'PayPay決済', 'is_auto_payment' => true, 'sort_order' => 5, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'code' => 'other', 'name' => 'その他', 'is_auto_payment' => false, 'sort_order' => 6, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function insertAccountCategories($now): void
    {
        $this->insertRows('account_categories', [
            ['id' => 1, 'code' => 'tuition_sales', 'name' => '授業料売上', 'transaction_type' => '収益', 'sort_order' => 1, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'code' => 'admission_sales', 'name' => '入会金売上', 'transaction_type' => '収益', 'sort_order' => 2, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'code' => 'shop_sales', 'name' => 'ショップ売上', 'transaction_type' => '収益', 'sort_order' => 3, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'code' => 'event_sales', 'name' => 'イベント売上', 'transaction_type' => '収益', 'sort_order' => 4, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'code' => 'spot_sales', 'name' => 'スポット売上', 'transaction_type' => '収益', 'sort_order' => 5, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'code' => 'refund', 'name' => '返金', 'transaction_type' => '費用', 'sort_order' => 6, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'code' => 'point_item_cost', 'name' => 'ポイント商品費用', 'transaction_type' => '費用', 'sort_order' => 7, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'code' => 'expense', 'name' => '経費', 'transaction_type' => '費用', 'sort_order' => 8, 'is_active' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function insertInvoicesAndTransactions($now): void
    {
        $invoices = [];
        $items = [];
        $payments = [];
        $transactions = [];

        $invoiceId = 1;
        $itemId = 1;
        $paymentId = 1;
        $transactionId = 1;

        foreach ([4, 5, 6] as $month) {
            foreach ($this->students() as $student) {
                $hasAdmission = $month === 4 && in_array($student['id'], [1001, 1004, 1007, 1010], true);
                $discountAmount = in_array($student['id'], [1004, 1008, 1012], true) ? 1000 : 0;
                $isUnpaid = $month === 6 && in_array($student['id'], [1004, 1008, 1012], true);
                $admissionFee = $hasAdmission ? 11000 : 0;
                $subtotal = $student['monthly_fee'] + $admissionFee;
                $totalAmount = $subtotal - $discountAmount;
                $paymentMethodId = (($student['id'] % 3) + 1);
                $dueDate = sprintf('2026-%02d-27', $month);
                $paidAt = $isUnpaid ? null : sprintf('2026-%02d-27 10:00:00', $month);
                $paymentDate = $isUnpaid ? null : sprintf('2026-%02d-27', $month);

                $invoices[] = [
                    'id' => $invoiceId,
                    'student_id' => $student['id'],
                    'payment_method_id' => $paymentMethodId,
                    'invoice_no' => sprintf('INV-2026%02d-%04d', $month, $student['id']),
                    'billing_year' => 2026,
                    'billing_month' => $month,
                    'issue_date' => sprintf('2026-%02d-01', $month),
                    'due_date' => $dueDate,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => 0,
                    'total_amount' => $totalAmount,
                    'payment_status' => $isUnpaid ? 'unpaid' : 'paid',
                    'paid_at' => $paidAt,
                    'note' => $discountAmount > 0 ? '兄弟割引適用' : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $tuitionDiscount = $this->allocateDiscount($student['monthly_fee'], $subtotal, $discountAmount);

                $items[] = [
                    'id' => $itemId,
                    'invoice_id' => $invoiceId,
                    'item_type' => 'tuition',
                    'item_name' => '授業料',
                    'quantity' => 1,
                    'unit_price' => $student['monthly_fee'],
                    'amount' => $student['monthly_fee'],
                    'tax_rate' => 0,
                    'note' => sprintf('2026年%d月授業料', $month),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $transactions[] = [
                    'id' => $transactionId++,
                    'scheduled_date' => $dueDate,
                    'transaction_date' => $paymentDate,
                    'account_category_id' => 1,
                    'payment_method_id' => $paymentMethodId,
                    'transaction_name' => sprintf('%s %s 2026年%d月授業料', $student['last_name'] . $student['first_name'], $student['course_name'], $month),
                    'amount' => $student['monthly_fee'] - $tuitionDiscount,
                    'before_discount_amount' => $student['monthly_fee'],
                    'discount_amount' => $tuitionDiscount,
                    'discount_type_id' => $tuitionDiscount > 0 ? 1 : null,
                    'discount_note' => $tuitionDiscount > 0 ? '兄弟割引適用' : null,
                    'status' => $isUnpaid ? 'planned' : 'confirmed',
                    'cancelled_reason' => null,
                    'memo' => null,
                    'school_id' => 1,
                    'student_id' => $student['id'],
                    'source_table' => 'invoice_item',
                    'source_id' => $itemId,
                    'related_transaction_id' => null,
                    'created_by' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $itemId++;

                if ($hasAdmission) {
                    $admissionDiscount = $this->allocateDiscount($admissionFee, $subtotal, $discountAmount);

                    $items[] = [
                        'id' => $itemId,
                        'invoice_id' => $invoiceId,
                        'item_type' => 'admission',
                        'item_name' => '入会金',
                        'quantity' => 1,
                        'unit_price' => $admissionFee,
                        'amount' => $admissionFee,
                        'tax_rate' => 0,
                        'note' => '入会金',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $transactions[] = [
                        'id' => $transactionId++,
                        'scheduled_date' => $dueDate,
                        'transaction_date' => $paymentDate,
                        'account_category_id' => 2,
                        'payment_method_id' => $paymentMethodId,
                        'transaction_name' => $student['last_name'] . $student['first_name'] . ' 入会金',
                        'amount' => $admissionFee - $admissionDiscount,
                        'before_discount_amount' => $admissionFee,
                        'discount_amount' => $admissionDiscount,
                        'discount_type_id' => $admissionDiscount > 0 ? 1 : null,
                        'discount_note' => $admissionDiscount > 0 ? '兄弟割引適用' : null,
                        'status' => $isUnpaid ? 'planned' : 'confirmed',
                        'cancelled_reason' => null,
                        'memo' => null,
                        'school_id' => 1,
                        'student_id' => $student['id'],
                        'source_table' => 'invoice_item',
                        'source_id' => $itemId,
                        'related_transaction_id' => null,
                        'created_by' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $itemId++;
                }

                if (!$isUnpaid) {
                    $payments[] = [
                        'id' => $paymentId++,
                        'invoice_id' => $invoiceId,
                        'student_id' => $student['id'],
                        'payment_method_id' => $paymentMethodId,
                        'payment_date' => $paymentDate,
                        'payment_amount' => $totalAmount,
                        'payment_status' => 'confirmed',
                        'transaction_no' => sprintf('PAY-%06d', $invoiceId),
                        'confirmed_by' => 1,
                        'confirmed_at' => $now,
                        'note' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $invoiceId++;
            }
        }

        $this->insertRows('invoices', $invoices);
        $this->insertRows('invoice_items', $items);
        $this->insertRows('payments', $payments);
        $this->insertRows('account_transactions', $transactions);
    }

    private function allocateDiscount(int $itemAmount, int $subtotal, int $discountAmount): int
    {
        if ($subtotal <= 0 || $discountAmount <= 0) {
            return 0;
        }

        return (int) round(($itemAmount / $subtotal) * $discountAmount);
    }

    private function students(): array
    {
        return [
            ['id' => 1001, 'user_id' => 1101, 'parent_user_id' => 1201, 'parent_id' => 1001, 'last_name' => '佐藤', 'first_name' => '花', 'grade_id' => 3, 'course_id' => 1, 'course_price_id' => 1, 'course_name' => '脳開発コース', 'attendance_type' => '週2', 'monthly_fee' => 18000],
            ['id' => 1002, 'user_id' => 1102, 'parent_user_id' => 1202, 'parent_id' => 1002, 'last_name' => '鈴木', 'first_name' => '陽太', 'grade_id' => 4, 'course_id' => 1, 'course_price_id' => 2, 'course_name' => '脳開発コース', 'attendance_type' => '週3', 'monthly_fee' => 26000],
            ['id' => 1003, 'user_id' => 1103, 'parent_user_id' => 1203, 'parent_id' => 1003, 'last_name' => '高橋', 'first_name' => '結衣', 'grade_id' => 5, 'course_id' => 1, 'course_price_id' => 3, 'course_name' => '脳開発コース', 'attendance_type' => 'フリー', 'monthly_fee' => 35000],
            ['id' => 1004, 'user_id' => 1104, 'parent_user_id' => 1204, 'parent_id' => 1004, 'last_name' => '田中', 'first_name' => '湊', 'grade_id' => 3, 'course_id' => 2, 'course_price_id' => 4, 'course_name' => '脳開発＋将棋コース', 'attendance_type' => '週2', 'monthly_fee' => 27000],
            ['id' => 1005, 'user_id' => 1105, 'parent_user_id' => 1205, 'parent_id' => 1005, 'last_name' => '伊藤', 'first_name' => '凛', 'grade_id' => 4, 'course_id' => 2, 'course_price_id' => 5, 'course_name' => '脳開発＋将棋コース', 'attendance_type' => '週3', 'monthly_fee' => 30000],
            ['id' => 1006, 'user_id' => 1106, 'parent_user_id' => 1206, 'parent_id' => 1006, 'last_name' => '渡辺', 'first_name' => '悠真', 'grade_id' => 5, 'course_id' => 2, 'course_price_id' => 6, 'course_name' => '脳開発＋将棋コース', 'attendance_type' => 'フリー', 'monthly_fee' => 33000],
            ['id' => 1007, 'user_id' => 1107, 'parent_user_id' => 1207, 'parent_id' => 1007, 'last_name' => '中村', 'first_name' => '葵', 'grade_id' => 3, 'course_id' => 3, 'course_price_id' => 7, 'course_name' => '脳開発＋資格取得コース', 'attendance_type' => '週2', 'monthly_fee' => 28000],
            ['id' => 1008, 'user_id' => 1108, 'parent_user_id' => 1208, 'parent_id' => 1008, 'last_name' => '小林', 'first_name' => '蓮', 'grade_id' => 4, 'course_id' => 3, 'course_price_id' => 8, 'course_name' => '脳開発＋資格取得コース', 'attendance_type' => '週3', 'monthly_fee' => 31000],
            ['id' => 1009, 'user_id' => 1109, 'parent_user_id' => 1209, 'parent_id' => 1009, 'last_name' => '加藤', 'first_name' => '美月', 'grade_id' => 5, 'course_id' => 3, 'course_price_id' => 9, 'course_name' => '脳開発＋資格取得コース', 'attendance_type' => 'フリー', 'monthly_fee' => 34000],
            ['id' => 1010, 'user_id' => 1110, 'parent_user_id' => 1210, 'parent_id' => 1010, 'last_name' => '吉田', 'first_name' => '大和', 'grade_id' => 3, 'course_id' => 4, 'course_price_id' => 10, 'course_name' => '脳開発＋将棋＋資格取得コース', 'attendance_type' => '週2', 'monthly_fee' => 32000],
            ['id' => 1011, 'user_id' => 1111, 'parent_user_id' => 1211, 'parent_id' => 1011, 'last_name' => '山本', 'first_name' => '紬', 'grade_id' => 4, 'course_id' => 4, 'course_price_id' => 11, 'course_name' => '脳開発＋将棋＋資格取得コース', 'attendance_type' => '週3', 'monthly_fee' => 35000],
            ['id' => 1012, 'user_id' => 1112, 'parent_user_id' => 1212, 'parent_id' => 1012, 'last_name' => '松本', 'first_name' => '蒼', 'grade_id' => 5, 'course_id' => 4, 'course_price_id' => 12, 'course_name' => '脳開発＋将棋＋資格取得コース', 'attendance_type' => 'フリー', 'monthly_fee' => 38000],
        ];
    }
}