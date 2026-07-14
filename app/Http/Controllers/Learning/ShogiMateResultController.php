<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Services\ShogiMateResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShogiMateResultController extends Controller
{
    public function store(Request $request, ShogiMateResultService $service): JsonResponse
    {
        $student = $request->user()?->student;
        abort_unless($student, 403, '生徒アカウントでログインしてください。');

        $payload = $request->validate([
            'completion_id' => ['required', 'uuid'],
            'component_type' => ['required', Rule::in(['shogi_problem_set'])],
            'problem_set_id' => ['nullable', 'string', 'max:255'],
            'completed' => ['required', 'accepted'],
            'completed_at' => ['required', 'date'],
            'score' => ['required', 'integer', 'between:0,100'],
            'rank' => ['nullable', 'string', 'max:10'],
            'stars' => ['required', 'integer', 'between:0,3'],
            'total_elapsed_ms' => ['required', 'integer', 'min:0', 'max:86400000'],
            'learning_content_id' => ['nullable', 'integer', 'exists:learning_contents,id'],
            'routine_content_id' => ['nullable', 'integer', 'exists:routine_contents,id'],
            'learning_session_id' => ['nullable', 'integer', 'exists:learning_sessions,id'],
            'student_routine_item_id' => ['nullable', 'integer'],
            'context' => ['nullable', 'array'],
            'context.learning_content_id' => ['nullable', 'integer', 'exists:learning_contents,id'],
            'context.routine_content_id' => ['nullable', 'integer', 'exists:routine_contents,id'],
            'context.learning_session_id' => ['nullable', 'integer', 'exists:learning_sessions,id'],
            'context.student_routine_item_id' => ['nullable', 'integer'],
            'result' => ['required', 'array'],
            'result.total' => ['required', 'integer', 'min:1', 'max:500'],
            'result.completed' => ['required', 'integer', 'min:0', 'max:500'],
            'result.problems' => ['nullable', 'array', 'max:500'],
        ]);

        $session = $service->save($student, $payload);

        return response()->json([
            'ok' => true,
            'study_session_id' => $session->id,
            'study_result_id' => $session->result?->id,
            'student_routine_item_id' => $session->student_routine_item_id,
            'completed_at' => $session->ended_at?->toIso8601String(),
        ]);
    }
}
