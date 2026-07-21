<?php

namespace Database\Seeders;

use App\Enums\BadgeGrantMethod;
use App\Enums\BadgeRemovalReason;
use App\Models\Badge;
use App\Models\BadgeCategory;
use App\Models\Student;
use App\Models\StudentBadge;
use App\Models\User;
use App\Services\Reward\BadgeGrantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * わくわく > バッジ画面開発用Seeder。
 *
 * 作成データ:
 * - バッジカテゴリ5件
 * - バッジ15件
 * - 生徒が存在する場合、付与中・取り外し済み・再付与の履歴サンプル
 *
 * 安全性:
 * - カテゴリは名称、バッジはcodeでupdateOrCreateする。
 * - 既存のstudent_badgesは削除しない。
 * - 同じ開発データが存在する場合は付与を重複させない。
 *
 * 実行:
 * php artisan db:seed --class=WakuwakuBadgeDevelopmentSeeder
 */
class WakuwakuBadgeDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $categories = $this->seedCategories();
            $badges = $this->seedBadges($categories);
            $this->seedGrantHistories($badges);
        });
    }

    /**
     * @return array<string, BadgeCategory>
     */
    private function seedCategories(): array
    {
        $definitions = [
            'brain' => ['脳開発', '集中・記憶・思考など、脳の土台づくりに関するバッジ'],
            'continuation' => ['継続', '連続学習や習慣化に関するバッジ'],
            'challenge' => ['挑戦', '新しい課題やチャレンジへの挑戦に関するバッジ'],
            'shogi' => ['将棋', '詰将棋・棋力・対局など将棋学習に関するバッジ'],
            'special' => ['特別', 'イベントや管理者表彰など特別なバッジ'],
        ];

        $result = [];
        $order = 1;

        foreach ($definitions as $key => [$name, $description]) {
            $result[$key] = BadgeCategory::query()->updateOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'display_order' => $order++,
                    'is_active' => true,
                ],
            );
        }

        return $result;
    }

    /**
     * @param  array<string, BadgeCategory>  $categories
     * @return array<string, Badge>
     */
    private function seedBadges(array $categories): array
    {
        $definitions = [
            ['LF-BDG-001', 'brain', '集中スタート', 1, '集中トレーニングに初めて取り組みました。', 10, 'both'],
            ['LF-BDG-002', 'brain', '集中マスター', 3, '集中トレーニングを継続して達成しました。', 50, 'both'],
            ['LF-BDG-003', 'brain', '記憶チャレンジャー', 2, '記憶課題へ積極的に挑戦しました。', 30, 'both'],
            ['LF-BDG-004', 'continuation', '3日連続学習', 1, '3日間連続で学習しました。', 20, 'auto'],
            ['LF-BDG-005', 'continuation', '7日連続学習', 2, '7日間連続で学習しました。', 50, 'auto'],
            ['LF-BDG-006', 'continuation', '30日継続', 5, '学習を30日間継続しました。', 200, 'auto'],
            ['LF-BDG-007', 'challenge', 'はじめての挑戦', 1, '初めてチャレンジへ参加しました。', 20, 'both'],
            ['LF-BDG-008', 'challenge', 'チャレンジ合格', 2, 'チャレンジに合格しました。', 50, 'auto'],
            ['LF-BDG-009', 'challenge', 'あきらめない心', 3, '失敗しても再挑戦し、最後まで取り組みました。', 80, 'manual'],
            ['LF-BDG-010', 'shogi', '詰将棋はじめの一手', 1, '初めて詰将棋問題を解きました。', 20, 'auto'],
            ['LF-BDG-011', 'shogi', '詰将棋10問達成', 2, '詰将棋を10問達成しました。', 50, 'auto'],
            ['LF-BDG-012', 'shogi', '読みの達人', 4, '先を読む力を発揮しました。', 100, 'manual'],
            ['LF-BDG-013', 'special', '今月のがんばり賞', 3, '今月、特に努力した生徒へ贈られます。', 100, 'manual'],
            ['LF-BDG-014', 'special', 'おともだち応援賞', 2, '仲間を応援し、良い影響を与えました。', 50, 'manual'],
            ['LF-BDG-015', 'special', '生きる力スター', 5, '考える・挑戦する・続ける力を総合的に発揮しました。', 300, 'manual'],
        ];

        $result = [];

        foreach ($definitions as $index => [$code, $categoryKey, $name, $level, $description, $points, $grantMethod]) {
            $result[$code] = Badge::query()->updateOrCreate(
                ['code' => $code],
                [
                    'badge_category_id' => $categories[$categoryKey]->id,
                    'badge_series_id' => null,
                    'name' => $name,
                    'level' => $level,
                    'description' => $description,
                    'acquisition_message' => "「{$name}」を獲得しました！",
                    'grant_method' => $grantMethod,
                    'allow_regrant' => true,
                    'notify_on_grant' => true,
                    'image_path' => null,
                    'locked_image_path' => null,
                    'point_reward' => $points,
                    'is_limited' => false,
                    'start_date' => null,
                    'end_date' => null,
                    'display_order' => $index + 1,
                    'is_active' => true,
                    'created_by' => null,
                    'updated_by' => null,
                ],
            );
        }

        return $result;
    }

    /**
     * @param  array<string, Badge>  $badges
     */
    private function seedGrantHistories(array $badges): void
    {
        $students = Student::query()->orderBy('id')->limit(4)->get();

        if ($students->isEmpty()) {
            $this->command?->warn('studentsが0件のため、バッジマスタのみ作成しました。');
            return;
        }

        $operatorId = User::query()->orderBy('id')->value('id');
        /** @var BadgeGrantService $service */
        $service = app(BadgeGrantService::class);

        // 1人目: 現在付与中の履歴を複数作成する。
        $first = $students->get(0);
        $this->grantUnlessActive($service, $first->id, $badges['LF-BDG-001'], $operatorId, now()->subDays(18), '開発用：初回付与サンプル');
        $this->grantUnlessActive($service, $first->id, $badges['LF-BDG-007'], $operatorId, now()->subDays(8), '開発用：挑戦達成サンプル');

        // 2人目: 取り外し済みの履歴を作成する。
        if ($second = $students->get(1)) {
            $assignment = $this->grantUnlessAnyHistory(
                $service,
                $second->id,
                $badges['LF-BDG-013'],
                $operatorId,
                now()->subDays(12),
                '開発用：誤付与サンプル',
            );

            if ($assignment?->isActive()) {
                $service->remove($assignment->id, [
                    'operator_id' => $operatorId,
                    'reason_code' => BadgeRemovalReason::MISTAKEN_GRANT,
                    'reason_detail' => '対象生徒を取り違えて付与したため',
                    'removed_at' => now()->subDays(11),
                    'notify' => false,
                    'metadata' => ['seed' => self::class],
                ]);
            }
        }

        // 3人目: 付与→取り外し→再付与の一連の履歴を作成する。
        if ($third = $students->get(2)) {
            $source = StudentBadge::query()
                ->where('student_id', $third->id)
                ->where('badge_id', $badges['LF-BDG-009']->id)
                ->whereNull('regrant_source_id')
                ->first();

            if (! $source) {
                $source = $service->grant($third->id, $badges['LF-BDG-009']->id, [
                    'grant_method' => BadgeGrantMethod::MANUAL,
                    'operator_id' => $operatorId,
                    'reason' => '開発用：再付与前の初回付与',
                    'acquired_at' => now()->subDays(20),
                    'notify' => false,
                    'metadata' => ['seed' => self::class],
                ]);
            }

            if ($source->isActive()) {
                $source = $service->remove($source->id, [
                    'operator_id' => $operatorId,
                    'reason_code' => BadgeRemovalReason::REGISTRATION_CORRECTION,
                    'reason_detail' => '獲得日の登録内容を修正するため',
                    'removed_at' => now()->subDays(19),
                    'notify' => false,
                    'metadata' => ['seed' => self::class],
                ]);
            }

            $hasRegrant = StudentBadge::query()->where('regrant_source_id', $source->id)->exists();

            if (! $hasRegrant) {
                $service->regrant($source->id, [
                    'operator_id' => $operatorId,
                    'reason' => '正しい達成記録を確認したため再付与',
                    'acquired_at' => now()->subDays(17),
                    'notify' => false,
                    'metadata' => ['seed' => self::class],
                ]);
            }
        }

        // 4人目: 自動付与サンプル。
        if ($fourth = $students->get(3)) {
            $this->grantUnlessActive(
                $service,
                $fourth->id,
                $badges['LF-BDG-004'],
                null,
                now()->subDays(3),
                '3日連続学習条件を達成',
                BadgeGrantMethod::AUTO,
            );
        }
    }

    private function grantUnlessActive(
        BadgeGrantService $service,
        int $studentId,
        Badge $badge,
        ?int $operatorId,
        mixed $acquiredAt,
        string $reason,
        BadgeGrantMethod $method = BadgeGrantMethod::MANUAL,
    ): ?StudentBadge {
        $active = StudentBadge::query()
            ->where('student_id', $studentId)
            ->where('badge_id', $badge->id)
            ->where('status', 'active')
            ->first();

        if ($active) {
            return $active;
        }

        return $service->grant($studentId, $badge->id, [
            'grant_method' => $method,
            'operator_id' => $operatorId,
            'reason' => $reason,
            'acquired_at' => $acquiredAt,
            'notify' => false,
            'metadata' => ['seed' => self::class],
        ]);
    }

    private function grantUnlessAnyHistory(
        BadgeGrantService $service,
        int $studentId,
        Badge $badge,
        ?int $operatorId,
        mixed $acquiredAt,
        string $reason,
    ): ?StudentBadge {
        $existing = StudentBadge::query()
            ->where('student_id', $studentId)
            ->where('badge_id', $badge->id)
            ->oldest('id')
            ->first();

        return $existing ?: $service->grant($studentId, $badge->id, [
            'grant_method' => BadgeGrantMethod::MANUAL,
            'operator_id' => $operatorId,
            'reason' => $reason,
            'acquired_at' => $acquiredAt,
            'notify' => false,
            'metadata' => ['seed' => self::class],
        ]);
    }
}
