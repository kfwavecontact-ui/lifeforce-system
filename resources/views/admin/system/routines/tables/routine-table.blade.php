<section class="routine-table-card">
    <button type="button" class="routine-create-button" data-create-routine>
        ＋ 新規ルーティン
    </button>
    <div class="routine-table-scroll">
        <table class="routine-table routine-package-table">
            <colgroup>
                <col class="c-id">
                <col class="c-routine-name">
                <col class="c-grade">
                <col class="c-difficulty">
                <col class="c-item-count">
                <col class="c-total-time">
                <col class="c-using">
                <col class="c-assigned">
                <col class="c-tags">
                <col class="c-active">
                <col class="c-actions">
                <col class="c-package-items">
            </colgroup>
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-name">ルーティン名</th>
                    <th>対象学年</th>
                    <th>難易度</th>
                    <th>ルーティンアイテム数</th>
                    <th>総学習時間（自動集計）</th>
                    <th>使用中フラグ</th>
                    <th>割当生徒数</th>
                    <th>検索タグ</th>
                    <th>有効</th>
                    <th class="col-action">操作</th>
                    <th>ルーティンアイテム</th>
                </tr>
            </thead>
            <tbody>
                <tr data-edit-row data-create-routine-row hidden>
                    <td class="txt-center">NEW</td>

                    <td class="txt-left name-cell">
                        <input class="edit-field" name="name" value="" placeholder="ルーティン名">
                        <textarea class="edit-field edit-description" name="description" placeholder="説明"></textarea>
                    </td>

                    <td>
                        <input class="edit-field" name="target_grade" value="" placeholder="対象学年">
                    </td>

                    <td>
                        <select class="edit-field edit-small" name="difficulty">
                            @for($i=1;$i<=5;$i++)
                                <option value="{{ $i }}" @selected($i === 1)>{{ $i }}</option>
                            @endfor
                        </select>
                    </td>

                    <td>0</td>
                    <td>0分</td>
                    <td><span class="status">未使用</span></td>
                    <td>0</td>

                    <td>
                        <input class="edit-field" name="search_tags" value="" placeholder="検索タグ">
                    </td>

                    <td>
                        <select class="edit-field edit-small" name="is_active">
                            <option value="1" selected>有効</option>
                            <option value="0">無効</option>
                        </select>
                    </td>

                    <td>
                        <div class="row-actions action-edit">
                            <button type="button" data-save>保存</button>
                            <button type="button" data-cancel-create-routine>キャンセル</button>
                        </div>
                    </td>
                    <td>-</td>
                </tr>
            @forelse($routines as $routine)
                @php
                    $grade = $routine->display_grade ?? '-';
                    $difficulty = $routine->display_level ?? '標準';
                    $difficultyStars = $difficultyTextToStar[$difficulty] ?? 3;
                    $tags = $tagList($routine->search_tags ?? '');
                    $routineItems = collect($routine->items ?? []);
                    $activeRoutineItems = collect($routine->active_items ?? [])->values();
                    $itemNames = $activeRoutineItems->map(fn($v) => $v->item_name ?: $v->content_name)->filter()->values();
                    $limitedItemNames = $itemNames->take(10);
                    $remainingItemCount = max(0, $itemNames->count() - 10);
                    $itemCountValue = (int)($routine->item_count ?? $activeRoutineItems->count());
                    $tooltipLines = $limitedItemNames->map(fn($name, $index) => ($index + 1) . '. ' . $name)->values()->all();
                    if ($remainingItemCount > 0) {
                        $tooltipLines[] = '他' . $remainingItemCount . '件';
                    }
                    $itemCountTitle = $itemCountValue > 0
                        ? implode("&#10;", array_map('e', $tooltipLines))
                        : '有効なルーティンアイテムがありません。';
                    $totalLearningMinutes = (int)($routine->total_learning_minutes ?? 0);
                    $totalBreakdownItems = $activeRoutineItems->map(function ($v) {
                        $name = $v->item_name ?: $v->content_name;
                        $minutes = (int)($v->required_days ?? 0) * (int)($v->estimated_minutes ?? 0);
                        return ['name' => $name, 'minutes' => $minutes];
                    })->filter(fn($v) => !empty($v['name']))->values();
                    $detailItems = $routineItems->map(fn($v) => [
                        'routine_content_id' => (int)($v->routine_content_id ?? 0),
                        'name' => $v->item_name ?: $v->content_name,
                        'item_name' => $v->item_name ?: $v->content_name,
                        'content_name' => $v->content_name ?? null,
                        'target_grade' => $v->content_target_grade ?? '-',
                        'difficulty' => (int)($v->content_difficulty ?? 1),
                        'required_days' => (int)($v->required_days ?? 0),
                        'estimated_minutes' => (int)($v->estimated_minutes ?? 0),
                        'is_active' => (bool)($v->content_is_active ?? true),
                    ])->values()->all();
                    $detail = [
                        'type' => 'routine',
                        'id' => $routine->id,
                        'name' => $routine->display_name ?? ($routine->name ?? '名称未設定'),
                        'description' => $routine->description ?? '',
                        'target_grade' => $grade,
                        'target_level' => $difficulty,
                        'item_count' => (int)($routine->item_count ?? $routineItems->count()),
                        'total_learning_minutes' => (int)($routine->total_learning_minutes ?? 0),
                        'student_routine_count' => (int)($routine->student_routine_count ?? 0),
                        'assigned_student_count' => (int)($routine->assigned_student_count ?? 0),
                        'search_tags' => $routine->search_tags ?? '',
                        'is_active' => (bool)($routine->is_active ?? true),
                        'items' => $detailItems,
                        'created_by_name' => $routine->created_by_name ?? ($routine->created_by ?? null),
                        'updated_by_name' => $routine->updated_by_name ?? ($routine->updated_by ?? null),
                        'created_at' => $routine->created_at ?? null,
                        'updated_at' => $routine->updated_at ?? null,
                    ];
                @endphp
                <tr class="display-row" data-display-row data-id="{{ $routine->id }}">
                    <td class="col-id">{{ $routine->id }}</td>
                    <td class="col-name txt-left"><span class="name-hover" title="{{ $routine->description ?? '' }}">{{ $routine->display_name ?? ($routine->name ?? '名称未設定') }}</span></td>
                    <td>{{ $grade }}</td>
                    <td><span class="stars">{{ $stars($difficultyStars) }}</span></td>
                    <td class="routine-item-count-cell">
                        <span class="routine-item-count-badge {{ $itemCountValue > 0 ? 'has-items' : 'is-empty' }}"
                              title="{!! $itemCountTitle !!}">
                            {{ $itemCountValue }}
                            <span class="tooltip-box">
                                <b>ルーティンアイテム（上位10件）</b>
                                @forelse($limitedItemNames as $name)
                                    <em>{{ $loop->iteration }}. {{ $name }}</em>
                                @empty
                                    <em>ルーティンアイテムがありません。</em>
                                @endforelse
                                @if($remainingItemCount > 0)
                                    <small>他{{ $remainingItemCount }}件</small>
                                @endif
                            </span>
                        </span>
                    </td>
                    <td class="routine-total-time-cell">
                        <button type="button" class="routine-total-time-button" aria-label="総学習時間の内訳を表示">
                            {{ $formatMinutes($totalLearningMinutes) }}
                            <span class="total-time-tooltip">
                                <b>総学習時間の内訳</b>
                                @forelse($totalBreakdownItems as $breakdownItem)
                                    <em>{{ $loop->iteration }}. {{ $breakdownItem['name'] }}：{{ $formatMinutes($breakdownItem['minutes']) }}</em>
                                @empty
                                    <em>有効なルーティンアイテムがありません。</em>
                                @endforelse
                                <small>合計 {{ $formatMinutes($totalLearningMinutes) }}</small>
                            </span>
                        </button>
                    </td>
                    <td><span class="active-badge {{ ($routine->student_routine_count ?? 0) > 0 ? 'active' : 'inactive' }}">{{ ($routine->student_routine_count ?? 0) > 0 ? '使用中' : '未使用' }}</span></td>
                    <td>{{ $routine->assigned_student_count ?? 0 }}</td>
                    <td>@foreach($tags as $tag)<b class="tag">{{ $tag }}</b>@endforeach</td>
                    <td><span class="active-badge {{ ($routine->is_active ?? true) ? 'active' : 'inactive' }}">{{ ($routine->is_active ?? true) ? '有効' : '無効' }}</span></td>
                    <td class="col-action">
                        <div class="row-actions">
                            <button type="button" data-detail="{{ $json($detail) }}">詳細</button>
                            <button type="button" data-edit-trigger="{{ $routine->id }}">編集</button>
                            <form method="POST" action="{{ route('admin.system.routines.packages.duplicate', $routine->id) }}" data-duplicate-form>@csrf<button type="submit">複製</button></form>
                        </div>
                    </td>
                    <td>
                        <button type="button"
                                class="routine-item-add-button {{ $itemCountValue > 0 ? 'has-items' : 'is-empty' }}"
                                data-manage-package-items
                                data-routine-id="{{ $routine->id }}"
                                data-routine-name="{{ e($routine->display_name ?? ($routine->name ?? '名称未設定')) }}"
                                data-current-items="{{ e(json_encode($detailItems, JSON_UNESCAPED_UNICODE)) }}">
                            ＋追加
                        </button>
                    </td>
                </tr>
                <tr class="edit-row" data-edit-row data-id="{{ $routine->id }}" hidden>
                    <td class="col-id">{{ $routine->id }}</td>
                    <td class="col-name txt-left">
                        <input class="edit-field full" name="name" value="{{ $routine->display_name ?? ($routine->name ?? '') }}">
                        <textarea class="edit-field full edit-description" name="description" placeholder="説明">{{ $routine->description ?? '' }}</textarea>
                    </td>
                    <td><input class="edit-field short" name="target_grade" value="{{ $grade === '-' ? '' : $grade }}"></td>
                    <td>
                        <select class="edit-field medium" name="target_level">
                            @for($i=1;$i<=5;$i++)
                                <option value="{{ $i }}" @selected($difficultyStars === $i)>{{ $i }}</option>
                            @endfor
                        </select>
                    </td>
                    <td>{{ $routine->item_count ?? $routineItems->count() }}</td>
                    <td>{{ $formatMinutes($routine->total_learning_minutes ?? 0) }}</td>
                    <td><span class="active-badge {{ ($routine->student_routine_count ?? 0) > 0 ? 'active' : 'inactive' }}">{{ ($routine->student_routine_count ?? 0) > 0 ? '使用中' : '未使用' }}</span></td>
                    <td>{{ $routine->assigned_student_count ?? 0 }}</td>
                    <td><input class="edit-field full" name="search_tags" value="{{ $routine->search_tags ?? '' }}"></td>
                    <td><select class="edit-field short" name="is_active"><option value="1" @selected($routine->is_active ?? true)>有効</option><option value="0" @selected(!($routine->is_active ?? true))>無効</option></select></td>
                    <td class="col-action"><div class="row-actions"><button type="button" data-save>保存</button><button type="button" data-cancel>キャンセル</button></div></td>
                    <td>-</td>
                </tr>
            @empty
                <tr><td colspan="12" class="empty">ルーティンがありません。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="routine-pagination"><span>全 {{ $routines->total() }} 件</span>{{ $routines->links() }}</div>
</section>
