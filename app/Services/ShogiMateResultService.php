<?php

namespace App\Services;

use App\Models\RoutineStudyResult;
use App\Models\RoutineStudySession;
use App\Models\Student;
use App\Models\StudentRoutineItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShogiMateResultService
{
    public function save(Student $student, array $payload): RoutineStudySession
    {
        $completionKey = (string) $payload['completion_id'];

        $existing = RoutineStudySession::query()
            ->with('result')
            ->where('student_id', $student->id)
            ->where('completion_key', $completionKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $routineItem = $this->resolveRoutineItem($student, $payload, $context);
        $routineContentId = (int) $routineItem->routine_content_id;
        $learningSessionId = $this->positiveId(
            $context['learning_session_id'] ?? $payload['learning_session_id'] ?? null
        );

        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        $rows = is_array($result['problems'] ?? null) ? $result['problems'] : [];

        $totalQuestions = max(1, (int) ($result['total'] ?? count($rows) ?: 1));
        $correctAnswers = min($totalQuestions, max(0, (int) ($result['completed'] ?? $totalQuestions)));
        $incorrectAnswers = max(0, $totalQuestions - $correctAnswers);
        $accuracyRate = round(($correctAnswers / $totalQuestions) * 100, 2);
        $elapsedMs = max(0, (int) ($payload['total_elapsed_ms'] ?? $result['total_elapsed_ms'] ?? 0));
        $hintCount = max(0, (int) ($result['total_hint_count'] ?? collect($rows)->sum('hint_count')));
        $usedAnswer = collect($rows)->contains(
            fn ($row) => (bool) Arr::get($row, 'result.used_answer', Arr::get($row, 'used_answer', false))
        );
        $completedAt = CarbonImmutable::parse($payload['completed_at'] ?? now());
        $startedAt = $completedAt->subMilliseconds($elapsedMs);

        return DB::transaction(function () use (
            $student,
            $routineItem,
            $routineContentId,
            $learningSessionId,
            $payload,
            $context,
            $completionKey,
            $result,
            $totalQuestions,
            $correctAnswers,
            $incorrectAnswers,
            $accuracyRate,
            $elapsedMs,
            $hintCount,
            $usedAnswer,
            $startedAt,
            $completedAt
        ) {
            $session = RoutineStudySession::create([
                'student_id' => $student->id,
                'student_routine_item_id' => $routineItem->id,
                'routine_content_id' => $routineContentId,
                'learning_session_id' => $learningSessionId,
                'component_type' => 'shogi_problem_set',
                'completion_key' => $completionKey,
                'started_at' => $startedAt,
                'ended_at' => $completedAt,
                'actual_minutes' => (int) ceil($elapsedMs / 60000),
                'is_completed' => true,
                'last_activity_at' => $completedAt,
                'context' => array_merge($context, [
                    'problem_set_id' => $payload['problem_set_id'] ?? null,
                    'component_type' => 'shogi_problem_set',
                ]),
            ]);

            RoutineStudyResult::create([
                'routine_study_session_id' => $session->id,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'incorrect_answers' => $incorrectAnswers,
                'accuracy_rate' => $accuracyRate,
                'score' => min(100, max(0, (int) ($payload['score'] ?? $result['average_score'] ?? 0))),
                'rank' => mb_substr((string) ($payload['rank'] ?? $result['rank'] ?? ''), 0, 10),
                'stars' => min(3, max(0, (int) ($payload['stars'] ?? $result['stars'] ?? 0))),
                'total_elapsed_ms' => $elapsedMs,
                'hint_count' => $hintCount,
                'used_answer' => $usedAnswer,
                'details' => $result,
            ]);

            if (! $routineItem->completed_at) {
                $routineItem->forceFill(['completed_at' => $completedAt])->save();
            }

            return $session->load('result');
        });
    }

    private function positiveId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function resolveRoutineItem(Student $student, array $payload, array $context): StudentRoutineItem
    {
        $id = (int) ($context['student_routine_item_id'] ?? $payload['student_routine_item_id'] ?? 0);

        if ($id <= 0) {
            throw ValidationException::withMessages([
                'context.student_routine_item_id' => '生徒ルーティン項目を確認できません。',
            ]);
        }

        $item = StudentRoutineItem::query()
            ->whereKey($id)
            ->whereHas('routine', fn ($query) => $query->where('student_id', $student->id))
            ->first();

        $requestedRoutineContentId = $this->positiveId(
            $context['routine_content_id'] ?? $payload['routine_content_id'] ?? null
        );

        if ($item && $requestedRoutineContentId && (int) $item->routine_content_id !== $requestedRoutineContentId) {
            $item = null;
        }

        if (! $item) {
            throw ValidationException::withMessages([
                'context.student_routine_item_id' => 'この生徒のルーティン項目を確認できません。',
            ]);
        }

        return $item;
    }
}
