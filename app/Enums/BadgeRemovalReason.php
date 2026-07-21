<?php

namespace App\Enums;

/**
 * バッジ取り外し理由。
 */
enum BadgeRemovalReason: string
{
    case MISTAKEN_GRANT = 'mistaken_grant';
    case REQUIREMENT_NOT_MET = 'requirement_not_met';
    case REGISTRATION_CORRECTION = 'registration_correction';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MISTAKEN_GRANT => '誤って付与した',
            self::REQUIREMENT_NOT_MET => '獲得条件を満たしていなかった',
            self::REGISTRATION_CORRECTION => '登録内容の修正',
            self::OTHER => 'その他',
        };
    }
}
