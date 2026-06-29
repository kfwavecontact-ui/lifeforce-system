<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopOrderSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('shop_orders')->truncate();

        $students = DB::table('students')
            ->select('id', 'school_id', 'last_name', 'first_name')
            ->orderBy('id')
            ->limit(10)
            ->get();

        $paymentMethods = DB::table('payment_methods')
            ->select('id')
            ->orderBy('id')
            ->pluck('id')
            ->values();

        foreach ($students as $index => $student) {
            $orderedAt = Carbon::now()->subDays(10 - $index);
            $isPaid = $index % 3 !== 0;

            DB::table('shop_orders')->insert([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'order_no' => 'SHOP-' . $orderedAt->format('Ymd') . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),

                'order_status' => 'ordered',
                'payment_status' => $isPaid ? 'paid' : 'unpaid',
                'payment_method_id' => $paymentMethods->isNotEmpty()
                    ? $paymentMethods[$index % $paymentMethods->count()]
                    : null,

                'ordered_at' => $orderedAt,
                'scheduled_date' => $orderedAt->copy()->addDays(3)->toDateString(),
                'transaction_date' => $isPaid ? $orderedAt->copy()->addDays(1)->toDateString() : null,

                'subtotal_amount' => 0,
                'discount_amount' => 0,
                'coupon_discount' => 0,
                'point_discount' => 0,
                'campaign_discount' => 0,
                'shipping_fee' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,

                'buyer_name' => trim($student->last_name . ' ' . $student->first_name),
                'buyer_email' => null,
                'buyer_phone' => null,

                'delivery_method' => 'classroom',
                'delivery_status' => $isPaid ? 'delivered' : 'pending',
                'delivered_at' => $isPaid ? $orderedAt->copy()->addDays(2) : null,

                'cancelled_at' => null,
                'cancel_reason' => null,
                'memo' => '開発用サンプル注文',

                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}