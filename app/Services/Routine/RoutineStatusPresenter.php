<?php

namespace App\Services\Routine;

/**
 * ルーティン状態の表示統一を担うPresenter。
 * 関連画面: 教育＞ルーティン一覧・達成状況・達成履歴・生徒カルテ
 * 利用DB: なし（DB値を画面表示用へ変換するのみ）
 * 参照/更新区分: 参照のみ
 */
final class RoutineStatusPresenter
{
    public static function label(?string $status, ?bool $isActive = null, $completedAt = null): string
    {
        $normalized = mb_strtolower(trim((string) $status));

        if ($completedAt || in_array($normalized, ['completed', 'complete', 'done', '完了', '達成'], true)) {
            return '完了';
        }
        if (in_array($normalized, ['partial', 'partially_completed', '一部完了'], true)) {
            return '一部完了';
        }
        if (in_array($normalized, ['studying', 'in_progress', 'learning', '学習中'], true)) {
            return '学習中';
        }
        if (in_array($normalized, ['paused', 'stopped', 'inactive', '停止'], true) || ($isActive === false && $normalized === '')) {
            return '停止';
        }
        if (in_array($normalized, ['not_started', 'unstarted', '未着手', ''], true)) {
            return '未着手';
        }
        if (in_array($normalized, ['cancelled', 'canceled', '取消'], true)) {
            return '取消';
        }
        if (in_array($normalized, ['expired', 'overdue', '期限超過'], true)) {
            return '期限超過';
        }
        if (in_array($normalized, ['before_start', 'scheduled', '開始前'], true)) {
            return '開始前';
        }

        return $isActive === true ? '実施中' : ($status ? (string) $status : '未着手');
    }

    public static function badgeClass(string $label): string
    {
        return match ($label) {
            '実施中', '完了', '達成' => 'green',
            '開始前', '学習中' => 'blue',
            '一部完了' => 'amber',
            '未着手' => 'orange',
            '期限超過', '取消' => 'red',
            default => 'gray',
        };
    }


    /**
     * 生徒カルテと同じ考え方で、日次の結果ではなく継続状況を判定する。
     * 戻り値: label / badge_class / reason
     */
    public static function followStatus(
        ?int $requiredDays,
        ?int $paceDiff,
        int $consecutiveMissedDays,
        ?string $todayStatus,
        bool $isActive = true,
        $completedAt = null
    ): array {
        if (!$isActive) {
            return ['label' => '停止', 'badge_class' => 'gray', 'reason' => '現在停止中のルーティンアイテムです。'];
        }

        if (!$requiredDays || $requiredDays <= 0 || $paceDiff === null) {
            return ['label' => '—', 'badge_class' => 'gray', 'reason' => '達成必要日数が未設定のため、状態判定の対象外です。'];
        }

        if ($consecutiveMissedDays >= 3 || $paceDiff <= -40) {
            return ['label' => 'フォロー急務', 'badge_class' => 'red', 'reason' => '連続未実施または学習ペースの大幅な遅れがあります。'];
        }

        if ($consecutiveMissedDays >= 2 || ($paceDiff <= -20 && $paceDiff >= -39)) {
            return ['label' => 'フォロー必要', 'badge_class' => 'orange', 'reason' => '連続未実施または学習ペースの遅れがあります。'];
        }

        $todayLabel = self::label($todayStatus, true, $completedAt);
        if ($todayLabel === '未着手' && $paceDiff <= -1 && $paceDiff >= -19) {
            return ['label' => '要確認', 'badge_class' => 'amber', 'reason' => '今日が未着手で、予定より少し遅れています。'];
        }

        if (in_array($todayLabel, ['完了', '達成'], true) || $paceDiff >= 0) {
            return ['label' => '順調', 'badge_class' => 'green', 'reason' => '予定どおり、または予定以上に進んでいます。'];
        }

        return ['label' => '要確認', 'badge_class' => 'amber', 'reason' => '学習ペースを確認してください。'];
    }

    public static function approvalLabel($approvedAt, $approvedBy, ?string $status): string
    {
        if ($approvedAt || $approvedBy) {
            return '承認済';
        }

        $label = self::label($status);
        return in_array($label, ['完了', '達成', '一部完了'], true) ? '承認待ち' : '承認不要';
    }
}
