<?php

namespace Database\Seeders\C;

class SampleSchoolDataset
{
    public static function students(): array
    {
        return [
            ['id' => 1001, 'last' => '佐藤', 'first' => '花',   'course_id' => 1, 'course_price_id' => 1,  'monthly_fee' => 18000],
            ['id' => 1002, 'last' => '鈴木', 'first' => '陽太', 'course_id' => 1, 'course_price_id' => 2,  'monthly_fee' => 26000],
            ['id' => 1003, 'last' => '高橋', 'first' => '結衣', 'course_id' => 1, 'course_price_id' => 3,  'monthly_fee' => 35000],
            ['id' => 1004, 'last' => '田中', 'first' => '湊',   'course_id' => 2, 'course_price_id' => 4,  'monthly_fee' => 27000],
            ['id' => 1005, 'last' => '伊藤', 'first' => '凛',   'course_id' => 2, 'course_price_id' => 5,  'monthly_fee' => 30000],
            ['id' => 1006, 'last' => '渡辺', 'first' => '悠真', 'course_id' => 2, 'course_price_id' => 6,  'monthly_fee' => 33000],
            ['id' => 1007, 'last' => '中村', 'first' => '葵',   'course_id' => 3, 'course_price_id' => 7,  'monthly_fee' => 28000],
            ['id' => 1008, 'last' => '小林', 'first' => '蓮',   'course_id' => 3, 'course_price_id' => 8,  'monthly_fee' => 31000],
            ['id' => 1009, 'last' => '加藤', 'first' => '美月', 'course_id' => 3, 'course_price_id' => 9,  'monthly_fee' => 34000],
            ['id' => 1010, 'last' => '吉田', 'first' => '大和', 'course_id' => 4, 'course_price_id' => 10, 'monthly_fee' => 32000],
            ['id' => 1011, 'last' => '山本', 'first' => '紬',   'course_id' => 4, 'course_price_id' => 11, 'monthly_fee' => 35000],
            ['id' => 1012, 'last' => '松本', 'first' => '蒼',   'course_id' => 4, 'course_price_id' => 12, 'monthly_fee' => 38000],
        ];
    }

    public static function billingMonths(): array
    {
        return [4, 5, 6];
    }

    public static function admissionStudentIds(): array
    {
        return [1001, 1004, 1007, 1010];
    }

    public static function discountStudentIds(): array
    {
        return [1004, 1008, 1012];
    }

    public static function unpaidInvoiceTargets(): array
    {
        return [
            ['student_id' => 1004, 'month' => 6],
            ['student_id' => 1008, 'month' => 6],
            ['student_id' => 1012, 'month' => 6],
        ];
    }

    public static function admissionFee(): int
    {
        return 11000;
    }

    public static function discountAmount(): int
    {
        return 1000;
    }

    public static function isAdmissionTarget(int $studentId, int $month): bool
    {
        return $month === 4 && in_array($studentId, self::admissionStudentIds(), true);
    }

    public static function isDiscountTarget(int $studentId): bool
    {
        return in_array($studentId, self::discountStudentIds(), true);
    }

    public static function isUnpaidTarget(int $studentId, int $month): bool
    {
        foreach (self::unpaidInvoiceTargets() as $target) {
            if ($target['student_id'] === $studentId && $target['month'] === $month) {
                return true;
            }
        }

        return false;
    }
}