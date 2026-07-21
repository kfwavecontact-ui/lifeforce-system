<?php

namespace App\Services\Reward;

use App\Contracts\RewardGrantServiceInterface;
use App\Enums\BadgeGrantMethod;
use App\Enums\BadgeRemovalReason;
use App\Enums\StudentBadgeEventType;
use App\Enums\StudentBadgeStatus;
use App\Models\Badge;
use App\Models\Student;
use App\Models\StudentBadge;
use App\Models\StudentBadgeEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * バッジの付与・取り外し・再付与を一元管理するサービス。
 *
 * 関連画面:
 * - わくわく > バッジ > 獲得履歴
 * - 手動付与モーダル
 * - 取り外しモーダル
 * - 再付与モーダル
 *
 * 利用DB:
 * - students: 対象生徒の存在確認（参照）
 * - badges: バッジ状態・付与方式・再付与可否の確認（参照）
 * - student_badges: 1回の付与単位と現在状態（参照・追加・更新）
 * - student_badge_events: 全操作履歴（追加）
 *
 * 設計方針:
 * - Controllerからstudent_badgesを直接更新しない。
 * - 付与本体とイベント履歴を同一トランザクションで保存する。
 * - 取り外し済みレコードをactiveへ戻さず、再付与時は新規行を作る。
 * - 通知送信は別サービスで実装し、このクラスでは送信済みフラグを先に立てない。
 */
class BadgeGrantService implements RewardGrantServiceInterface
{
    /** @var array<string, mixed> */
    private array $currentGrantContext = [];
    /**
     * 生徒へバッジを付与する。
     *
     * context:
     * - grant_method: BadgeGrantMethod|string（manual / auto）
     * - operator_id: 操作者users.id。自動処理はnull
     * - reason: 付与理由
     * - acquired_at: CarbonInterface|string。未指定は現在日時
     * - is_displayed: 生徒画面へ表示するか
     * - notify: 通知を要求するか。実送信は後続フェーズ
     * - metadata: イベントへ保存する補足配列
     */
    public function grant(int $studentId, int $rewardId, array $context = []): StudentBadge
    {
        return DB::transaction(function () use ($studentId, $rewardId, $context): StudentBadge {
            $student = Student::query()->lockForUpdate()->findOrFail($studentId);
            $badge = Badge::query()->lockForUpdate()->findOrFail($rewardId);
            $method = $this->resolveGrantMethod($context['grant_method'] ?? BadgeGrantMethod::MANUAL);
            $this->currentGrantContext = $context;

            $this->assertBadgeCanBeGranted($badge, $method);
            $this->assertNoActiveBadge($student->id, $badge->id);

            $acquiredAt = $this->resolveDateTime($context['acquired_at'] ?? null);

            $studentBadge = StudentBadge::query()->create([
                'student_id' => $student->id,
                'badge_id' => $badge->id,
                'status' => StudentBadgeStatus::ACTIVE,
                'grant_method' => $method,
                'acquired_at' => $acquiredAt,
                'granted_by' => $context['operator_id'] ?? null,
                'grant_reason' => Arr::get($context, 'reason'),
                'removed_at' => null,
                'removed_by' => null,
                'removal_reason_code' => null,
                'removal_reason_detail' => null,
                'regrant_source_id' => null,
                'grant_notification_sent' => false,
                'removal_notification_sent' => false,
                'is_displayed' => (bool) ($context['is_displayed'] ?? true),
            ]);

            $this->recordEvent(
                studentBadge: $studentBadge,
                eventType: StudentBadgeEventType::GRANTED,
                operatedBy: $context['operator_id'] ?? null,
                reasonDetail: Arr::get($context, 'reason'),
                eventAt: $acquiredAt,
                metadata: $this->buildMetadata($context, [
                    'grant_method' => $method->value,
                    'notification_requested' => (bool) ($context['notify'] ?? $badge->notify_on_grant),
                ]),
            );

            return $studentBadge->fresh(['student', 'badge', 'events']);
        }, 3);
    }

    /**
     * 付与中のバッジを取り外す。
     *
     * context:
     * - operator_id: 取り外したusers.id
     * - reason_code: BadgeRemovalReason|string（必須）
     * - reason_detail: 理由詳細。otherの場合は必須
     * - removed_at: CarbonInterface|string。未指定は現在日時
     * - notify: 取り外し通知を要求するか
     * - metadata: イベントへ保存する補足配列
     */
    public function remove(int $assignmentId, array $context = []): StudentBadge
    {
        return DB::transaction(function () use ($assignmentId, $context): StudentBadge {
            $studentBadge = StudentBadge::query()->lockForUpdate()->findOrFail($assignmentId);

            if (! $studentBadge->isActive()) {
                throw new DomainException('取り外し済みのバッジは再度取り外せません。');
            }

            $reason = $this->resolveRemovalReason($context['reason_code'] ?? null);
            $reasonDetail = trim((string) ($context['reason_detail'] ?? ''));

            if ($reason === BadgeRemovalReason::OTHER && $reasonDetail === '') {
                throw new DomainException('取り外し理由で「その他」を選択した場合は、理由の詳細が必要です。');
            }

            $removedAt = $this->resolveDateTime($context['removed_at'] ?? null);

            $studentBadge->update([
                'status' => StudentBadgeStatus::REMOVED,
                'removed_at' => $removedAt,
                'removed_by' => $context['operator_id'] ?? null,
                'removal_reason_code' => $reason,
                'removal_reason_detail' => $reasonDetail !== '' ? $reasonDetail : null,
                'removal_notification_sent' => false,
            ]);

            $this->recordEvent(
                studentBadge: $studentBadge,
                eventType: StudentBadgeEventType::REMOVED,
                operatedBy: $context['operator_id'] ?? null,
                reasonCode: $reason,
                reasonDetail: $reasonDetail !== '' ? $reasonDetail : null,
                eventAt: $removedAt,
                metadata: $this->buildMetadata($context, [
                    'before_status' => StudentBadgeStatus::ACTIVE->value,
                    'after_status' => StudentBadgeStatus::REMOVED->value,
                    'notification_requested' => (bool) ($context['notify'] ?? false),
                ]),
            );

            return $studentBadge->fresh(['student', 'badge', 'events']);
        }, 3);
    }

    /**
     * 取り外し済みバッジを新しい付与行として再付与する。
     */
    public function regrant(int $assignmentId, array $context = []): StudentBadge
    {
        return DB::transaction(function () use ($assignmentId, $context): StudentBadge {
            $source = StudentBadge::query()
                ->with('badge')
                ->lockForUpdate()
                ->findOrFail($assignmentId);

            if ($source->status !== StudentBadgeStatus::REMOVED) {
                throw new DomainException('付与中のバッジは再付与できません。');
            }

            if (! $source->badge->allow_regrant) {
                throw new DomainException('このバッジは再付与が許可されていません。');
            }

            $this->assertNoActiveBadge($source->student_id, $source->badge_id);
            $acquiredAt = $this->resolveDateTime($context['acquired_at'] ?? null);

            $regranted = StudentBadge::query()->create([
                'student_id' => $source->student_id,
                'badge_id' => $source->badge_id,
                'status' => StudentBadgeStatus::ACTIVE,
                'grant_method' => BadgeGrantMethod::REGRANT,
                'acquired_at' => $acquiredAt,
                'granted_by' => $context['operator_id'] ?? null,
                'grant_reason' => Arr::get($context, 'reason'),
                'removed_at' => null,
                'removed_by' => null,
                'removal_reason_code' => null,
                'removal_reason_detail' => null,
                'regrant_source_id' => $source->id,
                'grant_notification_sent' => false,
                'removal_notification_sent' => false,
                'is_displayed' => (bool) ($context['is_displayed'] ?? true),
            ]);

            $this->recordEvent(
                studentBadge: $regranted,
                eventType: StudentBadgeEventType::REGRANTED,
                operatedBy: $context['operator_id'] ?? null,
                reasonDetail: Arr::get($context, 'reason'),
                eventAt: $acquiredAt,
                metadata: $this->buildMetadata($context, [
                    'regrant_source_id' => $source->id,
                    'notification_requested' => (bool) ($context['notify'] ?? $source->badge->notify_on_grant),
                ]),
            );

            return $regranted->fresh(['student', 'badge', 'regrantSource', 'events']);
        }, 3);
    }

    /**
     * バッジの有効状態・期間・許可された付与方式を検証する。
     */
    private function assertBadgeCanBeGranted(Badge $badge, BadgeGrantMethod $method): void
    {
        if (! $badge->is_active) {
            throw new DomainException('無効なバッジは付与できません。');
        }

        $today = now()->startOfDay();

        if ($badge->start_date && $today->lt($badge->start_date->startOfDay())) {
            throw new DomainException('このバッジは付与期間前です。');
        }

        if ($badge->end_date && $today->gt($badge->end_date->endOfDay())) {
            throw new DomainException('このバッジの付与期間は終了しています。');
        }

        $allowManualOverride = $method === BadgeGrantMethod::MANUAL
            && (bool) ($this->currentGrantContext['allow_manual_override'] ?? false);

        $allowed = match ($method) {
            BadgeGrantMethod::MANUAL => $allowManualOverride
                ? [BadgeGrantMethod::AUTO, BadgeGrantMethod::MANUAL, BadgeGrantMethod::BOTH]
                : [BadgeGrantMethod::MANUAL, BadgeGrantMethod::BOTH],
            BadgeGrantMethod::AUTO => [BadgeGrantMethod::AUTO, BadgeGrantMethod::BOTH],
            default => [],
        };

        if (! in_array($badge->grant_method, $allowed, true)) {
            throw new DomainException('このバッジでは指定された付与方法を利用できません。');
        }
    }

    /**
     * DB制約へ到達する前に、利用者向けの明確な重複エラーを返す。
     */
    private function assertNoActiveBadge(int $studentId, int $badgeId): void
    {
        $exists = StudentBadge::query()
            ->where('student_id', $studentId)
            ->where('badge_id', $badgeId)
            ->where('status', StudentBadgeStatus::ACTIVE->value)
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw new DomainException('対象生徒は、このバッジをすでに保有しています。');
        }
    }

    private function resolveGrantMethod(BadgeGrantMethod|string $method): BadgeGrantMethod
    {
        $resolved = $method instanceof BadgeGrantMethod
            ? $method
            : BadgeGrantMethod::tryFrom($method);

        if (! in_array($resolved, [BadgeGrantMethod::AUTO, BadgeGrantMethod::MANUAL], true)) {
            throw new DomainException('付与方法はautoまたはmanualを指定してください。');
        }

        return $resolved;
    }

    private function resolveRemovalReason(BadgeRemovalReason|string|null $reason): BadgeRemovalReason
    {
        $resolved = $reason instanceof BadgeRemovalReason
            ? $reason
            : (is_string($reason) ? BadgeRemovalReason::tryFrom($reason) : null);

        if (! $resolved) {
            throw new DomainException('取り外し理由を選択してください。');
        }

        return $resolved;
    }

    private function resolveDateTime(CarbonInterface|string|null $value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return $value ? Carbon::parse($value) : now();
    }

    /**
     * student_badge_eventsへの追加処理をこのService内へ閉じ込める。
     *
     * @param  array<string, mixed>  $metadata
     */
    private function recordEvent(
        StudentBadge $studentBadge,
        StudentBadgeEventType $eventType,
        ?int $operatedBy,
        CarbonInterface $eventAt,
        ?BadgeRemovalReason $reasonCode = null,
        ?string $reasonDetail = null,
        array $metadata = [],
    ): StudentBadgeEvent {
        return StudentBadgeEvent::query()->create([
            'student_badge_id' => $studentBadge->id,
            'student_id' => $studentBadge->student_id,
            'badge_id' => $studentBadge->badge_id,
            'event_type' => $eventType,
            'operated_by' => $operatedBy,
            'reason_code' => $reasonCode,
            'reason_detail' => $reasonDetail,
            'event_at' => $eventAt,
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }

    /**
     * Service制御用キーを除き、明示的なmetadataだけを履歴へ保存する。
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $additional
     * @return array<string, mixed>
     */
    private function buildMetadata(array $context, array $additional = []): array
    {
        $metadata = Arr::get($context, 'metadata', []);

        return array_merge(is_array($metadata) ? $metadata : [], $additional);
    }
}
