<?php

namespace App\Enums;

enum RefundSourceType: string
{
    case TuitionEnrollment = 'tuition_enrollment';
    case Shop = 'shop';
    case Event = 'event';
    case Spot = 'spot';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TuitionEnrollment => '授業料・入会金返金',
            self::Shop => 'ショップ返金',
            self::Event => 'イベント返金',
            self::Spot => 'スポット返金',
            self::Other => 'その他',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases()
        );
    }
}
