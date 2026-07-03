<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => '未払い',
            self::Paid => '支払済',
            self::Cancelled => '取消',
        };
    }

    public function accountTransactionStatus(): string
    {
        return match ($this) {
            self::Unpaid => 'planned',
            self::Paid => 'confirmed',
            self::Cancelled => 'cancelled',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases()
        );
    }
}
