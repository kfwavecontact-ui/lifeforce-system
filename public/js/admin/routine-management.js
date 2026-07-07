(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    ready(function () {
        const page = document.querySelector('.routine-page');
        if (!page) return;
        if (page.dataset.routineJsBound === '36') return;
        page.dataset.routineJsBound = '36';

        const csrf = page.dataset.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const esc = function (value) {
            return String(value ?? '').replace(/[&<>'"]/g, function (c) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c];
            });
        };

        const decodeHtml = function (value) {
            const textarea = document.createElement('textarea');
            textarea.innerHTML = String(value || '');
            return textarea.value;
        };

        const cssEscape = function (value) {
            value = String(value ?? '');
            if (window.CSS && typeof CSS.escape === 'function') return CSS.escape(value);
            return value.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
        };

        const star = function (value) {
            const n = Math.max(1, Math.min(5, Number(value || 1)));
            return '★'.repeat(n) + '☆'.repeat(5 - n);
        };

        const minutes = function (value) {
            const total = Number(value || 0);
            if (total < 60) return total + '分';
            const h = Math.floor(total / 60);
            const m = total % 60;
            return m ? h + '時間' + m + '分' : h + '時間';
        };

        const dateText = function (value) {
            if (!value) return '-';
            const text = String(value);
            return esc(text.length >= 16 ? text.slice(0, 16).replace('T', ' ') : text);
        };

        const boolActive = function (value) {
            return (value === true || value === 1 || value === '1')
                ? '<span class="active-badge active">有効</span>'
                : '<span class="active-badge inactive">無効</span>';
        };

        const useFlag = function (value) {
            return Number(value || 0) > 0
                ? '<span class="active-badge active">使用中</span>'
                : '<span class="active-badge inactive">未使用</span>';
        };

        const yesNoMark = function (value, label) {
            return (value === true || value === 1 || value === '1')
                ? '<span class="modal-mark is-on">' + label + ' 登録済み</span>'
                : '<span class="modal-mark">' + label + ' 未登録</span>';
        };

        const tagHtml = function (value) {
            const html = String(value || '')
                .split(',')
                .map(function (v) { return v.trim(); })
                .filter(Boolean)
                .map(function (v) { return '<b class="tag">' + esc(v) + '</b>'; })
                .join(' ');
            return html || '<span class="muted">-</span>';
        };

        const itemList = function (items) {
            if (!Array.isArray(items) || items.length === 0) {
                return '<ul class="modal-list"><li>該当データがありません。</li></ul>';
            }
            return '<ul class="modal-list">' + items.map(function (item, index) {
                const name = typeof item === 'string'
                    ? item
                    : (item.name || item.item_name || item.content_name || '名称未設定');
                const suffix = (item && item.required_days !== undefined)
                    ? ' <small>' + esc(item.required_days) + '日 × ' + esc(item.estimated_minutes) + '分</small>'
                    : '';
                return '<li><span>' + (index + 1) + '</span><strong>' + esc(name) + '</strong>' + suffix + '</li>';
            }).join('') + '</ul>';
        };

        const section = function (title, rows) {
            return '<section class="modal-section"><h3>' + esc(title) + '</h3><dl class="modal-grid">' +
                rows.map(function (row) {
                    return '<dt>' + esc(row[0]) + '</dt><dd>' + (row[1] ?? '<span class="muted">-</span>') + '</dd>';
                }).join('') +
                '</dl></section>';
        };

        function openEdit(button) {
            const id = button.getAttribute('data-edit-trigger');
            const displayRow = button.closest('[data-display-row]');
            const sameRow = button.closest('[data-edit-row]');
            const separateEditRow = id ? document.querySelector('[data-edit-row][data-id="' + cssEscape(id) + '"]') : null;

            if (displayRow && separateEditRow && displayRow !== separateEditRow) {
                displayRow.hidden = true;
                separateEditRow.hidden = false;
                separateEditRow.classList.add('is-editing');
                return;
            }

            const row = sameRow || separateEditRow || button.closest('tr');
            if (row) row.classList.add('is-editing');
        }

        function cancelEdit(button) {
            const row = button.closest('[data-edit-row]') || button.closest('tr');
            if (!row) return;

            row.classList.remove('is-editing');

            const id = row.getAttribute('data-id');
            const displayRow = id ? document.querySelector('[data-display-row][data-id="' + cssEscape(id) + '"]') : null;
            if (displayRow && displayRow !== row) {
                row.hidden = true;
                displayRow.hidden = false;
            }
        }

        async function saveEdit(button) {

            const row = button.closest('[data-edit-row]') || button.closest('tr');
            
            if (!row) {
                alert('保存対象の行が取得できません。');
                return;
            }

            const id = row.getAttribute('data-id');
            const isCreate = row.hasAttribute('data-create-row');
            const isItem = !!row.closest('.routine-item-table');

            const isRoutineCreate = row.hasAttribute('data-create-routine-row');

            const base =
                isCreate
                    ? page.dataset.itemStoreUrl
                    : isRoutineCreate
                        ? page.dataset.routineStoreUrl
                        : (isItem ? page.dataset.itemUpdateUrlBase : page.dataset.routineUpdateUrlBase);

            if (!base) {
                alert('保存先URLが取得できません。');
                return;
            }


            const payload = {};
            row.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
                if (!field.name) return;
                if (!field.classList.contains('edit-field') && !field.closest('.edit-field')) return;

                if (field.type === 'checkbox') {
                    if (field.checked) {
                        payload[field.name] = 1;
                    } else if (!(field.name in payload)) {
                        payload[field.name] = 0;
                    }
                    return;
                }

                if (field.type === 'hidden') {
                    if (!(field.name in payload)) {
                        payload[field.name] = field.value;
                    }
                    return;
                }

                payload[field.name] = field.value;
            });

            try {

                const url = (isCreate || isRoutineCreate)
                    ? base
                    : base + '/' + encodeURIComponent(id);

                const response = await fetch(url, {
                    method: (isCreate || isRoutineCreate) ? 'POST' : 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },

                    
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(text || '保存に失敗しました。');
                }

                location.reload();
            } catch (error) {
                alert('必須項目を入力してください。');
            }
        }

        async function toggleItemMark(target, type) {
            const id = target.getAttribute('data-id');
            if (!id || target.dataset.markUpdating === '1') return;

            const url = type === 'favorite'
                ? '/admin/system/routine-management/items/' + encodeURIComponent(id) + '/favorite-toggle'
                : '/admin/system/routine-management/items/' + encodeURIComponent(id) + '/frequently-used-toggle';

            target.dataset.markUpdating = '1';
            target.style.pointerEvents = 'none';

            try {

                alert(url + "\n" + JSON.stringify(payload));
                return;



                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(text || '更新に失敗しました。');
                }

                const data = await response.json();

                if (!data || data.success !== true) {
                    throw new Error('更新に失敗しました。');
                }

                target.classList.toggle('on', !!data.value);

            } catch (error) {
                alert(error.message || '更新に失敗しました。');
            } finally {
                delete target.dataset.markUpdating;
                target.style.pointerEvents = '';
            }
        }

        function openDetail(button) {
            const modal = document.getElementById('routineDetailModal');
            const title = document.getElementById('routineModalTitle');
            const body = document.getElementById('routineModalBody');

            if (!modal || !title || !body) {
                alert('詳細モーダルのHTMLが見つかりません。');
                return;
            }

            let raw = button.getAttribute('data-detail') || button.dataset.detail || '{}';
            let data = {};
            try {
                data = JSON.parse(raw);
            } catch (error) {
                try {
                    data = JSON.parse(decodeHtml(raw));
                } catch (secondError) {
                    alert('詳細データの読み込みに失敗しました。');
                    return;
                }
            }

            const isItem = data.type === 'item';
            title.textContent = isItem ? 'ルーティンアイテム詳細' : 'ルーティン詳細';

            if (isItem) {
                body.innerHTML =
                    section('基本情報', [
                        ['ID', esc(data.id)],
                        ['ルーティンアイテム名', esc(data.name)],
                        ['説明', data.description ? esc(data.description) : '<span class="muted">-</span>'],
                        ['対象学年', esc(data.target_grade || '-')],
                        ['難易度', '<span class="stars">' + star(data.difficulty) + '</span>'],
                        ['学習想定日数', esc(data.estimated_days || 0) + '日'],
                        ['1日の推奨学習時間', esc(data.daily_learning_minutes || 0) + '分'],
                        ['学習ページ状態', esc(data.learning_page_status || '-')],
                        ['検索タグ', tagHtml(data.search_tags)]
                    ]) +
                    section('利用状況', [
                        ['使用中ルーティン数', esc(data.used_routine_count || 0)],
                        ['使用中ルーティン一覧', itemList(data.used_routine_names)],
                        ['割当生徒数', esc(data.assigned_student_count || 0)],
                        ['過去学習回数', esc(data.past_study_count || 0)],
                        ['最終学習日', dateText(data.last_studied_at)]
                    ]) +
                    section('管理情報', [
                        ['作成日', dateText(data.created_at)],
                        ['作成者', esc(data.created_by_name || '-')],
                        ['更新日', dateText(data.updated_at)],
                        ['更新者', esc(data.updated_by_name || '-')],
                        ['有効', boolActive(data.is_active)]
                    ]) +
                    section('このアイテムのマーク', [
                        ['お気に入り', yesNoMark(data.is_favorite, '★')],
                        ['よく使う', yesNoMark(data.is_frequently_used, '📌')]
                    ]);
            } else {
                body.innerHTML =
                    section('基本情報', [
                        ['ID', esc(data.id)],
                        ['ルーティン名', esc(data.name)],
                        ['説明', data.description ? esc(data.description) : '<span class="muted">-</span>'],
                        ['対象学年', esc(data.target_grade || '-')],
                        ['難易度', esc(data.target_level || '-')],
                        ['アイテム数', esc(data.item_count || 0)],
                        ['総学習時間（自動集計）', minutes(data.total_learning_minutes || 0)],
                        ['使用中フラグ', useFlag(data.student_routine_count)],
                        ['割当生徒数', esc(data.assigned_student_count || 0)],
                        ['検索タグ', tagHtml(data.search_tags)],
                        ['有効', boolActive(data.is_active)]
                    ]) +
                    section('利用状況', [
                        ['使用中フラグ', useFlag(data.student_routine_count)],
                        ['割当生徒数', esc(data.assigned_student_count || 0)]
                    ]) +
                    '<section class="modal-section"><h3>構成アイテム一覧</h3>' + itemList(data.items) + '</section>' +
                    section('管理情報', [
                        ['作成日', dateText(data.created_at)],
                        ['作成者', esc(data.created_by_name || '-')],
                        ['更新日', dateText(data.updated_at)],
                        ['更新者', esc(data.updated_by_name || '-')]
                    ]);
            }

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('routine-modal-open');
        }

        function closeModal() {
            const modal = document.getElementById('routineDetailModal');
            if (!modal) return;
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('routine-modal-open');
        }

        document.addEventListener('click', function (event) {

            const createItemButton = event.target.closest('[data-create-item]');
            if (createItemButton) {
                event.preventDefault();

                const row = document.querySelector('[data-create-row]');
                if (!row) {
                    alert('新規作成行が見つかりません。');
                    return;
                }

                row.hidden = false;
                row.classList.add('is-editing');

                const nameInput = row.querySelector('input[name="name"]');
                if (nameInput) {
                    nameInput.focus();
                }

                return;
            }

            const cancelCreateRoutineButton = event.target.closest('[data-cancel-create-routine]');
            if (cancelCreateRoutineButton) {
                event.preventDefault();

                const row = cancelCreateRoutineButton.closest('[data-create-routine-row]');
                if (!row) return;

                row.hidden = true;
                row.style.display = '';
                row.classList.remove('is-editing');

                return;
            }

            const createRoutineButton = event.target.closest('[data-create-routine]');
            if (createRoutineButton) {
                event.preventDefault();

                const row = document.querySelector('[data-create-routine-row]');
                if (!row) {
                    alert('新規作成行が見つかりません。');
                    return;
                }

                row.hidden = false;
                row.classList.add('is-editing');

                const nameInput = row.querySelector('input[name="name"]');
                if (nameInput) {
                    nameInput.focus();
                }

                return;
            }

            const cancelCreateButton = event.target.closest('[data-cancel-create]');
            if (cancelCreateButton) {
                event.preventDefault();

                const row = cancelCreateButton.closest('[data-create-row]');
                if (!row) return;

                row.hidden = true;
                row.style.display = '';
                row.classList.remove('is-editing');

                return;
            }




            const favoriteToggle = event.target.closest('[data-favorite-toggle]');
            if (favoriteToggle) {
                event.preventDefault();
                event.stopPropagation();
                toggleItemMark(favoriteToggle, 'favorite');
                return;
            }

            const frequentToggle = event.target.closest('[data-frequent-toggle]');
            if (frequentToggle) {
                event.preventDefault();
                event.stopPropagation();
                toggleItemMark(frequentToggle, 'frequent');
                return;
            }

            const duplicateForm = event.target.closest('[data-duplicate-form]');
            if (duplicateForm && event.target.closest('button[type="submit"]')) {
                if (!confirm('複製します。よろしいですか？')) event.preventDefault();
                return;
            }

            const editButton = event.target.closest('[data-edit-trigger]');
            if (editButton) {
                event.preventDefault();
                openEdit(editButton);
                return;
            }

            const detailButton = event.target.closest('[data-detail]');
            if (detailButton) {
                event.preventDefault();
                openDetail(detailButton);
                return;
            }

            const saveButton = event.target.closest('[data-save]');
            if (saveButton) {
                event.preventDefault();
                saveEdit(saveButton);
                return;
            }


            const cancelButton = event.target.closest('[data-cancel]');
            if (cancelButton) {
                event.preventDefault();
                cancelEdit(cancelButton);
                return;
            }

            if (event.target.closest('[data-close-modal]')) {
                event.preventDefault();
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            const modal = document.getElementById('routineDetailModal');
            if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    });
})();