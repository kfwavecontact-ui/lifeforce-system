<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopOrderItemSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('shop_order_items')->truncate();

        $orders = DB::table('shop_orders')
            ->select('id')
            ->orderBy('id')
            ->get();

        $products = DB::table('shop_products')
            ->select('id', 'product_code', 'name', 'price', 'purchase_price', 'tax_rate', 'point_reward')
            ->orderBy('id')
            ->get();

        foreach ($orders as $orderIndex => $order) {
            $itemCount = ($orderIndex % 3) + 1;

            $orderSubtotal = 0;
            $orderDiscount = 0;
            $orderTax = 0;
            $orderTotal = 0;

            for ($i = 0; $i < $itemCount; $i++) {
                $product = $products[($orderIndex + $i) % $products->count()];

                $quantity = ($i % 2) + 1;
                $unitPrice = (int) $product->price;
                $subtotal = $unitPrice * $quantity;
                $discount = $i === 0 && $orderIndex % 4 === 0 ? 100 : 0;
                $taxRate = (int) $product->tax_rate;
                $total = max(0, $subtotal - $discount);
                $taxAmount = (int) floor($total * $taxRate / (100 + $taxRate));

                DB::table('shop_order_items')->insert([
                    'shop_order_id' => $order->id,
                    'shop_product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->product_code,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'purchase_price' => (int) $product->purchase_price,
                    'discount_amount' => $discount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'subtotal_amount' => $subtotal,
                    'total_amount' => $total,
                    'point_reward' => (int) $product->point_reward * $quantity,
                    'point_used' => 0,
                    'memo' => '開発用サンプル明細',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $orderSubtotal += $subtotal;
                $orderDiscount += $discount;
                $orderTax += $taxAmount;
                $orderTotal += $total;
            }

            DB::table('shop_orders')
                ->where('id', $order->id)
                ->update([
                    'subtotal_amount' => $orderSubtotal,
                    'discount_amount' => $orderDiscount,
                    'tax_amount' => $orderTax,
                    'total_amount' => $orderTotal,
                    'updated_at' => now(),
                ]);
        }
    }
}