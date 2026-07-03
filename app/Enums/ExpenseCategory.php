<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case TeachingMaterial = 'teaching_material';
    case Equipment = 'equipment';
    case Consumable = 'consumable';
    case Advertising = 'advertising';
    case Communication = 'communication';
    case Transportation = 'transportation';
    case Outsourcing = 'outsourcing';
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TeachingMaterial => '教材費',
            self::Equipment => '備品費',
            self::Consumable => '消耗品費',
            self::Advertising => '広告宣伝費',
            self::Communication => '通信費',
            self::Transportation => '交通費',
            self::Outsourcing => '外注費',
            self::Rent => '家賃',
            self::Utilities => '水道光熱費',
            self::Other => 'その他',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $category) => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            self::cases()
        );
    }
}
