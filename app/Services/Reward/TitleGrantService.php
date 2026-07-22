<?php

namespace App\Services\Reward;

use App\Contracts\RewardGrantServiceInterface;
use App\Enums\TitleGrantMethod;
use App\Enums\TitleRemovalReason;
use App\Enums\TitleHistoryType;
use App\Enums\StudentTitleStatus;
use App\Models\Title;
use App\Models\Student;
use App\Models\StudentTitle;
use App\Models\TitleHistory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * 称号の付与・取り外し・再付与を一元管理するサービス。
 *
 * 関連画面:
 * - わくわく > 称号 > 獲得履歴
 * - 手動付与モーダル
 * - 取り外しモーダル
 * - 再付与モーダル
 *
 * 利用DB:
 * - students: 対象生徒の存在確認（参照）
 * - titles: 称号状態・付与方式・再付与可否の確認（参照）
 * - student_titles: 1回の付与単位と現在状態（参照・追加・更新）
 * - title_histories: 全操作履歴（追加）
 *
 * 設計方針:
 * - Controllerからstudent_titlesを直接更新しない。
 * - 付与本体とイベント履歴を同一トランザクションで保存する。
 * - 取り外し済みレコードをactiveへ戻さず、再付与時は新規行を作る。
 * - 通知送信は別サービスで実装し、このクラスでは送信済みフラグを先に立てない。
 */
class TitleGrantService implements RewardGrantServiceInterface
{
    /** @var array<string, mixed> */
    private array $currentGrantContext = [];
    /**
     * 生徒へ称号を付与する。
     *
     * context:
     * - grant_method: TitleGrantMethod|string（manual / auto）
     * - operator_id: 操作者users.id。自動処理はnull
     * - reason: 付与理由
     * - acquired_at: CarbonInterface|string。未指定は現在日時
     * - is_displayed: 生徒画面へ表示するか
     * - notify: 通知を要求するか。実送信は後続フェーズ
     * - metadata: イベントへ保存する補足配列
     */
    public function grant(int $studentId, int $rewardId, array $context = []): StudentTitle
    {
        return DB::transaction(function () use ($studentId, $rewardId, $context): StudentTitle {
            $student = Student::query()->lockForUpdate()->findOrFail($studentId);
            $title = Title::query()->lockForUpdate()->findOrFail($rewardId);
            $method = $this->resolveGrantMethod($context['grant_method'] ?? TitleGrantMethod::MANUAL);
            $this->currentGrantContext = $context;

            $this->assertTitleCanBeGranted($title, $method);
            $this->assertNoActiveTitle($student->id, $title->id);

            $acquiredAt = $this->resolveDateTime($context['acquired_at'] ?? null);

            $studentTitle = StudentTitle::query()->create([
                'student_id' => $student->id,
                'title_id' => $title->id,
                'status' => StudentTitleStatus::ACTIVE,
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
                'is_equipped' => false,
            ]);

            $this->recordEvent(
                studentTitle: $studentTitle,
                eventType: TitleHistoryType::GRANTED,
                operatedBy: $context['operator_id'] ?? null,
                reasonDetail: Arr::get($context, 'reason'),
                eventAt: $acquiredAt,
                metadata: $this->buildMetadata($context, [
                    'grant_method' => $method->value,
                    'notification_requested' => (bool) ($context['notify'] ?? $title->notify_on_grant),
                ]),
            );

            return $studentTitle->fresh(['student', 'title', 'events']);
        }, 3);
    }

    /**
     * 付与中の称号を取り外す。
     *
     * context:
     * - operator_id: 取り外したusers.id
     * - reason_code: TitleRemovalReason|string（必須）
     * - reason_detail: 理由詳細。otherの場合は必須
     * - removed_at: CarbonInterface|string。未指定は現在日時
     * - notify: 取り外し通知を要求するか
     * - metadata: イベントへ保存する補足配列
     */
    public function remove(int $assignmentId, array $context = []): StudentTitle
    {
        return DB::transaction(function () use ($assignmentId, $context): StudentTitle {
            $studentTitle = StudentTitle::query()->lockForUpdate()->findOrFail($assignmentId);

            if (! $studentTitle->isActive()) {
                throw new DomainException('取り外し済みの称号は再度取り外せません。');
            }

            $reason = $this->resolveRemovalReason($context['reason_code'] ?? null);
            $reasonDetail = trim((string) ($context['reason_detail'] ?? ''));

            if ($reason === TitleRemovalReason::OTHER && $reasonDetail === '') {
                throw new DomainException('取り外し理由で「その他」を選択した場合は、理由の詳細が必要です。');
            }

            $removedAt = $this->resolveDateTime($context['removed_at'] ?? null);

            $studentTitle->update([
                'status' => StudentTitleStatus::REMOVED,
                'removed_at' => $removedAt,
                'removed_by' => $context['operator_id'] ?? null,
                'removal_reason_code' => $reason,
                'removal_reason_detail' => $reasonDetail !== '' ? $reasonDetail : null,
                'removal_notification_sent' => false,
            ]);

            $this->recordEvent(
                studentTitle: $studentTitle,
                eventType: TitleHistoryType::REMOVED,
                operatedBy: $context['operator_id'] ?? null,
                reasonCode: $reason,
                reasonDetail: $reasonDetail !== '' ? $reasonDetail : null,
                eventAt: $removedAt,
                metadata: $this->buildMetadata($context, [
                    'before_status' => StudentTitleStatus::ACTIVE->value,
                    'after_status' => StudentTitleStatus::REMOVED->value,
                    'notification_requested' => (bool) ($context['notify'] ?? false),
                ]),
            );

            return $studentTitle->fresh(['student', 'title', 'events']);
        }, 3);
    }

    /**
     * 取り外し済み称号を新しい付与行として再付与する。
     */
    public function regrant(int $assignmentId, array $context = []): StudentTitle
    {
        return DB::transaction(function () use ($assignmentId, $context): StudentTitle {
            $source = StudentTitle::query()
                ->with('title')
                ->lockForUpdate()
                ->findOrFail($assignmentId);

            if ($source->status !== StudentTitleStatus::REMOVED) {
                throw new DomainException('付与中の称号は再付与できません。');
            }

            if (! $source->title->allow_regrant) {
                throw new DomainException('この称号は再付与が許可されていません。');
            }

            $this->assertNoActiveTitle($source->student_id, $source->title_id);
            $acquiredAt = $this->resolveDateTime($context['acquired_at'] ?? null);

            $regranted = StudentTitle::query()->create([
                'student_id' => $source->student_id,
                'title_id' => $source->title_id,
                'status' => StudentTitleStatus::ACTIVE,
                'grant_method' => TitleGrantMethod::REGRANT,
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
                'is_equipped' => false,
            ]);

            $this->recordEvent(
                studentTitle: $regranted,
                eventType: TitleHistoryType::REGRANTED,
                operatedBy: $context['operator_id'] ?? null,
                reasonDetail: Arr::get($context, 'reason'),
                eventAt: $acquiredAt,
                metadata: $this->buildMetadata($context, [
                    'regrant_source_id' => $source->id,
                    'notification_requested' => (bool) ($context['notify'] ?? $source->title->notify_on_grant),
                ]),
            );

            return $regranted->fresh(['student', 'title', 'regrantSource', 'events']);
        }, 3);
    }

    /**
     * 称号の有効状態・期間・許可された付与方式を検証する。
     */
    private function assertTitleCanBeGranted(Title $title, TitleGrantMethod $method): void
    {
        if (! $title->is_active) {
            throw new DomainException('無効な称号は付与できません。');
        }

        $today = now()->startOfDay();

        if ($title->start_date && $today->lt($title->start_date->startOfDay())) {
            throw new DomainException('この称号は付与期間前です。');
        }

        if ($title->end_date && $today->gt($title->end_date->endOfDay())) {
            throw new DomainException('この称号の付与期間は終了しています。');
        }

        $allowManualOverride = $method === TitleGrantMethod::MANUAL
            && (bool) ($this->currentGrantContext['allow_manual_override'] ?? false);

        $allowed = match ($method) {
            TitleGrantMethod::MANUAL => $allowManualOverride
                ? [TitleGrantMethod::AUTO, TitleGrantMethod::MANUAL, TitleGrantMethod::BOTH]
                : [TitleGrantMethod::MANUAL, TitleGrantMethod::BOTH],
            TitleGrantMethod::AUTO => [TitleGrantMethod::AUTO, TitleGrantMethod::BOTH],
            default => [],
        };

        if (! in_array($title->grant_method, $allowed, true)) {
            throw new DomainException('この称号では指定された付与方法を利用できません。');
        }
    }

    /**
     * DB制約へ到達する前に、利用者向けの明確な重複エラーを返す。
     */
    private function assertNoActiveTitle(int $studentId, int $titleId): void
    {
        $exists = StudentTitle::query()
            ->where('student_id', $studentId)
            ->where('title_id', $titleId)
            ->where('status', StudentTitleStatus::ACTIVE->value)
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw new DomainException('対象生徒は、この称号をすでに保有しています。');
        }
    }

    private function resolveGrantMethod(TitleGrantMethod|string $method): TitleGrantMethod
    {
        $resolved = $method instanceof TitleGrantMethod
            ? $method
            : TitleGrantMethod::tryFrom($method);

        if (! in_array($resolved, [TitleGrantMethod::AUTO, TitleGrantMethod::MANUAL], true)) {
            throw new DomainException('付与方法はautoまたはmanualを指定してください。');
        }

        return $resolved;
    }

    private function resolveRemovalReason(TitleRemovalReason|string|null $reason): TitleRemovalReason
    {
        $resolved = $reason instanceof TitleRemovalReason
            ? $reason
            : (is_string($reason) ? TitleRemovalReason::tryFrom($reason) : null);

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
     * title_historiesへの追加処理をこのService内へ閉じ込める。
     *
     * @param  array<string, mixed>  $metadata
     */
    private function recordEvent(
        StudentTitle $studentTitle,
        TitleHistoryType $eventType,
        ?int $operatedBy,
        CarbonInterface $eventAt,
        ?TitleRemovalReason $reasonCode = null,
        ?string $reasonDetail = null,
        array $metadata = [],
    ): TitleHistory {
        return TitleHistory::query()->create([
            'student_title_id' => $studentTitle->id,
            'student_id' => $studentTitle->student_id,
            'title_id' => $studentTitle->title_id,
            'event_type' => $eventType,
            'operated_by' => $operatedBy,
            'reason_code' => $reasonCode,
            'reason_detail' => $reasonDetail,
            'event_at' => $eventAt,
            'is_equipped' => false,
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
