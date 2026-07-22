<?php

namespace App\Enums;

/**
 * 称号付与方法。
 *
 * student_titles.grant_method と titles.grant_method で共通利用し、
 * 自動付与・手動付与・再付与の判定値を一元管理する。
 */
enum TitleGrantMethod: string
{
    case AUTO = 'auto';
    case MANUAL = 'manual';
    case BOTH = 'both';
    case REGRANT = 'regrant';
    case SAMPLE = 'sample';

    public function label(): string
    {
        return match ($this) {
            self::AUTO => '自動',
            self::MANUAL => '手動',
            self::BOTH => '自動・手動',
            self::REGRANT => '再付与',
        };
    }
}
