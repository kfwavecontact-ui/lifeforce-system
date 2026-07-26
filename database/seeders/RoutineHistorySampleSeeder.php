<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * ルーティン達成履歴画面の件数・検索・ページネーション確認用Seeder。
 *
 * 役割:
 * - 既存の生徒ルーティンアイテムを利用し、日次履歴と学習結果の表示確認データを追加する。
 * - 既存のルーティン、割当、教材データは削除・変更しない。
 *
 * 使用DB:
 * - student_routines / student_routine_items: 参照
 * - student_routine_daily_statuses: 追加・同一キー更新
 * - routine_study_sessions / routine_study_results: 追加
 */
class RoutineHistorySampleSeeder extends Seeder
{
    private const SAMPLE_COMMENT = '【履歴画面確認用サンプル】';

    public function run(): void
    {
        foreach (['student_routines', 'student_routine_items', 'student_routine_daily_statuses', 'routine_study_sessions', 'routine_study_results'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException("{$table} テーブルが存在しないため、サンプルデータを作成できません。");
            }
        }

        $items = DB::table('student_routine_items as i')
            ->join('student_routines as r', 'r.id', '=', 'i.student_routine_id')
            ->select('i.id', 'i.student_routine_id', 'i.routine_content_id', 'r.student_id')
            ->orderBy('i.id')
            ->limit(30)
            ->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('生徒へ割り当て済みのルーティンアイテムがありません。先にルーティンを割り当ててください。');
        }

        $dailyColumns = Schema::getColumnListing('student_routine_daily_statuses');
        $sessionColumns = Schema::getColumnListing('routine_study_sessions');
        $resultColumns = Schema::getColumnListing('routine_study_results');
        $now = now();

        DB::transaction(function () use ($items, $dailyColumns, $sessionColumns, $resultColumns, $now): void {
            foreach ($items as $itemIndex => $item) {
                for ($dayOffset = 0; $dayOffset < 12; $dayOffset++) {
                    $targetDate = Carbon::today()->subDays($dayOffset);
                    $statusIndex = ($itemIndex + $dayOffset) % 4;
                    $status = ['完了', '達成', '学習中', '未着手'][$statusIndex];
                    $isCompleted = in_array($status, ['完了', '達成'], true);
                    $studySeconds = $status === '未着手' ? 0 : 120 + (($itemIndex + $dayOffset) % 15) * 20;
                    $achievementRate = $isCompleted ? 100 : ($status === '学習中' ? 50 : 0);
                    $studiedAt = $status === '未着手' ? null : $targetDate->copy()->setTime(16 + ($itemIndex % 3), ($dayOffset * 7) % 60);

                    $dailyData = [
                        'student_id' => $item->student_id,
                        'student_routine_item_id' => $item->id,
                        'target_date' => $targetDate->toDateString(),
                        'status' => $status,
                        'achieved_days' => $isCompleted ? 1 : 0,
                        'elapsed_days' => $dayOffset + 1,
                        'achievement_rate' => $achievementRate,
                        'studied_at' => $studiedAt,
                        'study_seconds' => $studySeconds,
                        'comment' => self::SAMPLE_COMMENT,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $dailyData = array_intersect_key($dailyData, array_flip($dailyColumns));

                    DB::table('student_routine_daily_statuses')->updateOrInsert(
                        ['student_routine_item_id' => $item->id, 'target_date' => $targetDate->toDateString()],
                        $dailyData
                    );

                    if ($status === '未着手') {
                        continue;
                    }

                    $startedAt = $studiedAt;
                    $endedAt = $startedAt->copy()->addSeconds($studySeconds);
                    $sessionLookup = [
                        'student_routine_item_id' => $item->id,
                        'started_at' => $startedAt,
                    ];
                    $sessionId = DB::table('routine_study_sessions')->where($sessionLookup)->value('id');

                    if (!$sessionId) {
                        $sessionData = [
                            'student_id' => $item->student_id,
                            'student_routine_item_id' => $item->id,
                            'routine_content_id' => $item->routine_content_id,
                            'started_at' => $startedAt,
                            'ended_at' => $endedAt,
                            'actual_minutes' => max(1, (int) ceil($studySeconds / 60)),
                            'is_completed' => $isCompleted,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $sessionData = array_intersect_key($sessionData, array_flip($sessionColumns));
                        $sessionId = DB::table('routine_study_sessions')->insertGetId($sessionData);
                    }

                    $totalQuestions = 10;
                    $correctAnswers = 5 + (($itemIndex + $dayOffset) % 6);
                    $incorrectAnswers = $totalQuestions - $correctAnswers;
                    $resultData = [
                        'routine_study_session_id' => $sessionId,
                        'total_questions' => $totalQuestions,
                        'correct_answers' => $correctAnswers,
                        'incorrect_answers' => $incorrectAnswers,
                        'accuracy_rate' => round($correctAnswers / $totalQuestions * 100, 2),
                        'score' => $correctAnswers * 10,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $resultData = array_intersect_key($resultData, array_flip($resultColumns));

                    DB::table('routine_study_results')->updateOrInsert(
                        ['routine_study_session_id' => $sessionId],
                        $resultData
                    );
                }
            }
        });

        $this->command?->info('日次履歴・学習結果の確認用サンプルデータを追加しました。');
    }
}
