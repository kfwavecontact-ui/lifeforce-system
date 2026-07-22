<?php

namespace App\Enums;

/**
 * 生徒称号の現在状態。
 *
 * active は現在保有中、removed は取り外し済みを表す。
 */
enum StudentTitleStatus: string
{
    case ACTIVE = 'active';
    case REMOVED = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => '付与中',
            self::REMOVED => '取り外し済み',
        };
    }
}
