<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * 報酬付与サービスの共通契約。
 *
 * 役割:
 * - バッジ・称号など、保有レコードを持つ報酬の基本操作を統一する。
 * - ControllerからDB更新の詳細を分離する。
 *
 * 実装予定:
 * - BadgeGrantService
 * - TitleGrantService（将来）
 *
 * 注意:
 * ポイントは残高加算型のため、同じ契約に無理に合わせず、将来は
 * PointGrantServiceInterfaceとして独立させる。
 */
interface RewardGrantServiceInterface
{
    /**
     * 生徒へ報酬を付与する。
     *
     * @param  int  $studentId 対象生徒ID
     * @param  int  $rewardId 対象報酬ID
     * @param  array<string, mixed>  $context 操作者・理由・日時・補足情報
     */
    public function grant(int $studentId, int $rewardId, array $context = []): Model;

    /**
     * 現在付与中の報酬を取り外す。
     *
     * @param  int  $assignmentId 生徒報酬レコードID
     * @param  array<string, mixed>  $context 操作者・理由・日時・補足情報
     */
    public function remove(int $assignmentId, array $context = []): Model;

    /**
     * 取り外し済みの報酬を、新しい付与レコードとして再付与する。
     *
     * @param  int  $assignmentId 再付与元の生徒報酬レコードID
     * @param  array<string, mixed>  $context 操作者・理由・日時・補足情報
     */
    public function regrant(int $assignmentId, array $context = []): Model;
}
