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
        if (page.dataset.routineJsBound === '40') return;
        page.dataset.routineJsBound = '40';

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
                        ['ルーティンアイテム数', esc(data.item_count || 0)],
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


        let packageItemsState = {
            routineId: null,
            selected: []
        };

        function candidateData(button) {
            return {
                routine_content_id: Number(button.dataset.id || 0),
                id: Number(button.dataset.id || 0),
                name: button.dataset.name || '名称未設定',
                target_grade: button.dataset.grade || '-',
                difficulty: Number(button.dataset.difficulty || 1),
                estimated_minutes: Number(button.dataset.minutes || 0),
                required_days: Number(button.dataset.days || 0),
                is_active: true
            };
        }

        function normalizeCurrentItems(items) {
            return Array.isArray(items) ? items.map(function (item) {
                return {
                    routine_content_id: Number(item.routine_content_id || item.id || 0),
                    id: Number(item.routine_content_id || item.id || 0),
                    name: item.name || item.item_name || item.content_name || '名称未設定',
                    target_grade: item.target_grade || item.grade || '-',
                    difficulty: Number(item.difficulty || 1),
                    estimated_minutes: Number(item.estimated_minutes || item.daily_learning_minutes || 0),
                    required_days: Number(item.required_days || item.estimated_days || 0),
                    is_active: item.is_active !== false && item.is_active !== 0 && item.is_active !== '0'
                };
            }).filter(function (item) {
                return item.routine_content_id > 0;
            }) : [];
        }

        function parseCurrentItems(raw) {
            try {
                return normalizeCurrentItems(JSON.parse(raw || '[]'));
            } catch (error) {
                try {
                    return normalizeCurrentItems(JSON.parse(decodeHtml(raw || '[]')));
                } catch (secondError) {
                    return [];
                }
            }
        }

        function refreshCandidateStates() {
            const selectedIds = new Set(packageItemsState.selected.map(function (item) {
                return Number(item.routine_content_id);
            }));

            document.querySelectorAll('[data-package-item-candidate]').forEach(function (button) {
                const selected = selectedIds.has(Number(button.dataset.id || 0));
                button.classList.toggle('is-selected', selected);
                button.disabled = selected;
            });
        }

        function renderSelectedPackageItems() {
            const list = document.querySelector('[data-selected-package-items]');
            const count = document.querySelector('[data-selected-item-count]');
            if (!list) return;

            if (count) count.textContent = String(packageItemsState.selected.length);

            if (packageItemsState.selected.length === 0) {
                list.innerHTML = '<p class="package-items-empty">構成ルーティンアイテムがありません。左側から追加してください。</p>';
                refreshCandidateStates();
                return;
            }

            list.innerHTML = packageItemsState.selected.map(function (item, index) {
                const inactiveLabel = item.is_active === false ? ' <span class="selected-package-item-inactive">無効</span>' : '';
                return '<div class="selected-package-item' + (item.is_active === false ? ' is-inactive' : '') + '" data-selected-package-item data-index="' + index + '">' +
                    '<span class="selected-package-item-order">' + (index + 1) + '</span>' +
                    '<div>' +
                        '<span class="selected-package-item-name">' + esc(item.name) + ' (ID:' + esc(item.routine_content_id || item.id || '') + ')' + inactiveLabel + '</span>' +
                        '<span class="selected-package-item-meta">対象学年：' + esc(item.target_grade || '-') + ' ／ 難易度：' + esc(item.difficulty || 1) + ' ／ 学習想定日数：' + esc(item.required_days || 0) + '日 ／ 1日の推奨学習時間：' + esc(item.estimated_minutes || 0) + '分</span>' +
                    '</div>' +
                    '<div class="selected-package-item-actions">' +
                        '<button type="button" data-package-item-up>↑</button>' +
                        '<button type="button" data-package-item-down>↓</button>' +
                        '<button type="button" class="remove" data-package-item-remove>削除</button>' +
                    '</div>' +
                '</div>';
            }).join('');

            refreshCandidateStates();
        }

        function openPackageItemsModal(button) {
            const modal = document.getElementById('routinePackageItemsModal');
            if (!modal) return;

            packageItemsState.routineId = button.dataset.routineId || null;
            packageItemsState.selected = parseCurrentItems(button.getAttribute('data-current-items') || '[]');

            const title = document.getElementById('routinePackageItemsModalTitle');
            const subtitle = document.getElementById('routinePackageItemsModalSubtitle');
            const search = document.querySelector('[data-package-item-search]');

            if (title) title.textContent = 'ルーティンアイテム管理';
            if (subtitle) subtitle.textContent = button.dataset.routineName || '対象ルーティン';
            if (search) search.value = '';

            filterPackageItemCandidates('');

            document.querySelectorAll('[data-package-item-candidate]').forEach(function (candidate) {
                candidate.hidden = false;
            });

            renderSelectedPackageItems();
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('routine-modal-open');
        }

        function closePackageItemsModal() {
            const modal = document.getElementById('routinePackageItemsModal');
            if (!modal) return;
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('routine-modal-open');
        }

        async function savePackageItems() {
            if (!packageItemsState.routineId) {
                alert('保存対象のルーティンが取得できません。');
                return;
            }

            const base = page.dataset.routineItemsSyncUrlBase;
            if (!base) {
                alert('保存先URLが取得できません。');
                return;
            }

            const payload = {
                items: packageItemsState.selected.map(function (item, index) {
                    return {
                        routine_content_id: Number(item.routine_content_id),
                        order_no: index + 1
                    };
                })
            };

            try {
                const response = await fetch(base + '/' + encodeURIComponent(packageItemsState.routineId) + '/items', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    let message = '保存に失敗しました。';
                    try {
                        const data = await response.json();
                        message = data.message || message;
                    } catch (jsonError) {
                        try {
                            const text = await response.text();
                            message = text || message;
                        } catch (textError) {}
                    }
                    throw new Error(message);
                }

                location.reload();
            } catch (error) {
                alert('ルーティンアイテムの保存に失敗しました。' + (error && error.message ? '\n' + error.message : ''));
            }
        }

        function moveSelectedPackageItem(index, direction) {
            const nextIndex = index + direction;
            if (nextIndex < 0 || nextIndex >= packageItemsState.selected.length) return;
            const temp = packageItemsState.selected[index];
            packageItemsState.selected[index] = packageItemsState.selected[nextIndex];
            packageItemsState.selected[nextIndex] = temp;
            renderSelectedPackageItems();
        }

        function normalizePackageSearchText(value) {
            return String(value || '')
                .normalize('NFKC')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim();
        }

        function filterPackageItemCandidates(keyword) {
            const words = normalizePackageSearchText(keyword).split(' ').filter(Boolean);
            document.querySelectorAll('[data-package-item-candidate]').forEach(function (button) {
                const text = normalizePackageSearchText([
                    button.dataset.id,
                    button.dataset.search,
                    button.dataset.name,
                    button.dataset.grade,
                    button.textContent
                ].join(' '));
                const matched = words.length === 0 || words.every(function (word) { return text.indexOf(word) !== -1; });
                button.hidden = !matched;
                button.classList.toggle('is-filtered-out', !matched);
            });
        }

        document.addEventListener('click', function (event) {


            const managePackageItemsButton = event.target.closest('[data-manage-package-items]');
            if (managePackageItemsButton) {
                event.preventDefault();
                openPackageItemsModal(managePackageItemsButton);
                return;
            }

            const candidateButton = event.target.closest('[data-package-item-candidate]');
            if (candidateButton) {
                event.preventDefault();
                if (candidateButton.disabled) return;
                const item = candidateData(candidateButton);
                if (item.routine_content_id > 0) {
                    packageItemsState.selected.push(item);
                    renderSelectedPackageItems();
                }
                return;
            }

            const selectedItemRow = event.target.closest('[data-selected-package-item]');
            if (selectedItemRow) {
                const index = Number(selectedItemRow.dataset.index || 0);
                if (event.target.closest('[data-package-item-up]')) {
                    event.preventDefault();
                    moveSelectedPackageItem(index, -1);
                    return;
                }
                if (event.target.closest('[data-package-item-down]')) {
                    event.preventDefault();
                    moveSelectedPackageItem(index, 1);
                    return;
                }
                if (event.target.closest('[data-package-item-remove]')) {
                    event.preventDefault();
                    packageItemsState.selected.splice(index, 1);
                    renderSelectedPackageItems();
                    return;
                }
            }

            const savePackageItemsButton = event.target.closest('[data-save-package-items]');
            if (savePackageItemsButton) {
                event.preventDefault();
                savePackageItems();
                return;
            }

            if (event.target.closest('[data-close-package-items-modal]')) {
                event.preventDefault();
                closePackageItemsModal();
                return;
            }

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


        ['input', 'keyup', 'change', 'search'].forEach(function (eventName) {
            document.addEventListener(eventName, function (event) {
                if (event.target && event.target.matches('[data-package-item-search]')) {
                    filterPackageItemCandidates(event.target.value);
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            const modal = document.getElementById('routineDetailModal');
            const packageModal = document.getElementById('routinePackageItemsModal');
            if (event.key === 'Escape' && packageModal && packageModal.classList.contains('is-open')) {
                closePackageItemsModal();
                return;
            }
            if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    });
})();
/* v41: fixed-position total learning time breakdown popover */
(function () {
    'use strict';

    function removeTotalTimePopover() {
        const existing = document.querySelector('.routine-total-time-popover');
        if (existing) existing.remove();
        document.querySelectorAll('.routine-total-time-button.is-popover-open').forEach(function (button) {
            button.classList.remove('is-popover-open');
            button.setAttribute('aria-expanded', 'false');
        });
    }

    function placePopover(popover, button) {
        const rect = button.getBoundingClientRect();
        const gap = 8;
        const width = Math.min(360, window.innerWidth - 24);

        popover.style.width = width + 'px';
        popover.style.left = '0px';
        popover.style.top = '0px';
        popover.style.visibility = 'hidden';
        document.body.appendChild(popover);

        const popRect = popover.getBoundingClientRect();
        let left = rect.left + (rect.width / 2) - (popRect.width / 2);
        left = Math.max(12, Math.min(left, window.innerWidth - popRect.width - 12));

        let top = rect.bottom + gap;
        if (top + popRect.height > window.innerHeight - 12) {
            top = rect.top - popRect.height - gap;
        }
        top = Math.max(12, top);

        popover.style.left = left + 'px';
        popover.style.top = top + 'px';
        popover.style.visibility = 'visible';
    }

    function showTotalTimePopover(button) {
        if (!button) return;
        const tooltip = button.querySelector('.total-time-tooltip');
        if (!tooltip) return;

        removeTotalTimePopover();

        const popover = document.createElement('div');
        popover.className = 'routine-total-time-popover';
        popover.innerHTML = tooltip.innerHTML;
        popover.setAttribute('role', 'tooltip');

        button.classList.add('is-popover-open');
        button.setAttribute('aria-expanded', 'true');
        placePopover(popover, button);
    }

    document.addEventListener('mouseover', function (event) {
        const button = event.target.closest && event.target.closest('.routine-total-time-button');
        if (!button) return;
        showTotalTimePopover(button);
    });

    document.addEventListener('focusin', function (event) {
        const button = event.target.closest && event.target.closest('.routine-total-time-button');
        if (!button) return;
        showTotalTimePopover(button);
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest && event.target.closest('.routine-total-time-button');
        if (button) {
            event.preventDefault();
            showTotalTimePopover(button);
            return;
        }
        if (!event.target.closest || !event.target.closest('.routine-total-time-popover')) {
            removeTotalTimePopover();
        }
    });

    document.addEventListener('mouseout', function (event) {
        const button = event.target.closest && event.target.closest('.routine-total-time-button');
        if (!button) return;
        const toElement = event.relatedTarget;
        if (toElement && (button.contains(toElement) || toElement.closest && toElement.closest('.routine-total-time-popover'))) return;
        window.setTimeout(function () {
            const hovered = document.querySelector('.routine-total-time-button:hover, .routine-total-time-popover:hover');
            if (!hovered) removeTotalTimePopover();
        }, 120);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') removeTotalTimePopover();
    });

    window.addEventListener('scroll', removeTotalTimePopover, true);
    window.addEventListener('resize', removeTotalTimePopover);
})();
