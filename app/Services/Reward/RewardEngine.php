<?php

namespace App\Services\Reward;

use App\Enums\BadgeGrantMethod;
use App\Models\Badge;
use App\Models\StudentBadge;
use DomainException;

/**
 * LifeForce Core内の報酬付与を受け付ける共通入口。
 *
 * 現段階の責務:
 * - 外部機能からバッジコード単位の付与要求を受け取る。
 * - 実際の保存処理はBadgeGrantServiceへ委譲する。
 *
 * 将来追加する責務:
 * - ルーティン達成、チャレンジ合格、学習継続などの条件判定
 * - TitleGrantService、PointGrantServiceとの連携
 * - 重い判定処理のQueue化
 *
 * 利用側はstudent_badgesやstudent_badge_eventsを直接更新せず、
 * RewardEngineまたは各GrantServiceを経由する。
 */
class RewardEngine
{
    public function __construct(
        private readonly BadgeGrantService $badgeGrantService,
    ) {
    }

    /**
     * バッジコードを指定して1件付与する。
     *
     * @param  array<string, mixed>  $context
     */
    public function grantBadgeByCode(int $studentId, string $badgeCode, array $context = []): StudentBadge
    {
        $badge = Badge::query()->where('code', $badgeCode)->first();

        if (! $badge) {
            throw new DomainException("バッジコード『{$badgeCode}』が見つかりません。");
        }

        $context['grant_method'] = $context['grant_method'] ?? BadgeGrantMethod::AUTO;

        return $this->badgeGrantService->grant($studentId, $badge->id, $context);
    }

    /**
     * 1つの達成処理から複数バッジを付与するための共通入口。
     *
     * 現段階では、呼び出し元が判定済みのbadge_codesを渡す。
     * 自動条件エンジンはバッジ画面完成後のPhase9で実装する。
     *
     * @param  array<int, string>  $badgeCodes
     * @param  array<string, mixed>  $context
     * @return array<int, StudentBadge>
     */
    public function grantBadgesByCodes(int $studentId, array $badgeCodes, array $context = []): array
    {
        $results = [];

        foreach (array_values(array_unique($badgeCodes)) as $badgeCode) {
            $results[] = $this->grantBadgeByCode($studentId, $badgeCode, $context);
        }

        return $results;
    }
}
