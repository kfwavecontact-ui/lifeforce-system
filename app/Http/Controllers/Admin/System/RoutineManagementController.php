<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Services\LearningMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RoutineManagementController extends Controller
{
    private array $learningStatusMap = [
        'not_created' => '未作成',
        'uncreated' => '未作成',
        'not-started' => '未作成',
        'draft' => '作成中',
        'drafting' => '作成中',
        'in_progress' => '作成中',
        'creating' => '作成中',
        'published' => '作成済',
        'done' => '作成済',
        'completed' => '作成済',
        'created' => '作成済',
        'none' => '不要',
        'unnecessary' => '不要',
        'not_required' => '不要',
        '不要' => '不要',
        '未作成' => '未作成',
        '作成中' => '作成中',
        '作成済' => '作成済',
    ];

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'items') === 'routines' ? 'routines' : 'items';

        $grades = DB::table('grades')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name']);

        return view('admin.system.routines.index', [
            'tab' => $tab,
            'items' => $tab === 'items' ? $this->routineItems($request) : collect(),
            'routines' => $tab === 'routines' ? $this->routines($request) : collect(),
            'routineItemOptions' => $tab === 'routines' ? $this->routineItemOptions() : collect(),
            'gradeOptions' => $this->gradeOptions(),
            'categoryOptions' => $this->categoryOptions(),
            'grades' => $grades,
            'difficultyOptions' => [1, 2, 3, 4, 5],
            'learningPageStatuses' => ['未作成', '作成中', '作成済', '不要'],
        ]);
    }


    public function learningPageBuilder(int $routineContentId)
    {
        abort_unless(Schema::hasTable('routine_contents'), 404);

        $item = DB::table('routine_contents')->where('id', $routineContentId)->first();
        abort_if(empty($item), 404);

        $learningPage = $this->ensureLearningPage($item);
        $dailySessions = $this->learningSessionsForBuilder((int) $learningPage->id, max(1, (int) ($item->estimated_days ?? 1)));

        $status = $this->learningPageStatusLabel($learningPage, $item);
        $difficulty = max(1, min(5, (int) ($item->difficulty ?? 1)));
        $formatMinutes = function ($minutes) {
            $minutes = (int) $minutes;
            if ($minutes < 60) {
                return $minutes . '分';
            }
            $hours = intdiv($minutes, 60);
            $rest = $minutes % 60;
            return $rest > 0 ? $hours . '時間' . $rest . '分' : $hours . '時間';
        };

        $builder = [
            'id' => $item->id,
            'learning_page_id' => $learningPage->id,
            'name' => $this->fallbackName($item->name ?? null, null, '名称未設定'),
            'description' => $item->description ?? '',
            'target_grade' => $this->fallbackName($item->target_grade ?? null, $item->target_level ?? null, '-'),
            'difficulty' => $difficulty,
            'difficulty_stars' => str_repeat('★', $difficulty) . str_repeat('☆', 5 - $difficulty),
            'estimated_days' => (int) ($item->estimated_days ?? 0),
            'daily_learning_minutes' => (int) ($item->daily_learning_minutes ?? 0),
            'daily_learning_minutes_label' => $formatMinutes($item->daily_learning_minutes ?? 0),
            'learning_page_status' => $status,
            'publication_status' => $learningPage->publication_status ?? 'unpublished',
            'publish_start_at' => ! empty($learningPage->publish_start_at) ? \Carbon\Carbon::parse($learningPage->publish_start_at)->format('Y/m/d H:i') : '',
            'publish_end_at' => ! empty($learningPage->publish_end_at) ? \Carbon\Carbon::parse($learningPage->publish_end_at)->format('Y/m/d H:i') : '',
            'search_tags' => $item->search_tags ?? '',
            'is_active' => (bool) ($item->is_active ?? true),
            'time_limit_seconds' => $learningPage->time_limit_seconds ?? null,
            'attempt_limit' => $learningPage->attempt_limit ?? null,
            'is_random' => (bool) ($learningPage->is_random ?? false),
            'allow_resume' => (bool) ($learningPage->allow_resume ?? true),
            'bgm_enabled' => (bool) ($learningPage->bgm_enabled ?? false),
            'sound_enabled' => (bool) ($learningPage->sound_enabled ?? true),
            'save_url' => route('admin.system.routines.items.learning-page.save', ['routineContentId' => $item->id]),
            'session_edit_url_template' => route('admin.system.routines.items.learning-page.sessions.edit', ['routineContentId' => $item->id, 'learningSessionId' => '__SESSION__']),
        ];

        return view('admin.system.routines.learning-page-builder', [
            'item' => $item,
            'builder' => $builder,
            'dailySessions' => $dailySessions,
            'stepTypes' => $this->learningStepTypes(),
        ]);
    }


    public function learningSessionEditor(int $routineContentId, int $learningSessionId)
    {
        abort_unless(Schema::hasTable('routine_contents') && Schema::hasTable('learning_pages') && Schema::hasTable('learning_sessions'), 404);

        $item = DB::table('routine_contents')->where('id', $routineContentId)->first();
        abort_if(empty($item), 404);

        $learningPage = $this->ensureLearningPage($item);
        $session = collect($this->learningSessionsForBuilder((int) $learningPage->id, max(1, (int) ($item->estimated_days ?? 1))))
            ->firstWhere('id', $learningSessionId);
        abort_if(empty($session), 404);

        $difficulty = max(1, min(5, (int) ($item->difficulty ?? 1)));
        $minutes = (int) ($item->daily_learning_minutes ?? 0);
        $minutesLabel = $minutes < 60 ? $minutes . '分' : intdiv($minutes, 60) . '時間' . (($minutes % 60) ? ($minutes % 60) . '分' : '');

        $builder = [
            'id' => $item->id,
            'name' => $this->fallbackName($item->name ?? null, null, '名称未設定'),
            'target_grade' => $this->fallbackName($item->target_grade ?? null, $item->target_level ?? null, '-'),
            'difficulty_stars' => str_repeat('★', $difficulty) . str_repeat('☆', 5 - $difficulty),
            'estimated_days' => (int) ($item->estimated_days ?? 0),
            'daily_learning_minutes_label' => $minutesLabel,
            'back_url' => route('admin.system.routines.items.learning-page.builder', ['routineContentId' => $item->id]),
            'save_url' => route('admin.system.routines.items.learning-page.sessions.update', ['routineContentId' => $item->id, 'learningSessionId' => $learningSessionId]),
        ];

        return view('admin.system.routines.learning-session-editor', [
            'item' => $item,
            'builder' => $builder,
            'session' => $session,
            'stepTypes' => $this->learningStepTypes(),
        ]);
    }

    public function updateLearningSession(Request $request, int $routineContentId, int $learningSessionId)
    {
        abort_unless(Schema::hasTable('routine_contents') && Schema::hasTable('learning_pages') && Schema::hasTable('learning_sessions') && Schema::hasTable('learning_steps') && Schema::hasTable('learning_step_contents'), 500, 'LLE用テーブルが未作成です。migrationを実行してください。');

        $item = DB::table('routine_contents')->where('id', $routineContentId)->first();
        abort_if(empty($item), 404);
        $page = $this->ensureLearningPage($item);
        $sessionRow = DB::table('learning_sessions')->where('id', $learningSessionId)->where('learning_page_id', $page->id)->first();
        abort_if(empty($sessionRow), 404);

        if ($request->has('payload')) {
            $decoded = json_decode((string) $request->input('payload'), true);
            abort_if(! is_array($decoded), 422, '保存データの形式が不正です。');
            $request->merge($decoded);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'show_subtitle' => ['required', 'boolean'],
            'developer_note' => ['nullable', 'string'],
            'is_published' => ['required', 'boolean'],
            'development_complete' => ['required', 'boolean'],
            'steps' => ['nullable', 'array'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.key' => ['required_with:steps', 'string', 'max:50'],
            'steps.*.label' => ['nullable', 'string', 'max:255'],
            'steps.*.content_title' => ['nullable', 'string', 'max:255'],
            'steps.*.body' => ['nullable', 'string'],
            'steps.*.media_type' => ['nullable', 'string', 'max:50'],
            'steps.*.media_path' => ['nullable', 'string', 'max:2048'],
            'steps.*.settings' => ['nullable', 'array'],
            'steps.*.questions' => ['nullable', 'array'],
            'steps.*.media_remove' => ['nullable', 'array'],
            'steps.*.media_remove.*' => ['nullable', 'boolean'],
            'files.*.*' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
            'background_image_file' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp'],
            'background_step_index' => ['nullable', 'integer', 'min:0'],
        ]);

        /** @var LearningMediaService $mediaService */
        $mediaService = app(LearningMediaService::class);
        $cleanupAfterCommit = [];
        $uploadedDuringRequest = [];

        try {
            $savedSteps = DB::transaction(function () use ($data, $request, $learningSessionId, $page, $item, $mediaService, &$cleanupAfterCommit, &$uploadedDuringRequest) {
            $steps = array_values($data['steps'] ?? []);
            DB::table('learning_sessions')->where('id', $learningSessionId)->update([
                'title' => $data['title'],
                'subtitle' => $data['subtitle'] ?? null,
                'show_subtitle' => (bool) $data['show_subtitle'],
                'developer_note' => $data['developer_note'] ?? null,
                'development_status' => ! empty($data['development_complete']) ? 'completed' : (count($steps) ? 'in_progress' : 'not_started'),
                'is_published' => (bool) $data['is_published'],
                'updated_at' => now(),
            ]);

            $existing = DB::table('learning_steps')->where('learning_session_id', $learningSessionId)->pluck('id')->map(fn($id)=>(int)$id)->all();
            $kept = [];
            $result = [];
            $backgroundUpload = $request->file('background_image_file');
            $backgroundStepIndex = max(0, (int) $request->input('background_step_index', 0));

            foreach ($steps as $index => $step) {
                $type = $step['key'];
                $candidate = isset($step['id']) ? (int) $step['id'] : 0;
                $stepId = in_array($candidate, $existing, true) ? $candidate : 0;
                $isNewStep = $stepId === 0;
                $stepPayload = [
                    'learning_session_id' => $learningSessionId,
                    'step_type' => $type,
                    'title' => $step['label'] ?? $this->learningStepTypeLabel($type),
                    'sort_order' => $index + 1,
                    'is_required' => $type === 'complete',
                    'updated_at' => now(),
                ];
                if ($stepId) {
                    DB::table('learning_steps')->where('id', $stepId)->update($stepPayload);
                } else {
                    $stepPayload['created_at'] = now();
                    $stepId = DB::table('learning_steps')->insertGetId($stepPayload);
                }
                $kept[] = $stepId;

                $settings = $step['settings'] ?? [];
                $existingContent = DB::table('learning_step_contents')->where('learning_step_id', $stepId)->first();
                $existingSettings = [];
                if ($existingContent && ! empty($existingContent->settings)) {
                    $decodedExistingSettings = json_decode((string) $existingContent->settings, true);
                    $existingSettings = is_array($decodedExistingSettings) ? $decodedExistingSettings : [];
                }

                $incomingBackground = data_get($settings, 'screen_background.image');
                $existingBackground = data_get($existingSettings, 'screen_background.image');
                $existingBackgroundKey = $this->isExternalBackgroundUrl($existingBackground)
                    ? (string) $existingBackground
                    : ($mediaService->normalizeKey($existingBackground) ?: '');
                $storedBackgroundPath = null;

                if ($backgroundUpload && $index === $backgroundStepIndex) {
                    $storedBackgroundPath = $mediaService->upload(
                        $backgroundUpload,
                        (int) $page->id,
                        $learningSessionId,
                        $stepId,
                        'background'
                    );
                    $uploadedDuringRequest[] = $storedBackgroundPath;

                    $oldBackgroundKey = $this->isExternalBackgroundUrl($existingBackground)
                        ? null
                        : $mediaService->normalizeKey($existingBackground);
                    if ($oldBackgroundKey && $oldBackgroundKey !== $storedBackgroundPath) {
                        $cleanupAfterCommit[] = $oldBackgroundKey;
                    }
                } elseif (is_string($incomingBackground) && str_starts_with($incomingBackground, 'blob:')) {
                    // Browser blob URLs are preview-only. Preserve the stored value for this step.
                    $storedBackgroundPath = $existingBackgroundKey;
                } else {
                    $incomingBackground = trim((string) $incomingBackground);

                    if ($incomingBackground === '') {
                        $storedBackgroundPath = '';
                        if (! $this->isExternalBackgroundUrl($existingBackground)) {
                            $oldBackgroundKey = $mediaService->normalizeKey($existingBackground);
                            if ($oldBackgroundKey) {
                                $cleanupAfterCommit[] = $oldBackgroundKey;
                            }
                        }
                    } elseif ($this->isExternalBackgroundUrl($incomingBackground)) {
                        $storedBackgroundPath = $incomingBackground;
                    } else {
                        // Convert temporary S3 URLs back to stable object keys.
                        $storedBackgroundPath = $mediaService->normalizeKey($incomingBackground) ?: $existingBackgroundKey;
                    }
                }

                data_set($settings, 'screen_background.image', $storedBackgroundPath ?? '');

                $mediaType = $existingContent->media_type ?? ($step['media_type'] ?? null);
                $mediaPath = $mediaService->normalizeKey($existingContent->media_path ?? ($step['media_path'] ?? null));

                $slots = [
                    'main' => [
                        'setting_key' => null,
                        'purpose' => (($step['media_type'] ?? null) === 'pdf') ? 'pdf' : ($type === 'description' ? 'description' : 'media'),
                    ],
                    'prompt' => ['setting_key' => 'prompt_image_path', 'purpose' => 'example'],
                    'answer' => ['setting_key' => 'answer_image_path', 'purpose' => 'answer'],
                    'explanation' => ['setting_key' => 'explanation_image_path', 'purpose' => 'commentary'],
                ];

                foreach ($slots as $slot => $slotDefinition) {
                    $settingKey = $slotDefinition['setting_key'];
                    $purpose = $slotDefinition['purpose'];
                    $file = $request->file("files.$index.$slot");
                    $remove = (bool) data_get($step, "media_remove.$slot", false);

                    if ($slot === 'main') {
                        $oldPath = $mediaPath;
                        $incomingPath = $mediaService->normalizeKey($step['media_path'] ?? null);
                    } else {
                        $oldPath = $mediaService->normalizeKey(data_get($existingSettings, "specific.$settingKey"));
                        $incomingPath = $mediaService->normalizeKey(data_get($settings, "specific.$settingKey"));
                    }

                    $newPath = $oldPath;

                    if ($file) {
                        $newPath = $mediaService->upload(
                            $file,
                            (int) $page->id,
                            $learningSessionId,
                            $stepId,
                            $purpose
                        );
                        $uploadedDuringRequest[] = $newPath;
                        if ($oldPath && $oldPath !== $newPath) {
                            $cleanupAfterCommit[] = $oldPath;
                        }
                    } elseif ($remove) {
                        $newPath = null;
                        if ($oldPath) {
                            $cleanupAfterCommit[] = $oldPath;
                        }
                    } elseif ($isNewStep && $incomingPath) {
                        $newPath = $mediaService->copy(
                            $incomingPath,
                            (int) $page->id,
                            $learningSessionId,
                            $stepId,
                            $purpose
                        );
                        $uploadedDuringRequest[] = $newPath;
                    } elseif (! $oldPath && $incomingPath) {
                        $newPath = $incomingPath;
                    }

                    if ($slot === 'main') {
                        $mediaPath = $newPath;
                        if (! $newPath) {
                            $mediaType = null;
                        } elseif ($file) {
                            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
                            $mediaType = $extension === 'pdf' ? 'pdf' : 'image';
                        }
                    } elseif ($settingKey) {
                        data_set($settings, "specific.$settingKey", $newPath ?: '');
                    }
                }

                // Component-builder media files are stored inside settings.components.
                $components = data_get($settings, 'components', []);
                if (is_array($components)) {
                    foreach ($components as $componentIndex => $component) {
                        if (! is_array($component) || ($component['type'] ?? null) !== 'image') {
                            continue;
                        }

                        $componentId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($component['id'] ?? $componentIndex));
                        $slot = 'component_' . $componentId;
                        $file = $request->file("files.$index.$slot");
                        $incomingPath = $mediaService->normalizeKey($component['path'] ?? null);

                        if ($file) {
                            $storedPath = $mediaService->upload(
                                $file,
                                (int) $page->id,
                                $learningSessionId,
                                $stepId,
                                'media'
                            );
                            $uploadedDuringRequest[] = $storedPath;
                            data_set($settings, "components.$componentIndex.path", $storedPath);
                            data_set($settings, "components.$componentIndex.upload_slot", null);
                        } elseif ($incomingPath && ! str_starts_with((string) ($component['path'] ?? ''), 'blob:')) {
                            data_set($settings, "components.$componentIndex.path", $incomingPath);
                        } elseif (str_starts_with((string) ($component['path'] ?? ''), 'blob:')) {
                            data_set($settings, "components.$componentIndex.path", '');
                        }
                    }
                }

                $contentPayload = [
                    'content_title' => $step['content_title'] ?? ($step['label'] ?? $this->learningStepTypeLabel($type)),
                    'body' => $step['body'] ?? null,
                    'media_type' => $mediaType,
                    'media_path' => $mediaPath,
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                    'questions' => json_encode($step['questions'] ?? [], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ];
                if ($existingContent) {
                    DB::table('learning_step_contents')->where('learning_step_id', $stepId)->update($contentPayload);
                } else {
                    $contentPayload['learning_step_id'] = $stepId;
                    $contentPayload['created_at'] = now();
                    DB::table('learning_step_contents')->insert($contentPayload);
                }
                $previewSettings = $settings;
                $storedScreenBackground = data_get($previewSettings, 'screen_background.image');
                if ($storedScreenBackground) {
                    data_set($previewSettings, 'screen_background.image', $this->screenBackgroundPreviewUrl($storedScreenBackground, $mediaService));
                }
                foreach (['prompt_image_path', 'answer_image_path', 'explanation_image_path'] as $imageSettingKey) {
                    $storedImageKey = data_get($previewSettings, "specific.$imageSettingKey");
                    if ($storedImageKey) {
                        data_set($previewSettings, "specific.$imageSettingKey", $mediaService->previewUrl($storedImageKey));
                    }
                }
                $previewComponents = data_get($previewSettings, 'components', []);
                if (is_array($previewComponents)) {
                    foreach ($previewComponents as $componentIndex => $component) {
                        if (($component['type'] ?? null) === 'image' && ! empty($component['path'])) {
                            data_set($previewSettings, "components.$componentIndex.path", $mediaService->previewUrl($component['path']));
                        }
                    }
                }
                $result[] = [
                    'id' => $stepId,
                    'media_type' => $mediaType,
                    'media_path' => $mediaService->previewUrl($mediaPath),
                    'settings' => $previewSettings,
                ];
            }

            $removed = array_values(array_diff($existing, $kept));
            foreach ($removed as $removedId) {
                $mediaService->deleteDirectory("learning-pages/{$page->id}/sessions/{$learningSessionId}/steps/{$removedId}");
            }
            if ($removed) {
                DB::table('learning_step_contents')->whereIn('learning_step_id', $removed)->delete();
                DB::table('learning_steps')->whereIn('id', $removed)->delete();
            }

            $statuses = DB::table('learning_sessions')->where('learning_page_id', $page->id)->pluck('development_status');
            $pageStatus = $statuses->isNotEmpty() && $statuses->every(fn($v) => $v === 'completed') ? 'published' : ($statuses->contains(fn($v) => $v !== 'not_started') ? 'draft' : 'not_created');
            DB::table('learning_pages')->where('id', $page->id)->update(['status' => $pageStatus, 'updated_by' => Auth::id(), 'updated_at' => now()]);
            if (Schema::hasColumn('routine_contents', 'learning_page_status')) {
                DB::table('routine_contents')->where('id', $item->id)->update(['learning_page_status' => ['published'=>'作成済','draft'=>'作成中','not_created'=>'未作成'][$pageStatus], 'updated_at' => now()]);
            }
            return $result;
            });
        } catch (\Throwable $exception) {
            foreach (array_unique($uploadedDuringRequest) as $uploadedKey) {
                $mediaService->delete($uploadedKey);
            }
            throw $exception;
        }

        foreach (array_unique(array_filter($cleanupAfterCommit)) as $oldKey) {
            $mediaService->delete($oldKey);
        }

        return response()->json(['message' => '学習' . ((int) $sessionRow->session_no) . '日目を保存しました。', 'steps' => $savedSteps]);
    }

    private function normalizeLearningMediaKey(?string $path): ?string
    {
        if (! $path) return null;

        $path = trim($path);
        if ($path === '') return null;
        if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
            return ltrim($path, '/');
        }

        $urlPath = rawurldecode((string) parse_url($path, PHP_URL_PATH));
        $key = ltrim($urlPath, '/');
        $bucket = trim((string) config('filesystems.disks.s3.bucket'));
        if ($bucket !== '' && str_starts_with($key, $bucket . '/')) {
            $key = substr($key, strlen($bucket) + 1);
        }

        $marker = 'learning-pages/';
        $position = strpos($key, $marker);
        if ($position !== false) {
            $key = substr($key, $position);
        }

        return $key !== '' ? $key : null;
    }

    private function learningMediaPreviewUrl(?string $path): string
    {
        $key = $this->normalizeLearningMediaKey($path);
        if (! $key) return '';

        try {
            return Storage::disk('s3')->temporaryUrl($key, now()->addHours(6));
        } catch (\Throwable) {
            return Storage::disk('s3')->url($key);
        }
    }

    private function deleteLearningMedia(?string $path): void
    {
        $key = $this->normalizeLearningMediaKey($path);
        if ($key) Storage::disk('s3')->delete($key);
    }

    public function saveLearningPageBuilder(Request $request, int $routineContentId)
    {
        abort_unless(Schema::hasTable('routine_contents'), 404);
        abort_unless(Schema::hasTable('learning_pages') && Schema::hasTable('learning_sessions') && Schema::hasTable('learning_steps') && Schema::hasTable('learning_step_contents'), 500, 'LLE用テーブルが未作成です。migrationを実行してください。');

        $item = DB::table('routine_contents')->where('id', $routineContentId)->first();
        abort_if(empty($item), 404);

        $data = $request->validate([
            'publication' => ['nullable', 'array'],
            'publication.status' => ['nullable', 'in:unpublished,published,stopped'],
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*.title' => ['nullable', 'string', 'max:255'],
            'sessions.*.subtitle' => ['nullable', 'string', 'max:255'],
            'sessions.*.show_subtitle' => ['nullable', 'boolean'],
            'sessions.*.memo' => ['nullable', 'string'],
            'sessions.*.developer_note' => ['nullable', 'string'],
            'sessions.*.is_published' => ['nullable', 'boolean'],
            'sessions.*.development_complete' => ['nullable', 'boolean'],
            'sessions.*.steps' => ['nullable', 'array'],
            'sessions.*.steps.*.key' => ['required_with:sessions.*.steps', 'string', 'max:50'],
            'sessions.*.steps.*.label' => ['nullable', 'string', 'max:255'],
            'sessions.*.steps.*.content_title' => ['nullable', 'string', 'max:255'],
            'sessions.*.steps.*.body' => ['nullable', 'string'],
            'sessions.*.steps.*.media_type' => ['nullable', 'string', 'max:50'],
            'sessions.*.steps.*.media_path' => ['nullable', 'string', 'max:2048'],
            'sessions.*.steps.*.settings' => ['nullable', 'array'],
            'sessions.*.steps.*.questions' => ['nullable', 'array'],
        ]);

        $learningPageId = DB::transaction(function () use ($data, $item) {
            $page = $this->ensureLearningPage($item);
            $publication = $data['publication'] ?? [];
            $previousPublicationStatus = $page->publication_status ?? 'unpublished';
            $publicationStatus = $publication['status'] ?? 'unpublished';
            $publishStartAt = $page->publish_start_at ?? null;
            $publishEndAt = $page->publish_end_at ?? null;
            $publicationChangedAt = now();

            // 公開期間は手入力させず、公開状態の遷移に合わせて自動設定します。
            // 非公開／公開停止 → 公開：公開開始を現在日時にし、過去の公開終了を消去します。
            if ($publicationStatus === 'published' && $previousPublicationStatus !== 'published') {
                $publishStartAt = $publicationChangedAt;
                $publishEndAt = null;
            }

            // 公開 → 非公開／公開停止：公開終了を現在日時にします。
            if ($previousPublicationStatus === 'published' && in_array($publicationStatus, ['unpublished', 'stopped'], true)) {
                $publishEndAt = $publicationChangedAt;
            }

            // 公開中のレコードに公開終了が残らないよう、DB側でも保証します。
            if ($publicationStatus === 'published') {
                $publishEndAt = null;
            }

            $pageUpdate = [
                'status' => $this->overallLearningPageStatus($data['sessions']),
                'publication_status' => $publicationStatus,
                'publish_start_at' => $publishStartAt,
                'publish_end_at' => $publishEndAt,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ];

            // 既存環境ごとのテーブル差異で保存が失敗しないよう、実在する列だけ更新します。
            $pageUpdate = array_filter(
                $pageUpdate,
                static fn ($value, $column) => Schema::hasColumn('learning_pages', $column),
                ARRAY_FILTER_USE_BOTH
            );

            DB::table('learning_pages')
                ->where('id', $page->id)
                ->update($pageUpdate);

            $sessionIds = DB::table('learning_sessions')->where('learning_page_id', $page->id)->pluck('id')->all();
            if (! empty($sessionIds)) {
                $stepIds = DB::table('learning_steps')->whereIn('learning_session_id', $sessionIds)->pluck('id')->all();
                if (! empty($stepIds)) {
                    DB::table('learning_step_contents')->whereIn('learning_step_id', $stepIds)->delete();
                    DB::table('learning_steps')->whereIn('id', $stepIds)->delete();
                }
                DB::table('learning_sessions')->whereIn('id', $sessionIds)->delete();
            }

            foreach (array_values($data['sessions']) as $sessionIndex => $session) {
                $sessionNo = $sessionIndex + 1;
                $steps = array_values($session['steps'] ?? []);
                $developmentStatus = ! empty($session['development_complete'])
                    ? 'completed'
                    : (count($steps) > 0 ? 'in_progress' : 'not_started');

                $sessionId = DB::table('learning_sessions')->insertGetId([
                    'learning_page_id' => $page->id,
                    'session_no' => $sessionNo,
                    'title' => $session['title'] ?? ($sessionNo . '日目'),
                    'subtitle' => $session['subtitle'] ?? null,
                    'show_subtitle' => (bool) ($session['show_subtitle'] ?? false),
                    'developer_note' => $session['developer_note'] ?? ($session['memo'] ?? null),
                    'development_status' => $developmentStatus,
                    'is_published' => (bool) ($session['is_published'] ?? false),
                    'sort_order' => $sessionNo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($steps as $stepIndex => $step) {
                    $stepType = $step['key'];
                    $stepId = DB::table('learning_steps')->insertGetId([
                        'learning_session_id' => $sessionId,
                        'step_type' => $stepType,
                        'title' => $step['label'] ?? $this->learningStepTypeLabel($stepType),
                        'sort_order' => $stepIndex + 1,
                        'is_required' => $stepType === 'complete',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('learning_step_contents')->insert([
                        'learning_step_id' => $stepId,
                        'content_title' => $step['content_title'] ?? ($step['label'] ?? $this->learningStepTypeLabel($stepType)),
                        'body' => $step['body'] ?? null,
                        'media_type' => $step['media_type'] ?? null,
                        'media_path' => $step['media_path'] ?? null,
                        'settings' => json_encode($step['settings'] ?? [], JSON_UNESCAPED_UNICODE),
                        'questions' => json_encode($step['questions'] ?? [], JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (Schema::hasColumn('routine_contents', 'learning_page_status')) {
                DB::table('routine_contents')
                    ->where('id', $item->id)
                    ->update([
                        'learning_page_status' => $this->overallLearningPageStatusLabel($data['sessions']),
                        'updated_at' => now(),
                    ]);
            }

            return $page->id;
        });

        return response()->json([
            'message' => '学習ページを保存しました。',
            'learning_page_id' => $learningPageId,
        ]);
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_grade' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'integer', 'min:1', 'max:5'],
            'estimated_days' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'daily_learning_minutes' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'learning_page_status' => ['nullable', 'string', 'max:255'],
            'search_tags' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
            'is_frequently_used' => ['nullable', 'boolean'],
        ]);

        $payload = $this->onlyExistingColumns('routine_contents', [
            'content_code' => $this->nextCode('routine_contents', 'content_code', 'CONT-'),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_grade' => $data['target_grade'] ?? null,
            'target_level' => $data['target_grade'] ?? null,
            'difficulty' => $data['difficulty'] ?? 1,
            'estimated_days' => $data['estimated_days'] ?? 0,
            'daily_learning_minutes' => $data['daily_learning_minutes'] ?? 0,
            'learning_page_status' => $data['learning_page_status'] ?? '未作成',
            'search_tags' => $data['search_tags'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('routine_contents')->insertGetId($payload);

        $this->upsertRoutineItemMark($id, $data);

        return response()->json([
            'message' => '新規ルーティンアイテムを作成しました。',
            'id' => $id,
        ]);
    }

    public function updateItem(Request $request, int $routineContentId)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_grade' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'integer', 'min:1', 'max:5'],
            'estimated_days' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'daily_learning_minutes' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'learning_page_status' => ['nullable', 'string', 'max:255'],
            'search_tags' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
            'is_frequently_used' => ['nullable', 'boolean'],
        ]);

        $payload = $this->onlyExistingColumns('routine_contents', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_grade' => $data['target_grade'] ?? null,
            'target_level' => $data['target_grade'] ?? null,
            'difficulty' => $data['difficulty'] ?? null,
            'estimated_days' => $data['estimated_days'] ?? null,
            'daily_learning_minutes' => $data['daily_learning_minutes'] ?? null,
            'learning_page_status' => $data['learning_page_status'] ?? null,
            'search_tags' => $data['search_tags'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);

        DB::table('routine_contents')->where('id', $routineContentId)->update($payload);

        if (Schema::hasTable('learning_pages') && isset($data['learning_page_status'])) {
            DB::table('learning_pages')
                ->where('routine_content_id', $routineContentId)
                ->update([
                    'status' => $data['learning_page_status'],
                    'updated_at' => now(),
                ]);
        }

        $this->upsertRoutineItemMark($routineContentId, $data);

        if (Schema::hasTable('routine_package_items')
            && Schema::hasColumn('routine_package_items', 'routine_content_id')) {

            $packageItemPayload = [];

            if (Schema::hasColumn('routine_package_items', 'item_name')) {
                $packageItemPayload['item_name'] = $data['name'];
            }

            if (Schema::hasColumn('routine_package_items', 'target_grade')) {
                $packageItemPayload['target_grade'] = $data['target_grade'] ?? null;
            }

            if (Schema::hasColumn('routine_package_items', 'target_level')) {
                $packageItemPayload['target_level'] = $data['difficulty'] ?? null;
            }

            if (Schema::hasColumn('routine_package_items', 'required_days')) {
                $packageItemPayload['required_days'] = $data['estimated_days'] ?? null;
            }

            if (Schema::hasColumn('routine_package_items', 'estimated_minutes')) {
                $packageItemPayload['estimated_minutes'] = $data['daily_learning_minutes'] ?? null;
            }

            if (Schema::hasColumn('routine_package_items', 'updated_at')) {
                $packageItemPayload['updated_at'] = now();
            }

            if (! empty($packageItemPayload)) {
                DB::table('routine_package_items')
                    ->where('routine_content_id', $routineContentId)
                    ->update($packageItemPayload);
            }
        }

        return response()->json(['message' => '保存しました。']);
    }

    public function storeRoutine(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_grade' => ['nullable', 'string', 'max:255'],
            'estimated_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $payload = $this->onlyExistingColumns('routine_packages', [
            'package_code' => $this->nextCode('routine_packages', 'package_code', 'PACK-'),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_grade' => $data['target_grade'] ?? null,
            'estimated_days' => $data['estimated_days'] ?? 0,
            'is_active' => (bool) $data['is_active'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('routine_packages')->insertGetId($payload);

        return response()->json([
            'message' => '新規ルーティンを作成しました。',
            'id' => $id,
        ]);
    }

    public function updateRoutine(Request $request, int $routinePackageId)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_grade' => ['nullable', 'string', 'max:255'],
            'target_level' => ['nullable', 'string', 'max:255'],
            'search_tags' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $payload = $this->onlyExistingColumns('routine_packages', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'target_grade' => $data['target_grade'] ?? null,
            'target_level' => $data['target_level'] ?? null,
            'search_tags' => $data['search_tags'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);

        DB::table('routine_packages')->where('id', $routinePackageId)->update($payload);

        return response()->json(['message' => '保存しました。']);
    }


    public function syncRoutinePackageItems(Request $request, int $routinePackageId)
    {
        abort_unless(Schema::hasTable('routine_packages') && Schema::hasTable('routine_package_items') && Schema::hasTable('routine_contents'), 404);
        abort_if(DB::table('routine_packages')->where('id', $routinePackageId)->doesntExist(), 404);

        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.routine_content_id' => ['required', 'integer'],
            'items.*.order_no' => ['nullable', 'integer', 'min:1'],
        ]);

        $contentIds = collect($data['items'])
            ->pluck('routine_content_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $contents = $contentIds->isEmpty()
            ? collect()
            : DB::table('routine_contents')
                ->whereIn('id', $contentIds)
                ->get()
                ->keyBy('id');

        DB::transaction(function () use ($routinePackageId, $data, $contents) {
            DB::table('routine_package_items')
                ->where('routine_package_id', $routinePackageId)
                ->delete();

            $this->resetPostgresSequenceIfNeeded('routine_package_items', 'id');

            $order = 1;
            foreach ($data['items'] as $item) {
                $contentId = (int) ($item['routine_content_id'] ?? 0);
                $content = $contents->get($contentId);
                if (! $content) {
                    continue;
                }

                $completionTypeId = $this->resolveRoutineCompletionTypeId($content);

                $payload = $this->onlyExistingColumns('routine_package_items', [
                    'routine_package_id' => $routinePackageId,
                    'routine_content_id' => $contentId,
                    'package_item_code' => 'RPI-' . $routinePackageId . '-' . $contentId . '-' . $order,
                    'code' => 'RPI-' . $routinePackageId . '-' . $contentId . '-' . $order,
                    'item_name' => $content->name ?? null,
                    'order_no' => $order,
                    'sort_order' => $order,
                    'required_days' => $content->estimated_days ?? null,
                    'estimated_minutes' => $content->daily_learning_minutes ?? null,
                    'completion_type_id' => $completionTypeId,
                    'target_grade' => $content->target_grade ?? ($content->target_level ?? null),
                    'target_level' => $content->target_level ?? null,
                    'is_required' => true,
                    'is_active' => true,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('routine_package_items')->insert($payload);
                $order++;
            }
        });

        return response()->json(['message' => 'ルーティンアイテムを保存しました。']);
    }

    public function duplicateItem(int $routineContentId)
    {
        DB::transaction(function () use ($routineContentId) {
            $source = DB::table('routine_contents')->where('id', $routineContentId)->first();
            abort_if(empty($source), 404);

            $sourceArray = collect((array) $source)
                ->reject(fn ($value, $key) => trim((string) $key) === 'id')
                ->all();

            $baseName = DB::table('routine_package_items')
                ->where('routine_content_id', $routineContentId)
                ->whereNotNull('item_name')
                ->where('item_name', '<>', '')
                ->orderBy('order_no')
                ->value('item_name');

            $displayBaseName = trim((string) ($baseName ?: $source->name ?: 'ルーティンアイテム'));
            $duplicatedName = $displayBaseName . '（複製）';

            $sourceArray['name'] = $duplicatedName;

            if (array_key_exists('content_code', $sourceArray)) {
                $sourceArray['content_code'] = $this->nextCode('routine_contents', 'content_code', 'CONT-');
            }
            if (array_key_exists('created_by', $sourceArray)) {
                $sourceArray['created_by'] = Auth::id() ?? 1;
            }
            if (array_key_exists('updated_by', $sourceArray)) {
                $sourceArray['updated_by'] = Auth::id() ?? 1;
            }
            if (array_key_exists('created_at', $sourceArray)) {
                $sourceArray['created_at'] = now();
            }
            if (array_key_exists('updated_at', $sourceArray)) {
                $sourceArray['updated_at'] = now();
            }

            DB::table('routine_contents')->insert($sourceArray);
        });

        return back()->with('status', 'ルーティンアイテムを複製しました。');
    }

    public function duplicateRoutine(int $routinePackageId)
    {
        DB::transaction(function () use ($routinePackageId) {
            $source = DB::table('routine_packages')->where('id', $routinePackageId)->first();
            abort_if(empty($source), 404);

            $oldId = $source->id;

            $sourceArray = collect((array) $source)
                ->reject(fn ($value, $key) => trim((string) $key) === 'id')
                ->all();

            $sourceArray['name'] = trim(($source->name ?? 'ルーティン') . ' コピー');

            if (array_key_exists('package_code', $sourceArray)) {
                $sourceArray['package_code'] = $this->nextCode('routine_packages', 'package_code', 'PKG-');
            }
            if (array_key_exists('created_by', $sourceArray)) {
                $sourceArray['created_by'] = Auth::id() ?? 1;
            }
            if (array_key_exists('updated_by', $sourceArray)) {
                $sourceArray['updated_by'] = Auth::id() ?? 1;
            }
            if (array_key_exists('created_at', $sourceArray)) {
                $sourceArray['created_at'] = now();
            }
            if (array_key_exists('updated_at', $sourceArray)) {
                $sourceArray['updated_at'] = now();
            }


            $newId = DB::table('routine_packages')->insertGetId($sourceArray);

            if (Schema::hasTable('routine_package_items')) {
                DB::table('routine_package_items')
                    ->where('routine_package_id', $oldId)
                    ->orderBy('order_no')
                    ->get()
                    ->each(function ($item) use ($newId) {
                        $copy = collect((array) $item)
                            ->reject(fn ($value, $key) => trim((string) $key) === 'id')
                            ->all();

                        $copy['routine_package_id'] = $newId;

                        if (array_key_exists('created_at', $copy)) {
                            $copy['created_at'] = now();
                        }
                        if (array_key_exists('updated_at', $copy)) {
                            $copy['updated_at'] = now();
                        }

                        DB::table('routine_package_items')->insert($copy);
                    });
            }
        });

        return back()->with('status', 'ルーティンを複製しました。');
    }

    public function toggleFavorite(int $routineContentId)
    {
        $userId = Auth::id() ?? 1;

        $mark = DB::table('user_routine_item_marks')
            ->where('user_id', $userId)
            ->where('routine_content_id', $routineContentId)
            ->first();

        $value = !($mark->is_favorite ?? false);

        DB::table('user_routine_item_marks')->updateOrInsert(
            [
                'user_id' => $userId,
                'routine_content_id' => $routineContentId,
            ],
            [
                'is_favorite' => $value,
                'is_frequently_used' => $mark->is_frequently_used ?? false,
                'updated_at' => now(),
                'created_at' => $mark ? $mark->created_at : now(),
            ]
        );

        return response()->json([
            'success' => true,
            'value' => $value,
        ]);
    }

    public function toggleFrequentlyUsed(int $routineContentId)
    {
        $userId = Auth::id() ?? 1;

        $mark = DB::table('user_routine_item_marks')
            ->where('user_id', $userId)
            ->where('routine_content_id', $routineContentId)
            ->first();

        $value = !($mark->is_frequently_used ?? false);

        DB::table('user_routine_item_marks')->updateOrInsert(
            [
                'user_id' => $userId,
                'routine_content_id' => $routineContentId,
            ],
            [
                'is_favorite' => $mark->is_favorite ?? false,
                'is_frequently_used' => $value,
                'updated_at' => now(),
                'created_at' => $mark ? $mark->created_at : now(),
            ]
        );

        return response()->json([
            'success' => true,
            'value' => $value,
        ]);
    }



    private function toBooleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 't', 'yes', 'on'], true);
        }

        return false;
    }

    private function upsertRoutineItemMark(int $routineContentId, array $data): array
    {
        if (! Schema::hasTable('user_routine_item_marks')) {
            return [
                'is_favorite' => false,
                'is_frequently_used' => false,
            ];
        }

        $userId = Auth::id() ?? 1;

        $existing = DB::table('user_routine_item_marks')
            ->where('user_id', $userId)
            ->where('routine_content_id', $routineContentId)
            ->first();

        $isFavorite = array_key_exists('is_favorite', $data)
            ? $this->toBooleanValue($data['is_favorite'])
            : $this->toBooleanValue($existing->is_favorite ?? false);

        $isFrequentlyUsed = array_key_exists('is_frequently_used', $data)
            ? $this->toBooleanValue($data['is_frequently_used'])
            : $this->toBooleanValue($existing->is_frequently_used ?? false);

        DB::table('user_routine_item_marks')->updateOrInsert(
            [
                'user_id' => $userId,
                'routine_content_id' => $routineContentId,
            ],
            [
                'is_favorite' => $isFavorite,
                'is_frequently_used' => $isFrequentlyUsed,
                'created_at' => $existing->created_at ?? now(),
                'updated_at' => now(),
            ]
        );

        return [
            'is_favorite' => $isFavorite,
            'is_frequently_used' => $isFrequentlyUsed,
        ];
    }

    private function routineItems(Request $request)
    {
        $userId = Auth::id() ?? 1;
        $hasMarks = Schema::hasTable('user_routine_item_marks');
        $hasLearningPages = Schema::hasTable('learning_pages');
        $hasCreator = Schema::hasTable('users') && Schema::hasColumn('routine_contents', 'created_by');
        $hasUpdater = Schema::hasTable('users') && Schema::hasColumn('routine_contents', 'updated_by');

        $query = DB::table('routine_contents as rc');

        if ($hasMarks) {
            $query->leftJoin('user_routine_item_marks as marks', function ($join) use ($userId) {
                $join->on('marks.routine_content_id', '=', 'rc.id')->where('marks.user_id', '=', $userId);
            });
        }
        if ($hasLearningPages) {
            $query->leftJoin('learning_pages as lp', 'lp.routine_content_id', '=', 'rc.id');
        }
        if ($hasCreator) {
            $query->leftJoin('users as creator', 'creator.id', '=', 'rc.created_by');
        }
        if ($hasUpdater) {
            $query->leftJoin('users as updater', 'updater.id', '=', 'rc.updated_by');
        }

        $query->select('rc.*')
            ->addSelect([
                'is_favorite' => $hasMarks ? DB::raw('CASE WHEN marks.is_favorite IS TRUE THEN 1 ELSE 0 END') : DB::raw('0'),
                'is_frequently_used' => $hasMarks ? DB::raw('CASE WHEN marks.is_frequently_used IS TRUE THEN 1 ELSE 0 END') : DB::raw('0'),
                'created_by_name' => $hasCreator ? DB::raw('creator.name as created_by_name') : DB::raw('NULL as created_by_name'),
                'updated_by_name' => $hasUpdater ? DB::raw('updater.name as updated_by_name') : DB::raw('NULL as updated_by_name'),
                'learning_page_status_label' => $this->learningStatusSelectSql($hasLearningPages),
            ]);

        if (Schema::hasTable('routine_package_items')) {
            $query->selectSub(function ($q) {
                $q->from('routine_package_items')->selectRaw('COUNT(*)')->whereColumn('routine_package_items.routine_content_id', 'rc.id');
            }, 'used_routine_count')
            ->selectSub(function ($q) {
                $q->from('routine_package_items')
                    ->selectRaw("MIN(NULLIF(item_name, ''))")
                    ->whereColumn('routine_package_items.routine_content_id', 'rc.id');
            }, 'display_item_name')
            ->selectSub(function ($q) {
                $q->from('routine_package_items')
                    ->selectRaw("STRING_AGG(DISTINCT NULLIF(target_grade, ''), '、')")
                    ->whereColumn('routine_package_items.routine_content_id', 'rc.id');
            }, 'package_item_target_grades');
        } else {
            $query->addSelect([
                'used_routine_count' => DB::raw('0'),
                'display_item_name' => DB::raw('NULL'),
                'package_item_target_grades' => DB::raw('NULL'),
            ]);
        }

        if (Schema::hasTable('student_routine_items') && Schema::hasTable('student_routines')) {
            $query->selectSub(function ($q) {
                $q->from('student_routine_items')->selectRaw('COUNT(DISTINCT student_routines.student_id)')
                    ->join('student_routines', 'student_routines.id', '=', 'student_routine_items.student_routine_id')
                    ->whereColumn('student_routine_items.routine_content_id', 'rc.id');
            }, 'assigned_student_count');
        } else {
            $query->addSelect(['assigned_student_count' => DB::raw('0')]);
        }

        if (Schema::hasTable('routine_study_sessions')) {
            $query->selectSub(function ($q) {
                $q->from('routine_study_sessions')->selectRaw('COUNT(*)')->whereColumn('routine_study_sessions.routine_content_id', 'rc.id');
            }, 'past_study_count')
            ->selectSub(function ($q) {
                $q->from('routine_study_sessions')->selectRaw('MAX(ended_at)')->whereColumn('routine_study_sessions.routine_content_id', 'rc.id');
            }, 'last_studied_at');
        } else {
            $query->addSelect(['past_study_count' => DB::raw('0'), 'last_studied_at' => DB::raw('NULL')]);
        }

        $keyword = trim((string) $request->query('item_keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                if (is_numeric($keyword)) {
                    $q->orWhere('rc.id', (int) $keyword);
                }
                $q->orWhere('rc.name', 'like', "%{$keyword}%")
                    ->orWhere('rc.description', 'like', "%{$keyword}%");
            });
        }

        $this->applyColumnFilter($query, 'rc', 'category_name', $request->query('item_category'));
        $this->applyGradeFilter($query, 'rc', $request->query('item_grade'));
        $this->applyColumnFilter($query, 'rc', 'difficulty', $request->query('item_difficulty'));

        $learningStatus = $request->query('item_learning_status');
        if ($learningStatus && $learningStatus !== 'all') {
            $rawStatuses = array_keys(array_filter($this->learningStatusMap, fn ($label) => $label === $learningStatus));
            $query->where(function ($q) use ($learningStatus, $rawStatuses, $hasLearningPages) {
                if (Schema::hasColumn('routine_contents', 'learning_page_status')) {
                    $q->orWhereIn('rc.learning_page_status', array_merge([$learningStatus], $rawStatuses));
                }
                if ($hasLearningPages && Schema::hasColumn('learning_pages', 'status')) {
                    $q->orWhereIn('lp.status', array_merge([$learningStatus], $rawStatuses));
                }
            });
        }

        if ($hasMarks && $request->query('item_favorite') === 'yes') {
            $query->where('marks.is_favorite', true);
        }
        if ($hasMarks && $request->query('item_frequent') === 'yes') {
            $query->where('marks.is_frequently_used', true);
        }
        if ($request->query('item_active') === 'active') {
            $query->where('rc.is_active', true);
        } elseif ($request->query('item_active') === 'inactive') {
            $query->where('rc.is_active', false);
        }

        $items = $query->orderByRaw($this->orderColumnSql('rc', 'sort_order'))
            ->orderBy('rc.id')
            ->paginate(20)
            ->withQueryString();

        $userId = Auth::id() ?? 1;

        $marks = DB::table('user_routine_item_marks')
            ->where('user_id', $userId)
            ->whereIn('routine_content_id', $items->pluck('id'))
            ->get()
            ->keyBy('routine_content_id');

        $contentIds = $items->pluck('id')->all();
        $usedRoutineNames = $this->usedRoutineNamesByContentIds($contentIds);
        $items->getCollection()->transform(function ($item) use ($usedRoutineNames, $marks) {
            $item->display_name = $this->fallbackName(
                $item->name ?? null,
                $item->display_item_name ?? null,
                '名称未設定'
            );
            $item->display_grade = $this->fallbackName($item->target_grade ?? null, $item->package_item_target_grades ?? null, '-');
            $item->learning_page_status_label = $this->normalizeLearningStatus($item->learning_page_status_label ?? $item->learning_page_status ?? null);
            $item->learning_url = route('admin.system.routines.items.learning-page.builder', ['routineContentId' => $item->id]);
            $item->used_routine_names = $usedRoutineNames[$item->id] ?? [];
            $item->search_tags = $item->search_tags ?? '';
            $mark = $marks[$item->id] ?? null;

            $item->is_favorite = (bool) ($mark->is_favorite ?? false);
            $item->is_frequently_used = (bool) ($mark->is_frequently_used ?? false);
            return $item;
        });

        return $items;
    }

    private function routines(Request $request)
    {
        $itemSummary = Schema::hasTable('routine_package_items')
            ? DB::table('routine_package_items')
                ->select('routine_package_id')
                ->selectRaw('COUNT(*) as item_count')
                ->selectRaw('COALESCE(SUM(COALESCE(required_days, 0) * COALESCE(estimated_minutes, 0)), 0) as total_learning_minutes')
                ->groupBy('routine_package_id')
            : null;

        $studentSummary = Schema::hasTable('student_routines')
            ? DB::table('student_routines')
                ->select('routine_package_id')
                ->selectRaw('COUNT(*) as student_routine_count')
                ->selectRaw('COUNT(DISTINCT student_id) as assigned_student_count')
                ->groupBy('routine_package_id')
            : null;

        $hasPackageCreator = Schema::hasTable('users') && Schema::hasColumn('routine_packages', 'created_by');
        $hasPackageUpdater = Schema::hasTable('users') && Schema::hasColumn('routine_packages', 'updated_by');

        $query = DB::table('routine_packages as rp');
        if ($itemSummary) {
            $query->leftJoinSub($itemSummary, 'item_summary', fn ($join) => $join->on('item_summary.routine_package_id', '=', 'rp.id'));
        }
        if ($studentSummary) {
            $query->leftJoinSub($studentSummary, 'student_summary', fn ($join) => $join->on('student_summary.routine_package_id', '=', 'rp.id'));
        }
        if ($hasPackageCreator) {
            $query->leftJoin('users as creator', 'creator.id', '=', 'rp.created_by');
        }
        if ($hasPackageUpdater) {
            $query->leftJoin('users as updater', 'updater.id', '=', 'rp.updated_by');
        }

        $query->select('rp.*')->addSelect([
            $hasPackageCreator ? DB::raw('creator.name as created_by_name') : DB::raw('NULL as created_by_name'),
            $hasPackageUpdater ? DB::raw('updater.name as updated_by_name') : DB::raw('NULL as updated_by_name'),
            'item_count' => $itemSummary ? DB::raw('COALESCE(item_summary.item_count, 0)') : DB::raw('0'),
            'total_learning_minutes' => $itemSummary ? DB::raw('COALESCE(item_summary.total_learning_minutes, 0)') : DB::raw('0'),
            'assigned_student_count' => $studentSummary ? DB::raw('COALESCE(student_summary.assigned_student_count, 0)') : DB::raw('0'),
            'student_routine_count' => $studentSummary ? DB::raw('COALESCE(student_summary.student_routine_count, 0)') : DB::raw('0'),
        ]);

        $keyword = trim((string) $request->query('routine_keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                if (is_numeric($keyword)) {
                    $q->orWhere('rp.id', (int) $keyword);
                }
                $q->orWhere('rp.name', 'like', "%{$keyword}%")
                    ->orWhere('rp.description', 'like', "%{$keyword}%");
            });
        }

        $this->applyColumnFilter($query, 'rp', 'target_grade', $request->query('routine_grade'));
        $routineDifficulty = $request->query('routine_difficulty');
        if ($routineDifficulty && $routineDifficulty !== 'all' && Schema::hasColumn('routine_packages', 'target_level')) {
            $numericDifficulty = array_search($routineDifficulty, [1 => '非常に低い', 2 => '低い', 3 => '標準', 4 => '高い', 5 => '非常に高い'], true);
            $query->where(function ($q) use ($routineDifficulty, $numericDifficulty) {
                $q->where('rp.target_level', $routineDifficulty);
                if ($numericDifficulty !== false) {
                    $q->orWhere('rp.target_level', (string) $numericDifficulty);
                }
            });
        }
        if ($request->query('routine_active') === 'active') {
            $query->where('rp.is_active', true);
        } elseif ($request->query('routine_active') === 'inactive') {
            $query->where('rp.is_active', false);
        }
        if ($studentSummary) {
            if ($request->query('usage') === 'used') {
                $query->whereRaw('COALESCE(student_summary.student_routine_count, 0) > 0');
            } elseif ($request->query('usage') === 'unused') {
                $query->whereRaw('COALESCE(student_summary.student_routine_count, 0) = 0');
            }
            if (($min = $request->query('assigned_min')) !== null && $min !== '') {
                $query->whereRaw('COALESCE(student_summary.assigned_student_count, 0) >= ?', [(int) $min]);
            }
            if (($max = $request->query('assigned_max')) !== null && $max !== '') {
                $query->whereRaw('COALESCE(student_summary.assigned_student_count, 0) <= ?', [(int) $max]);
            }
        }

        $routines = $query->orderByRaw($this->orderColumnSql('rp', 'sort_order'))
            ->orderBy('rp.id')
            ->paginate(20)
            ->withQueryString();

        $ids = $routines->pluck('id')->all();
        $items = $this->routinePackageItemsByPackageIds($ids);
        $routines->getCollection()->transform(function ($routine) use ($items) {
            $routine->items = $items[$routine->id] ?? collect();

            // 一覧の「ルーティンアイテム数」と「総学習時間」は、
            // 有効な routine_contents に紐づく構成アイテムのみを運用上の件数として扱う。
            // 無効アイテムはDB上の構成としては残すが、一覧集計・ホバー・時間内訳からは除外する。
            $routine->active_items = $routine->items->filter(function ($item) {
                return (bool) ($item->content_is_active ?? true);
            })->values();
            $routine->item_count = $routine->active_items->count();
            $routine->total_learning_minutes = $routine->active_items->sum(function ($item) {
                return (int) ($item->required_days ?? 0) * (int) ($item->estimated_minutes ?? 0);
            });

            $routine->display_name = $this->routinePackageDisplayName($routine);
            $routine->display_grade = $routine->target_grade ?? '-';
            $routine->display_level = $this->normalizeDifficultyLabel($routine->target_level ?? null);
            $routine->search_tags = $routine->search_tags ?? '';
            return $routine;
        });

        return $routines;
    }


    private function routineItemOptions()
    {
        if (! Schema::hasTable('routine_contents')) {
            return collect();
        }

        $query = DB::table('routine_contents')
            ->orderBy(Schema::hasColumn('routine_contents', 'sort_order') ? 'sort_order' : 'id')
            ->orderBy('id');

        if (Schema::hasColumn('routine_contents', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->get()->map(function ($item) {
            $item->display_name = $this->fallbackName($item->name ?? null, $item->content_code ?? null, '名称未設定');
            $item->display_grade = $this->fallbackName($item->target_grade ?? null, $item->target_level ?? null, '-');
            $item->difficulty_value = (int) ($item->difficulty ?? 1);
            $item->daily_learning_minutes = (int) ($item->daily_learning_minutes ?? 0);
            $item->estimated_days = (int) ($item->estimated_days ?? 0);
            $item->search_text = trim(implode(' ', array_filter([
                (string) ($item->id ?? ''),
                $item->display_name,
                $item->description ?? null,
                $item->display_grade,
                $item->category_name ?? null,
                $item->search_tags ?? null,
            ])));
            return $item;
        });
    }

    private function usedRoutineNamesByContentIds(array $contentIds): array
    {
        if (empty($contentIds) || ! Schema::hasTable('routine_package_items') || ! Schema::hasTable('routine_packages')) {
            return [];
        }

        return DB::table('routine_package_items as rpi')
            ->join('routine_packages as rp', 'rp.id', '=', 'rpi.routine_package_id')
            ->whereIn('rpi.routine_content_id', $contentIds)
            ->orderBy('rp.id')
            ->get(['rpi.routine_content_id', 'rp.name', 'rp.package_code'])
            ->groupBy('routine_content_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => $this->fallbackName($row->name, $row->package_code, '名称未設定'))->values()->all())
            ->all();
    }

    private function routinePackageItemsByPackageIds(array $ids)
    {
        if (empty($ids) || ! Schema::hasTable('routine_package_items')) {
            return collect();
        }

        $query = DB::table('routine_package_items as rpi');
        if (Schema::hasTable('routine_contents')) {
            $query->leftJoin('routine_contents as rc', 'rc.id', '=', 'rpi.routine_content_id');
        }

        return $query->whereIn('rpi.routine_package_id', $ids)
            ->orderBy('rpi.routine_package_id')
            ->orderBy('rpi.order_no')
            ->get([
                'rpi.id',
                'rpi.routine_package_id',
                'rpi.routine_content_id',
                'rpi.item_name',
                'rpi.required_days',
                'rpi.estimated_minutes',
                DB::raw(Schema::hasTable('routine_contents') ? 'rc.name as content_name' : 'NULL as content_name'),
                DB::raw(Schema::hasTable('routine_contents') ? 'rc.target_grade as content_target_grade' : 'NULL as content_target_grade'),
                DB::raw(Schema::hasTable('routine_contents') ? 'rc.difficulty as content_difficulty' : 'NULL as content_difficulty'),
                DB::raw(Schema::hasTable('routine_contents') && Schema::hasColumn('routine_contents', 'is_active') ? 'rc.is_active as content_is_active' : 'TRUE as content_is_active'),
            ])
            ->groupBy('routine_package_id');
    }

    private function learningStepTypes(): array
    {
        return [
            ['key' => 'description', 'label' => '説明', 'description' => '学習前の説明・目的・注意事項を表示します。'],
            ['key' => 'example', 'label' => '例題', 'description' => '操作方法やサンプル問題を表示します。'],
            ['key' => 'video', 'label' => '動画', 'description' => '動画視聴と視聴完了条件を設定します。'],
            ['key' => 'material', 'label' => '教材', 'description' => 'PDF・画像・外部教材を表示します。'],
            ['key' => 'question', 'label' => '問題', 'description' => '問題を連続実行します。Version0.1の中心ステップです。'],
            ['key' => 'survey', 'label' => 'アンケート', 'description' => '学習後の自己評価や感想を取得します。'],
            ['key' => 'result', 'label' => '結果', 'description' => '正答数・正答率・学習時間などを表示します。'],
            ['key' => 'complete', 'label' => '完了', 'description' => '学習履歴保存・達成判定につながる必須ステップです。'],
        ];
    }

    private function learningStepTypeLabel(string $key): string
    {
        foreach ($this->learningStepTypes() as $type) {
            if ($type['key'] === $key) {
                return $type['label'];
            }
        }
        return 'ステップ';
    }

    private function ensureLearningPage(object $item): object
    {
        abort_unless(Schema::hasTable('learning_pages'), 500, 'learning_pagesテーブルが未作成です。migrationを実行してください。');

        $query = DB::table('learning_pages');
        if (Schema::hasColumn('learning_pages', 'routine_content_id')) {
            $query->where('routine_content_id', $item->id);
        } else {
            $query->where('routine_item_id', $item->id);
        }

        $page = $query->first();
        if ($page) {
            $this->ensureDefaultLearningSessions((int) $page->id, max(1, (int) ($item->estimated_days ?? 1)));
            return DB::table('learning_pages')->where('id', $page->id)->first();
        }

        $payload = [
            'status' => 'draft',
            'publication_status' => 'unpublished',
            'publish_start_at' => null,
            'publish_end_at' => null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // migration適用状況に左右されないよう、実在する列だけ初期登録します。
        $payload = array_filter(
            $payload,
            static fn ($value, $column) => Schema::hasColumn('learning_pages', $column),
            ARRAY_FILTER_USE_BOTH
        );
        if (Schema::hasColumn('learning_pages', 'routine_content_id')) {
            $payload['routine_content_id'] = $item->id;
        } else {
            $payload['routine_item_id'] = $item->id;
        }

        $pageId = DB::table('learning_pages')->insertGetId($payload);
        $this->ensureDefaultLearningSessions($pageId, max(1, (int) ($item->estimated_days ?? 1)));

        return DB::table('learning_pages')->where('id', $pageId)->first();
    }

    private function ensureDefaultLearningSessions(int $learningPageId, int $estimatedDays): void
    {
        if (! Schema::hasTable('learning_sessions')) {
            return;
        }

        $existingCount = DB::table('learning_sessions')->where('learning_page_id', $learningPageId)->count();
        if ($existingCount > 0) {
            return;
        }

        for ($i = 1; $i <= max(1, $estimatedDays); $i++) {
            DB::table('learning_sessions')->insert([
                'learning_page_id' => $learningPageId,
                'session_no' => $i,
                'title' => $i . '日目',
                'subtitle' => null,
                'show_subtitle' => false,
                'developer_note' => null,
                'development_status' => 'not_started',
                'is_published' => false,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function learningSessionsForBuilder(int $learningPageId, int $estimatedDays): array
    {
        $sessions = DB::table('learning_sessions')
            ->where('learning_page_id', $learningPageId)
            ->orderBy('sort_order')
            ->orderBy('session_no')
            ->get();

        if ($sessions->isEmpty()) {
            $this->ensureDefaultLearningSessions($learningPageId, $estimatedDays);
            $sessions = DB::table('learning_sessions')
                ->where('learning_page_id', $learningPageId)
                ->orderBy('sort_order')
                ->orderBy('session_no')
                ->get();
        }

        $sessionIds = $sessions->pluck('id')->all();
        $stepsBySession = collect();
        $contentsByStep = collect();
        if (! empty($sessionIds)) {
            $allSteps = DB::table('learning_steps')
                ->whereIn('learning_session_id', $sessionIds)
                ->orderBy('sort_order')
                ->get();
            $stepsBySession = $allSteps->groupBy('learning_session_id');
            $stepIds = $allSteps->pluck('id')->all();
            if (! empty($stepIds)) {
                $contentsByStep = DB::table('learning_step_contents')
                    ->whereIn('learning_step_id', $stepIds)
                    ->get()
                    ->keyBy('learning_step_id');
            }
        }

        /** @var LearningMediaService $mediaService */
        $mediaService = app(LearningMediaService::class);

        return $sessions->values()->map(function ($session, $index) use ($stepsBySession, $contentsByStep, $mediaService) {
            $steps = ($stepsBySession[$session->id] ?? collect())->map(function ($step) use ($contentsByStep, $mediaService) {
                $content = $contentsByStep[$step->id] ?? null;
                $decode = static function ($value, $default) {
                    if (is_array($value)) return $value;
                    if ($value === null || $value === '') return $default;
                    $decoded = json_decode($value, true);
                    return is_array($decoded) ? $decoded : $default;
                };
                $settings = $decode($content->settings ?? null, []);
                $storedScreenBackground = data_get($settings, 'screen_background.image');
                if ($storedScreenBackground) {
                    data_set($settings, 'screen_background.image', $this->screenBackgroundPreviewUrl($storedScreenBackground, $mediaService));
                }
                foreach (['prompt_image_path', 'answer_image_path', 'explanation_image_path'] as $imageSettingKey) {
                    $storedImageKey = data_get($settings, "specific.$imageSettingKey");
                    if ($storedImageKey) {
                        data_set($settings, "specific.$imageSettingKey", $mediaService->previewUrl($storedImageKey));
                    }
                }
                return [
                    'id' => $step->id,
                    'key' => $step->step_type,
                    'label' => $step->title ?: $this->learningStepTypeLabel($step->step_type),
                    'content_title' => $content->content_title ?? '',
                    'body' => $content->body ?? '',
                    'media_type' => $content->media_type ?? '',
                    'media_path' => $this->learningMediaPreviewUrl($content->media_path ?? null),
                    'settings' => $settings,
                    'questions' => $decode($content->questions ?? null, []),
                ];
            })->values()->all();

            return [
                'id' => $session->id,
                'session_no' => (int) ($session->session_no ?: ($index + 1)),
                'title' => (empty($session->title) || preg_match('/^(?:第\d+回学習|学習\d+日目|\d+日目)$/u', (string) $session->title))
                    ? (($index + 1) . '日目')
                    : $session->title,
                'subtitle' => $session->subtitle ?? '',
                'show_subtitle' => (bool) $session->show_subtitle,
                'memo' => $session->developer_note ?? '',
                'developer_note' => $session->developer_note ?? '',
                'development_status' => $this->developmentStatusLabel($session->development_status ?? 'not_started'),
                'is_published' => (bool) $session->is_published,
                'development_complete' => ($session->development_status ?? null) === 'completed',
                'steps' => $steps,
            ];
        })->all();
    }

    private function developmentStatusLabel(?string $status): string
    {
        return [
            'not_started' => '未着手',
            'in_progress' => '作成中',
            'completed' => '完成',
            '未着手' => '未着手',
            '作成中' => '作成中',
            '完成' => '完成',
        ][$status ?? 'not_started'] ?? '未着手';
    }

    private function learningPageStatusLabel(object $learningPage, object $item): string
    {
        $status = $learningPage->status ?? ($item->learning_page_status ?? null);
        return [
            'draft' => '作成中',
            'published' => '作成済',
            'stopped' => '不要',
            'not_created' => '未作成',
        ][$status] ?? $this->normalizeLearningStatus($status);
    }

    private function overallLearningPageStatus(array $sessions): string
    {
        $total = count($sessions);
        $completed = collect($sessions)->filter(fn ($session) => ! empty($session['development_complete']))->count();
        if ($total > 0 && $completed === $total) {
            return 'published';
        }
        return collect($sessions)->contains(fn ($session) => ! empty($session['steps'])) ? 'draft' : 'not_created';
    }

    private function overallLearningPageStatusLabel(array $sessions): string
    {
        $status = $this->overallLearningPageStatus($sessions);
        return ['published' => '作成済', 'draft' => '作成中', 'not_created' => '未作成'][$status] ?? '作成中';
    }

    private function learningStatusSelectSql(bool $hasLearningPages)
    {
        if (Schema::hasColumn('routine_contents', 'learning_page_status') && $hasLearningPages && Schema::hasColumn('learning_pages', 'status')) {
            return DB::raw('COALESCE(rc.learning_page_status, lp.status)');
        }
        if (Schema::hasColumn('routine_contents', 'learning_page_status')) {
            return DB::raw('rc.learning_page_status');
        }
        if ($hasLearningPages && Schema::hasColumn('learning_pages', 'status')) {
            return DB::raw('lp.status');
        }
        return DB::raw("'未作成'");
    }

    private function normalizeLearningStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return '未作成';
        }
        return $this->learningStatusMap[$status] ?? $status;
    }

    private function normalizeDifficultyLabel(?string $value): string
    {
        if (in_array($value, ['非常に低い', '低い', '標準', '高い', '非常に高い'], true)) {
            return $value;
        }
        if (is_numeric($value)) {
            return [1 => '非常に低い', 2 => '低い', 3 => '標準', 4 => '高い', 5 => '非常に高い'][(int) $value] ?? '標準';
        }
        return '標準';
    }

    private function applyColumnFilter($query, string $alias, string $column, $value): void
    {
        if ($value === null || $value === '' || $value === 'all') {
            return;
        }
        $table = $alias === 'rc' ? 'routine_contents' : 'routine_packages';
        if (! Schema::hasColumn($table, $column)) {
            return;
        }
        $query->where("{$alias}.{$column}", $value);
    }

    private function applyGradeFilter($query, string $alias, $value): void
    {
        if ($value === null || $value === '' || $value === 'all') {
            return;
        }
        $query->where(function ($q) use ($alias, $value) {
            if (Schema::hasColumn('routine_contents', 'target_grade')) {
                $q->orWhere("{$alias}.target_grade", $value);
            }
            if (Schema::hasColumn('routine_contents', 'target_level')) {
                $q->orWhere("{$alias}.target_level", $value);
            }
        });
    }

    private function orderColumnSql(string $alias, string $column): string
    {
        $table = $alias === 'rc' ? 'routine_contents' : 'routine_packages';
        return Schema::hasColumn($table, $column) ? "{$alias}.{$column} ASC NULLS LAST" : "{$alias}.id ASC";
    }


    private function resolveRoutineCompletionTypeId(object $content): ?int
    {
        if (! Schema::hasColumn('routine_package_items', 'completion_type_id')) {
            return null;
        }

        if (isset($content->completion_type_id) && (int) $content->completion_type_id > 0) {
            return (int) $content->completion_type_id;
        }

        if (Schema::hasTable('routine_completion_types')) {
            $query = DB::table('routine_completion_types')->orderBy('id');

            if (Schema::hasColumn('routine_completion_types', 'is_active')) {
                $query->where('is_active', true);
            }

            $id = $query->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return 1;
    }

    private function resetPostgresSequenceIfNeeded(string $table, string $column = 'id'): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', '{$column}'), COALESCE((SELECT MAX({$column}) FROM {$table}), 1), true)");
        } catch (\Throwable $e) {
            // シーケンスが存在しない環境では何もしない。
        }
    }

    private function onlyExistingColumns(string $table, array $payload): array
    {
        return collect($payload)->filter(fn ($value, $column) => Schema::hasColumn($table, $column))->all();
    }

    private function nextCode(string $table, string $column, string $prefix): string
    {
        $maxId = (int) DB::table($table)->max('id') + 1;
        return $prefix . str_pad((string) $maxId, 4, '0', STR_PAD_LEFT);
    }

    private function gradeOptions(): array
    {
        $values = collect();
        foreach ([['routine_contents', 'target_grade'], ['routine_contents', 'target_level'], ['routine_packages', 'target_grade']] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $values = $values->merge(DB::table($table)->whereNotNull($column)->where($column, '<>', '')->distinct()->pluck($column));
            }
        }
        return $values->unique()->sort()->values()->all();
    }

    private function categoryOptions(): array
    {
        if (! Schema::hasTable('routine_contents') || ! Schema::hasColumn('routine_contents', 'category_name')) {
            return [];
        }
        return DB::table('routine_contents')
            ->whereNotNull('category_name')
            ->where('category_name', '<>', '')
            ->distinct()
            ->orderBy('category_name')
            ->pluck('category_name')
            ->all();
    }


    private function routinePackageDisplayName(object $routine): string
    {
        $name = trim((string) ($routine->name ?? ''));
        $code = trim((string) ($routine->package_code ?? ''));

        if ($name !== '' && $name !== $code && ! $this->looksLikeRoutinePackageCode($name)) {
            return $name;
        }

        $knownNames = [
            'RP-BASIC-001' => '朝の脳活ルーティン（基礎）',
            'RP-THINK-001' => '思考力トレーニングルーティン',
        ];

        if ($code !== '' && isset($knownNames[$code])) {
            return $knownNames[$code];
        }

        if ($name !== '' && isset($knownNames[$name])) {
            return $knownNames[$name];
        }

        $sourceCode = $code !== '' ? $code : $name;
        if ($sourceCode !== '') {
            $label = $this->routinePackageCodeLabel($sourceCode);
            if ($label !== null) {
                return $label;
            }
        }

        return $this->fallbackName($name !== '' ? $name : null, $code !== '' ? $code : null, '名称未設定');
    }

    private function looksLikeRoutinePackageCode(string $value): bool
    {
        return (bool) preg_match('/^RP-[A-Z0-9_-]+$/i', trim($value));
    }

    private function routinePackageCodeLabel(string $code): ?string
    {
        $normalized = strtoupper(trim($code));
        if (! preg_match('/^RP-([A-Z0-9]+)-?/', $normalized, $matches)) {
            return null;
        }

        return match ($matches[1]) {
            'BASIC' => '基礎ルーティン',
            'THINK' => '思考力ルーティン',
            'MEM' => '記憶力ルーティン',
            'FOCUS' => '集中力ルーティン',
            'LOGIC' => '論理思考ルーティン',
            default => null,
        };
    }


    private function isExternalBackgroundUrl(?string $path): bool
    {
        if (! is_string($path)) {
            return false;
        }

        $path = trim($path);
        if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
            return false;
        }

        $urlPath = rawurldecode((string) parse_url($path, PHP_URL_PATH));

        return ! str_contains(ltrim($urlPath, '/'), 'learning-pages/');
    }

    private function screenBackgroundPreviewUrl(?string $path, LearningMediaService $mediaService): string
    {
        if (! $path) {
            return '';
        }

        return $this->isExternalBackgroundUrl($path)
            ? trim((string) $path)
            : $mediaService->previewUrl($path);
    }

    private function fallbackName(?string $name, ?string $code, string $fallback): string
    {
        $name = trim((string) $name);
        if ($name !== '') {
            return $name;
        }
        $code = trim((string) $code);
        return $code !== '' ? $code : $fallback;
    }
}
