<?php

namespace App\Enums;

/**
 * 称号操作履歴のイベント種別。
 */
enum TitleHistoryType: string
{
    case GRANTED = 'granted';
    case REMOVED = 'removed';
    case REGRANTED = 'regranted';

    public function label(): string
    {
        return match ($this) {
            self::GRANTED => '付与',
            self::REMOVED => '取り外し',
            self::REGRANTED => '再付与',
        };
    }
}
