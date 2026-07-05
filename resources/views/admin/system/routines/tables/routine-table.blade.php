<section class="routine-table-card">
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
            </colgroup>
            <thead>
                <tr>
                    <th>ID</th>
                    <th class="txt-left">ルーティン名</th>
                    <th>対象学年</th>
                    <th>難易度</th>
                    <th>アイテム数</th>
                    <th>総学習時間（自動集計）</th>
                    <th>使用中フラグ</th>
                    <th>割当生徒数</th>
                    <th>検索タグ</th>
                    <th>有効</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            @forelse($routines as $routine)
                @php
                    $grade = $routine->display_grade ?? '-';
                    $difficulty = $routine->display_level ?? '標準';
                    $difficultyStars = $difficultyTextToStar[$difficulty] ?? 3;
                    $tags = $tagList($routine->search_tags ?? '');
                    $name = $routine->display_name ?? ($routine->name ?? '名称未設定');
                    $itemNames = collect($routine->items ?? [])->map(fn($v) => $v->item_name ?: $v->content_name)->filter()->values();
                    $limitedItemNames = $itemNames->take(10);
                    $remainingItemCount = max(0, $itemNames->count() - 10);
                    $detailItems = collect($routine->items ?? [])->map(fn($v) => [
                        'name' => $v->item_name ?: $v->content_name,
                        'required_days' => (int)($v->required_days ?? 0),
                        'estimated_minutes' => (int)($v->estimated_minutes ?? 0),
                    ])->values()->all();
                    $detail = [
                        'type' => 'routine',
                        'id' => $routine->id,
                        'name' => $name,
                        'description' => $routine->description ?? '',
                        'target_grade' => $grade,
                        'target_level' => $difficulty,
                        'item_count' => (int)($routine->item_count ?? 0),
                        'total_learning_minutes' => (int)($routine->total_learning_minutes ?? 0),
                        'student_routine_count' => (int)($routine->student_routine_count ?? 0),
                        'assigned_student_count' => (int)($routine->assigned_student_count ?? 0),
                        'search_tags' => $routine->search_tags ?? '',
                        'is_active' => (bool)($routine->is_active ?? true),
                        'items' => $detailItems,
                        'created_by_name' => $routine->created_by_name ?? ($routine->created_by ?? null),
                        'updated_by_name' => $routine->updated_by_name ?? ($routine->updated_by ?? null),
                    ];
                @endphp
                <tr data-edit-row data-id="{{ $routine->id }}">
                    <td class="txt-center">{{ $routine->id }}</td>
                    <td class="txt-left name-cell">
                        <span class="display-value name-hover" title="{{ $routine->description ?? '' }}">{{ $name }}</span>
                        <input class="edit-field" name="name" value="{{ $name }}">
                        <input type="hidden" name="description" value="{{ $routine->description ?? '' }}">
                    </td>
                    <td>
                        <span class="display-value">{{ $grade }}</span>
                        <input class="edit-field" name="target_grade" value="{{ $grade === '-' ? '' : $grade }}">
                    </td>
                    <td>
                        <span class="display-value stars">{{ $stars($difficultyStars) }}</span>
                        <select class="edit-field" name="target_level">
                            @foreach(['非常に低い','低い','標準','高い','非常に高い'] as $option)
                                <option value="{{ $option }}" @selected($difficulty===$option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <span class="item-count-tooltip">
                            {{ $routine->item_count ?? 0 }}
                            <span class="tooltip-box">
                                <b>構成アイテム（最大10件まで表示）</b>
                                @forelse($limitedItemNames as $itemName)
                                    <em>{{ $loop->iteration }}. {{ $itemName }}</em>
                                @empty
                                    <em>構成アイテムがありません。</em>
                                @endforelse
                                @if($remainingItemCount > 0)
                                    <small>他 {{ $remainingItemCount }} 件</small>
                                @endif
                            </span>
                        </span>
                    </td>
                    <td>{{ $formatMinutes($routine->total_learning_minutes ?? 0) }}</td>
                    <td><span class="active-badge {{ ($routine->student_routine_count ?? 0) > 0 ? 'active' : 'inactive' }}">{{ ($routine->student_routine_count ?? 0) > 0 ? '使用中' : '未使用' }}</span></td>
                    <td>{{ $routine->assigned_student_count ?? 0 }}</td>
                    <td>
                        <span class="display-value tag-list">
                            @forelse($tags as $tag)
                                <b class="tag">{{ $tag }}</b>
                            @empty
                                <span class="muted">-</span>
                            @endforelse
                        </span>
                        <input class="edit-field" name="search_tags" value="{{ $routine->search_tags ?? '' }}">
                    </td>
                    <td>
                        <span class="display-value active-badge {{ ($routine->is_active ?? true) ? 'active' : 'inactive' }}">{{ ($routine->is_active ?? true) ? '有効' : '無効' }}</span>
                        <select class="edit-field edit-small" name="is_active">
                            <option value="1" @selected($routine->is_active ?? true)>有効</option>
                            <option value="0" @selected(!($routine->is_active ?? true))>無効</option>
                        </select>
                    </td>
                    <td>
                        <div class="row-actions action-view">
                            <button type="button" data-detail="{{ $json($detail) }}">詳細</button>
                            <button type="button" data-edit-trigger="{{ $routine->id }}">編集</button>
                            <form method="POST" action="{{ route('admin.system.routines.packages.duplicate', $routine->id) }}" data-duplicate-form>
                                @csrf
                                <button type="submit">複製</button>
                            </form>
                        </div>
                        <div class="row-actions action-edit">
                            <button type="button" data-save>保存</button>
                            <button type="button" data-cancel>キャンセル</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="empty">ルーティンがありません。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="routine-pagination"><span>全 {{ $routines->total() }} 件</span>{{ $routines->links() }}</div>
</section>
