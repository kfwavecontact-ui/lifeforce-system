<div class="routine-modal routine-items-modal" id="routinePackageItemsModal" aria-hidden="true">
    <div class="routine-modal-backdrop" data-close-package-items-modal></div>
    <div class="routine-modal-panel routine-items-modal-panel" role="dialog" aria-modal="true" aria-labelledby="routinePackageItemsModalTitle">
        <div class="routine-modal-header">
            <div>
                <h2 id="routinePackageItemsModalTitle">ルーティンアイテム管理</h2>
                <p id="routinePackageItemsModalSubtitle">対象ルーティンを選択してください。</p>
            </div>
            <button type="button" class="routine-modal-close" data-close-package-items-modal>×</button>
        </div>

        <div class="package-items-toolbar">
            <div class="package-item-search-box">
                <label for="packageItemSearchInput">ルーティンアイテム検索</label>
                <input id="packageItemSearchInput" type="search" placeholder="ID・ルーティンアイテム名・説明・検索タグで検索" data-package-item-search>
            </div>
            <div class="package-items-count">
                選択中 <strong data-selected-item-count>0</strong> 件
            </div>
        </div>

        <div class="package-items-layout">
            <section class="package-items-box">
                <h3>追加できるルーティンアイテム</h3>
                <div class="package-item-candidate-list" data-package-item-candidates>
                    @forelse($routineItemOptions ?? collect() as $item)
                        <button type="button"
                                class="package-item-candidate"
                                data-package-item-candidate
                                data-id="{{ $item->id }}"
                                data-name="{{ e($item->display_name) }}"
                                data-grade="{{ e($item->display_grade) }}"
                                data-difficulty="{{ (int) $item->difficulty_value }}"
                                data-minutes="{{ (int) $item->daily_learning_minutes }}"
                                data-days="{{ (int) $item->estimated_days }}"
                                data-search="{{ e($item->search_text) }}">
                            <span class="candidate-name">{{ $item->display_name }} (ID:{{ $item->id }})</span>
                            <span class="candidate-meta">
                                対象学年：{{ $item->display_grade }} ／
                                難易度：{{ (int) $item->difficulty_value }} ／
                                学習想定日数：{{ (int) $item->estimated_days }}日 ／
                                1日の推奨学習時間：{{ (int) $item->daily_learning_minutes }}分
                            </span>
                        </button>
                    @empty
                        <p class="package-items-empty">追加できるルーティンアイテムがありません。</p>
                    @endforelse
                </div>
            </section>

            <section class="package-items-box">
                <h3>構成ルーティンアイテム</h3>
                <div class="selected-package-item-list" data-selected-package-items></div>
                <p class="package-items-help">↑↓で並び順を変更できます。保存するとこのルーティンの構成アイテムとして反映されます。</p>
            </section>
        </div>

        <div class="routine-modal-footer">
            <button type="button" class="routine-modal-secondary" data-close-package-items-modal>閉じる</button>
            <button type="button" class="routine-modal-primary" data-save-package-items>保存</button>
        </div>
    </div>
</div>
