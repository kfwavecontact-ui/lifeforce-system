document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('.routine-page');
    if (!page) return;

    const csrf = page.dataset.csrfToken;
    const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
    const boolActive = (v) => (v === true || v === 1 || v === '1') ? '有効' : '無効';
    const yesNo = (v) => (v === true || v === 1 || v === '1') ? '登録済み' : '未登録';
    const star = (v) => {
        const n = Math.max(1, Math.min(5, Number(v || 1)));
        return '★'.repeat(n) + '☆'.repeat(5 - n);
    };
    const minutes = (v) => {
        v = Number(v || 0);
        if (v < 60) return `${v}分`;
        const h = Math.floor(v / 60);
        const m = v % 60;
        return m ? `${h}時間${m}分` : `${h}時間`;
    };
    const tags = (v) => String(v || '').split(',').map(s => s.trim()).filter(Boolean).map(s => `<b class="tag">${esc(s)}</b>`).join(' ') || '-';
    const list = (items) => {
        if (!items || !items.length) return '<ul class="modal-list"><li>該当データがありません。</li></ul>';
        return `<ul class="modal-list">${items.map((item, i) => `<li>${i + 1}. ${esc(item.name || item)}${item.required_days !== undefined ? `（${esc(item.required_days)}日 × ${esc(item.estimated_minutes)}分）` : ''}</li>`).join('')}</ul>`;
    };
    const section = (name, rows) => `<div class="modal-section"><h3>${name}</h3><dl class="modal-grid">${rows.map(r => `<dt>${r[0]}</dt><dd>${r[1] ?? '-'}</dd>`).join('')}</dl></div>`;

    document.querySelectorAll('[data-duplicate-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!confirm('複製します。よろしいですか？')) event.preventDefault();
        });
    });

    document.querySelectorAll('[data-edit-trigger]').forEach(function (button) {
        button.addEventListener('click', function () {
            const id = button.dataset.editTrigger;
            const row = document.querySelector(`[data-edit-row][data-id="${CSS.escape(id)}"]`);
            if (!row) return;
            row.classList.add('is-editing');
        });
    });

    document.querySelectorAll('[data-edit-row]').forEach(function (row) {
        const save = row.querySelector('[data-save]');
        const cancel = row.querySelector('[data-cancel]');

        if (cancel) cancel.addEventListener('click', function () {
            row.classList.remove('is-editing');
        });

        if (save) save.addEventListener('click', async function () {
            const isItem = !!row.closest('.routine-item-table');
            const base = isItem ? page.dataset.itemUpdateUrlBase : page.dataset.routineUpdateUrlBase;
            const payload = {};
            row.querySelectorAll('.edit-field[name]').forEach(function (field) {
                payload[field.name] = field.value;
            });

            try {
                const res = await fetch(base + '/' + row.dataset.id, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                if (!res.ok) {
                    const text = await res.text();
                    throw new Error(text || '保存に失敗しました。');
                }
                location.reload();
            } catch (e) {
                alert(e.message || '保存に失敗しました。');
            }
        });
    });

    const modal = document.getElementById('routineDetailModal');
    const title = document.getElementById('routineModalTitle');
    const body = document.getElementById('routineModalBody');

    document.querySelectorAll('[data-detail]').forEach(function (button) {
        button.addEventListener('click', function () {
            const data = JSON.parse(button.dataset.detail || '{}');
            const isItem = data.type === 'item';
            title.textContent = isItem ? 'ルーティンアイテム詳細' : 'ルーティン詳細';

            if (isItem) {
                body.innerHTML = section('基本情報', [
                    ['ID', esc(data.id)],
                    ['ルーティンアイテム名', esc(data.name)],
                    ['説明', esc(data.description)],
                    ['対象学年', esc(data.target_grade)],
                    ['難易度', `<span class="stars">${star(data.difficulty)}</span>`],
                    ['学習想定日数', `${esc(data.estimated_days)}日`],
                    ['1日の推奨学習時間', `${esc(data.daily_learning_minutes)}分`],
                    ['学習ページ状態', esc(data.learning_page_status)],
                    ['検索タグ', tags(data.search_tags)]
                ]) + section('利用状況', [
                    ['使用中ルーティン数', esc(data.used_routine_count)],
                    ['使用中ルーティン一覧', list(data.used_routine_names)],
                    ['割当生徒数', esc(data.assigned_student_count)],
                    ['過去学習回数', esc(data.past_study_count)],
                    ['最終学習日', esc(data.last_studied_at)]
                ]) + section('管理情報', [
                    ['作成日', esc(data.created_at)],
                    ['作成者', esc(data.created_by_name)],
                    ['更新日', esc(data.updated_at)],
                    ['更新者', esc(data.updated_by_name)],
                    ['有効', boolActive(data.is_active)]
                ]) + section('このアイテムのマーク', [
                    ['お気に入り', yesNo(data.is_favorite)],
                    ['よく使う', yesNo(data.is_frequently_used)]
                ]);
            } else {
                body.innerHTML = section('基本情報', [
                    ['ID', esc(data.id)],
                    ['ルーティン名', esc(data.name)],
                    ['説明', esc(data.description)],
                    ['対象学年', esc(data.target_grade)],
                    ['難易度', esc(data.target_level)],
                    ['アイテム数', esc(data.item_count)],
                    ['総学習時間（自動集計）', minutes(data.total_learning_minutes)],
                    ['使用中フラグ', Number(data.student_routine_count || 0) > 0 ? '使用中' : '未使用'],
                    ['割当生徒数', esc(data.assigned_student_count)],
                    ['検索タグ', tags(data.search_tags)],
                    ['有効', boolActive(data.is_active)]
                ]) + `<div class="modal-section"><h3>構成アイテム一覧</h3>${list(data.items)}</div>` + section('管理情報', [
                    ['作成者', esc(data.created_by_name)],
                    ['更新者', esc(data.updated_by_name)]
                ]);
            }
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(el => el.addEventListener('click', function () {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }));
});
