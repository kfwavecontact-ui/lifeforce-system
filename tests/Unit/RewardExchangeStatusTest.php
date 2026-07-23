<?php

namespace Tests\Unit;

use App\Enums\RewardExchangeStatus;
use PHPUnit\Framework\TestCase;

/** 商品交換申請の状態遷移が不正な飛び越しを許可しないことを確認します。 */
class RewardExchangeStatusTest extends TestCase
{
    public function test_requested_can_only_move_to_approved_rejected_or_cancelled(): void
    {
        $status = RewardExchangeStatus::Requested;

        $this->assertTrue($status->canTransitionTo(RewardExchangeStatus::Approved));
        $this->assertTrue($status->canTransitionTo(RewardExchangeStatus::Rejected));
        $this->assertTrue($status->canTransitionTo(RewardExchangeStatus::Cancelled));
        $this->assertFalse($status->canTransitionTo(RewardExchangeStatus::Delivered));
    }

    public function test_terminal_status_cannot_be_processed_again(): void
    {
        foreach ([RewardExchangeStatus::Delivered, RewardExchangeStatus::Rejected, RewardExchangeStatus::Cancelled] as $status) {
            $this->assertFalse($status->canTransitionTo(RewardExchangeStatus::Approved));
            $this->assertFalse($status->canTransitionTo(RewardExchangeStatus::Delivered));
        }
    }
}
