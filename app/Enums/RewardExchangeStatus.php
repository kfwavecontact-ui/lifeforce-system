<?php

namespace App\Enums;

/** 商品交換申請の状態と、許可する遷移を一元管理します。 */
enum RewardExchangeStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => '申請中', self::Approved => '承認済', self::Preparing => '準備中',
            self::Shipped => '発送済', self::Delivered => '受渡済', self::Rejected => '却下', self::Cancelled => '取消',
        };
    }

    public function isOpen(): bool { return in_array($this, [self::Requested, self::Approved, self::Preparing, self::Shipped], true); }
    public function isTerminal(): bool { return in_array($this, [self::Delivered, self::Rejected, self::Cancelled], true); }

    /** 不正な飛び越しや完了後の再処理を防止します。 */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Requested => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Preparing, self::Delivered, self::Cancelled],
            self::Preparing => [self::Shipped, self::Delivered, self::Cancelled],
            self::Shipped => [self::Delivered],
            default => [],
        }, true);
    }

    public static function labels(): array
    {
        $result=[]; foreach(self::cases() as $case){$result[$case->value]=$case->label();} return $result;
    }
}
