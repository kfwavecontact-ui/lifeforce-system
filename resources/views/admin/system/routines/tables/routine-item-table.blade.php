<section class="routine-table-card">
    <div class="routine-table-header">
        <button type="button" class="routine-create-button" data-create-item>
            ＋ 新規ルーティンアイテム
        </button>
    </div>

    <div class="routine-table-scroll">
        <table class="routine-table routine-item-table">
            <colgroup>
                <col class="c-id">
                <col class="c-item-name">
                <col class="c-page-status">
                <col class="c-grade">
                <col class="c-difficulty">
                <col class="c-days">
                <col class="c-minutes">
                <col class="c-used">
                <col class="c-assigned">
                <col class="c-count">
                <col class="c-tags">
                <col class="c-mark">
                <col class="c-mark">
                <col class="c-active">
                <col class="c-actions">
                <col class="c-develop">
            </colgroup>
            <thead>
                <tr>
                    <th>ID</th>
                    <th class="txt-left">ルーティンアイテム名</th>
                    <th>学習ページ状態</th>
                    <th>対象学年</th>
                    <th>難易度</th>
                    <th>学習想定日数</th>
                    <th>1日の推奨学習時間</th>
                    <th>使用中ルーティン数</th>
                    <th>割当生徒数</th>
                    <th>過去の学習回数</th>
                    <th>検索タグ</th>
                    <th>お気に入り</th>
                    <th>よく使う</th>
                    <th>有効</th>
                    <th>操作</th>
                    <th>開発</th>
                </tr>
            </thead>
            <tbody>


            <tr data-edit-row data-create-row hidden>
                <td class="txt-center">NEW</td>
                <td class="txt-left name-cell">
                    <input class="edit-field" name="name" value="" placeholder="ルーティンアイテム名">
                    <textarea class="edit-field edit-description" name="description" placeholder="説明"></textarea>
                </td>
                <td>
                    <select class="edit-field edit-status" name="learning_page_status">
                        @foreach(['未作成', '作成中', '作成済', '不要'] as $statusOption)
                            <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                        @endforeach
                    </select>
                </td>
                <td><input class="edit-field" name="target_grade" value=""></td>
                <td>
                    <select class="edit-field edit-small" name="difficulty">
                        @for($i=1;$i<=5;$i++)
                            <option value="{{ $i }}" @selected($i === 1)>{{ $i }}</option>
                        @endfor
                    </select>
                </td>
                <td><input class="edit-field edit-small" name="estimated_days" type="number" min="0" value="0"></td>
                <td><input class="edit-field edit-small" name="daily_learning_minutes" type="number" min="0" value="0"></td>
                <td>0</td>
                <td>0</td>
                <td>0</td>
                <td><input class="edit-field" name="search_tags" value=""></td>
                <td>
                    <input class="edit-field" type="hidden" name="is_favorite" value="0">
                    <label class="edit-field mark-check"><input type="checkbox" name="is_favorite" value="1"><span>ON</span></label>
                </td>
                <td>
                    <input class="edit-field" type="hidden" name="is_frequently_used" value="0">
                    <label class="edit-field mark-check"><input type="checkbox" name="is_frequently_used" value="1"><span>ON</span></label>
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
                        <button type="button" data-cancel-create>キャンセル</button>
                    </div>
                </td>
                <td class="develop-cell">-</td>
            </tr>



            @forelse($items as $item)
                @php
                    $status = $item->learning_page_status_label ?? '未作成';
                    $grade = $item->display_grade ?? '-';
                    $tags = $tagList($item->search_tags ?? '');
                    $name = $item->display_name ?? ($item->name ?? '名称未設定');
                    $toMarkBool = function ($value) {
                        if (is_bool($value)) return $value;
                        if (is_int($value)) return $value === 1;
                        if (is_string($value)) return in_array(strtolower(trim($value)), ['1', 'true', 't', 'yes', 'on'], true);
                        return false;
                    };
                    $isFavorite = $toMarkBool($item->is_favorite ?? false);
                    $isFrequentlyUsed = $toMarkBool($item->is_frequently_used ?? false);
                    $detail = [
                        'type' => 'item',
                        'id' => $item->id,
                        'name' => $name,
                        'description' => $item->description ?? '',
                        'target_grade' => $grade,
                        'difficulty' => (int)($item->difficulty ?? 1),
                        'estimated_days' => (int)($item->estimated_days ?? 0),
                        'daily_learning_minutes' => (int)($item->daily_learning_minutes ?? 0),
                        'learning_page_status' => $status,
                        'search_tags' => $item->search_tags ?? '',
                        'used_routine_count' => (int)($item->used_routine_count ?? 0),
                        'used_routine_names' => $item->used_routine_names ?? [],
                        'assigned_student_count' => (int)($item->assigned_student_count ?? 0),
                        'past_study_count' => (int)($item->past_study_count ?? 0),
                        'last_studied_at' => $item->last_studied_at ?? null,
                        'created_at' => $item->created_at ?? null,
                        'created_by_name' => $item->created_by_name ?? ($item->created_by ?? null),
                        'updated_at' => $item->updated_at ?? null,
                        'updated_by_name' => $item->updated_by_name ?? ($item->updated_by ?? null),
                        'is_active' => (bool)($item->is_active ?? true),
                        'is_favorite' => $isFavorite,
                        'is_frequently_used' => $isFrequentlyUsed,
                    ];
                @endphp
                <tr data-edit-row data-id="{{ $item->id }}">
                    <td class="txt-center">{{ $item->id }}</td>
                    <td class="txt-left name-cell">
                        <span class="display-value name-hover" title="{{ $item->description ?? '' }}">{{ $name }}</span>
                        <input class="edit-field" name="name" value="{{ $name }}">
                        <textarea class="edit-field edit-description" name="description" placeholder="説明">{{ $item->description ?? '' }}</textarea>
                    </td>
                    <td>
                        <span class="display-value status {{ $statusLabels[$status] ?? 'not-started' }}">{{ $status }}</span>
                        <select class="edit-field edit-status" name="learning_page_status">
                            @foreach(['未作成', '作成中', '作成済', '不要'] as $statusOption)
                                <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ $statusOption }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <span class="display-value">{{ $grade }}</span>
                        <input class="edit-field" name="target_grade" value="{{ $grade === '-' ? '' : $grade }}">
                    </td>
                    <td>
                        <span class="display-value stars">{{ $stars($item->difficulty ?? 1) }}</span>
                        <select class="edit-field edit-small" name="difficulty">
                            @for($i=1;$i<=5;$i++)
                                <option value="{{ $i }}" @selected((int)($item->difficulty ?? 1)===$i)>{{ $i }}</option>
                            @endfor
                        </select>
                    </td>
                    <td>
                        <span class="display-value">{{ $item->estimated_days ?? 0 }}日</span>
                        <input class="edit-field edit-small" name="estimated_days" type="number" min="0" value="{{ $item->estimated_days ?? 0 }}">
                    </td>
                    <td>
                        <span class="display-value">{{ $item->daily_learning_minutes ?? 0 }}分</span>
                        <input class="edit-field edit-small" name="daily_learning_minutes" type="number" min="0" value="{{ $item->daily_learning_minutes ?? 0 }}">
                    </td>
                    <td>{{ $item->used_routine_count ?? 0 }}</td>
                    <td>{{ $item->assigned_student_count ?? 0 }}</td>
                    <td>{{ $item->past_study_count ?? 0 }}</td>
                    <td>
                        <span class="display-value tag-list">
                            @forelse($tags as $tag)
                                <b class="tag">{{ $tag }}</b>
                            @empty
                                <span class="muted">-</span>
                            @endforelse
                        </span>
                        <input class="edit-field" name="search_tags" value="{{ $item->search_tags ?? '' }}">
                    </td>
                    <td>
                        <span
                            class="display-value mark {{ $isFavorite ? 'on' : '' }}"
                            data-favorite-toggle
                            data-id="{{ $item->id }}"
                            style="cursor:pointer;"
                            title="お気に入り">
                            ★
                        </span>
                        <input class="edit-field" type="hidden" name="is_favorite" value="0">
                        <label class="edit-field mark-check">
                            <input type="checkbox" name="is_favorite" value="1" @checked($isFavorite)>
                            <span>ON</span>
                        </label>
                    </td>
                    <td>
                        <span
                            class="display-value pin {{ $isFrequentlyUsed ? 'on' : '' }}"
                            data-frequent-toggle
                            data-id="{{ $item->id }}"
                            style="cursor:pointer;"
                            title="よく使う">
                            📌
                        </span>
                        <input class="edit-field" type="hidden" name="is_frequently_used" value="0">
                        <label class="edit-field mark-check">
                            <input type="checkbox" name="is_frequently_used" value="1" @checked($isFrequentlyUsed)>
                            <span>ON</span>
                        </label>
                    </td>
                    <td>
                        <span class="display-value active-badge {{ ($item->is_active ?? true) ? 'active' : 'inactive' }}">{{ ($item->is_active ?? true) ? '有効' : '無効' }}</span>
                        <select class="edit-field edit-small" name="is_active">
                            <option value="1" @selected($item->is_active ?? true)>有効</option>
                            <option value="0" @selected(!($item->is_active ?? true))>無効</option>
                        </select>
                    </td>
                    <td>
                        <div class="row-actions action-view">
                            <button type="button" data-detail="{{ $json($detail) }}">詳細</button>
                            <button type="button" data-edit-trigger="{{ $item->id }}">編集</button>
                            <form method="POST" action="{{ route('admin.system.routines.items.duplicate', $item->id) }}" data-duplicate-form>
                                @csrf
                                <button type="submit">複製</button>
                            </form>
                        </div>
                        <div class="row-actions action-edit">
                            <button type="button" data-save>保存</button>
                            <button type="button" data-cancel>キャンセル</button>
                        </div>
                    </td>
                    <td class="develop-cell">
                        <a class="develop-button" href="{{ $item->learning_url ?? '#' }}">開発</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="16" class="empty">ルーティンアイテムがありません。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('admin.system.routines.partials.pagination', [
        'paginator' => $items,
    ])
</section>
