document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.challenge-management-page');
    if (!page) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || page.dataset.csrfToken;
    const listUrl = page.dataset.listUrl;
    const storeUrl = page.dataset.storeUrl;
    const updateUrlBase = page.dataset.updateUrlBase;
    const reorderUrl = page.dataset.reorderUrl;
    const duplicateUrlBase = page.dataset.duplicateUrlBase;
    const deactivateUrlBase = page.dataset.deactivateUrlBase;
    const deleteUrlBase = page.dataset.deleteUrlBase;
    const bulkDeactivateUrl = page.dataset.bulkDeactivateUrl;
    const bulkDeleteUrl = page.dataset.bulkDeleteUrl;
    const bulkDeleteButton = document.getElementById('challengeBulkDeleteButton');

    const tbody = document.getElementById('challengeTableBody');

    const totalCount = document.getElementById('challengeTotalCount');
    const activeCount = document.getElementById('challengeActiveCount');
    const inactiveCount = document.getElementById('challengeInactiveCount');

    const searchInput = document.getElementById('challengeSearchInput');
    const categoryFilter = document.getElementById('challengeCategoryFilter');
    const difficultyFilter = document.getElementById('challengeDifficultyFilter');
    const badgeFilter = document.getElementById('challengeBadgeFilter');
    const titleFilter = document.getElementById('challengeTitleFilter');
    const statusFilter = document.getElementById('challengeStatusFilter');

    const addButton = document.getElementById('challengeAddButton');
    const modal = document.getElementById('challengeModal');
    const modalTitle = document.getElementById('challengeModalTitle');
    const modalClose = document.getElementById('challengeModalClose');
    const modalCancel = document.getElementById('challengeModalCancel');
    const modalSave = document.getElementById('challengeModalSave');
    const checkAll = document.getElementById('challengeCheckAll');
    const bulkDeactivateButton = document.getElementById('challengeBulkDeactivateButton');

    let rows = [];
    let editingId = null;
    let sortable = null;

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            ...options,
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => null);

            if (response.status === 422 && errorData?.errors) {
                const messages = Object.values(errorData.errors).flat().join('\n');
                throw new Error(messages);
            }

            throw new Error(errorData?.message || '処理に失敗しました。');
        }

        return response.json();
    }

    async function requestForm(url, formData, method = 'POST') {
        if (method !== 'POST') {
            formData.append('_method', method);
        }

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => null);

            if (response.status === 422 && errorData?.errors) {
                const messages = Object.values(errorData.errors).flat().join('\n');
                throw new Error(messages);
            }

            throw new Error(errorData?.message || '処理に失敗しました。');
        }

        return response.json();
    }

    async function loadChallenges() {
        const params = new URLSearchParams({
            keyword: searchInput.value,
            category_id: categoryFilter.value,
            difficulty: difficultyFilter.value,
            badge_id: badgeFilter.value,
            title_id: titleFilter.value,
            status: statusFilter.value,
        });

        const data = await request(`${listUrl}?${params.toString()}`);
        rows = data.rows || [];

        totalCount.textContent = data.summary.total;
        activeCount.textContent = data.summary.active;
        inactiveCount.textContent = data.summary.inactive;

        renderTable();
    }

    function formatRequirement(row) {
        if (row.challenge_type === 'teacher_approval') return '講師承認';
        if (row.challenge_type === 'qualification') return '資格取得';
        if (row.challenge_type === 'custom') return row.requirement_description || '-';

        if (row.challenge_type === 'score') {
            if (row.max_score && row.passing_score) {
                return `${row.max_score}問中${row.passing_score}点以上`;
            }

            if (row.passing_score) {
                return `${row.passing_score}点以上`;
            }
        }

        return row.requirement_description || '-';
    }

    function renderTable() {
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="13">データがありません</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => {
            const difficulty = '★'.repeat(row.difficulty ?? 1);

            return `
                <tr data-id="${row.id}">
                    <td>
                        <input
                            type="checkbox"
                            class="challenge-row-check"
                            value="${row.id}">
                    </td>
                    <td class="master-drag-handle">⋮⋮</td>
                    <td>${row.id}</td>
                    <td>${row.icon_path ? `<img src="${row.icon_path}" class="challenge-icon">` : '-'}</td>
                    <td>${row.category_name ?? ''}</td>
                    <td>${row.name ?? ''}</td>
                    <td><span class="challenge-difficulty">${difficulty}</span></td>
                    <td>${formatRequirement(row)}</td>
                    <td>${row.badge_name ?? '-'}</td>
                    <td>${row.title_name ?? '-'}</td>
                    <td>${Number(row.point_amount ?? 0).toLocaleString()}</td>
                    <td>
                        <span class="master-status ${row.is_active ? 'active' : 'inactive'}">
                            ${row.is_active ? '有効' : '無効'}
                        </span>
                    </td>
                    <td>
                        <button type="button" class="master-secondary-button challenge-edit-button" data-id="${row.id}">編集</button>
                        <button type="button" class="master-secondary-button challenge-copy-button" data-id="${row.id}">複製</button>
                        <button type="button" class="master-secondary-button challenge-deactivate-button" data-id="${row.id}">無効化</button>
                        <button type="button" class="master-secondary-button challenge-delete-button" data-id="${row.id}">削除</button>
                    </td>
                </tr>
            `;
        }).join('');

        bindEditButtons();
        bindDuplicateButtons();
        bindDeactivateButtons();
        bindDeleteButtons();
        initializeSortable();
    }

    function openCreateModal() {
        editingId = null;
        modalTitle.textContent = 'チャレンジを追加';

        document.getElementById('challengeIdInput').value = '';
        document.getElementById('challengeCodeInput').value = '';
        document.getElementById('challengeNameInput').value = '';
        document.getElementById('challengeCategoryInput').value = '';
        document.getElementById('challengeCategorySearchInput').value = '';
        document.getElementById('challengeDifficultyInput').value = '1';
        document.getElementById('challengeDescriptionInput').value = '';
        document.getElementById('challengeIconPathInput').value = '';
        document.getElementById('challengeIconFileInput').value = '';
        document.getElementById('challengeIconPreview').style.display = 'none';
        document.getElementById('challengeIconPreview').src = '';
        document.getElementById('challengeTypeInput').value = 'score';
        document.getElementById('requirementDescriptionInput').value = '';
        document.getElementById('maxScoreInput').value = '';
        document.getElementById('passingScoreInput').value = '';
        document.getElementById('badgeRewardInput').value = '';
        document.getElementById('badgeRewardSearchInput').value = '';
        document.getElementById('titleRewardInput').value = '';
        document.getElementById('titleRewardSearchInput').value = '';
        document.getElementById('pointRewardInput').value = 0;
        document.getElementById('challengeActiveInput').checked = true;

        modal.classList.add('show');
    }

    function openEditModal(row) {
        editingId = row.id;
        modalTitle.textContent = 'チャレンジを編集';

        document.getElementById('challengeIdInput').value = row.id;
        document.getElementById('challengeCodeInput').value = row.code ?? '';
        document.getElementById('challengeNameInput').value = row.name ?? '';
        document.getElementById('challengeCategoryInput').value = row.category_id ?? '';
        document.getElementById('challengeCategorySearchInput').value = row.category_name ?? '';
        document.getElementById('challengeDifficultyInput').value = row.difficulty ?? 1;
        document.getElementById('challengeDescriptionInput').value = row.description ?? '';
        document.getElementById('challengeIconPathInput').value = row.icon_path ?? '';
        document.getElementById('challengeIconFileInput').value = '';

        if (row.icon_path) {
            document.getElementById('challengeIconPreview').src = row.icon_path;
            document.getElementById('challengeIconPreview').style.display = 'block';
        } else {
            document.getElementById('challengeIconPreview').style.display = 'none';
            document.getElementById('challengeIconPreview').src = '';
        }

        document.getElementById('challengeTypeInput').value = row.challenge_type ?? 'score';
        document.getElementById('requirementDescriptionInput').value = row.requirement_description ?? '';
        document.getElementById('maxScoreInput').value = row.max_score ?? '';
        document.getElementById('passingScoreInput').value = row.passing_score ?? '';
        document.getElementById('badgeRewardInput').value = row.badge_id ?? '';
        document.getElementById('badgeRewardSearchInput').value = row.badge_name ?? '';
        document.getElementById('titleRewardInput').value = row.title_id ?? '';
        document.getElementById('titleRewardSearchInput').value = row.title_name ?? '';
        document.getElementById('pointRewardInput').value = row.point_amount ?? 0;
        document.getElementById('challengeActiveInput').checked = row.is_active;

        modal.classList.add('show');
    }

    function bindEditButtons() {
        document.querySelectorAll('.challenge-edit-button').forEach(button => {
            button.addEventListener('click', () => {
                const row = rows.find(item => String(item.id) === String(button.dataset.id));
                if (row) openEditModal(row);
            });
        });
    }

    function bindDuplicateButtons() {
        document.querySelectorAll('.challenge-copy-button').forEach(button => {
            button.addEventListener('click', async () => {
                if (!confirm('複製しますか？')) return;

                await request(`${duplicateUrlBase}/${button.dataset.id}/duplicate`, {
                    method: 'POST',
                });

                await loadChallenges();
            });
        });
    }

    function bindDeactivateButtons() {
        document.querySelectorAll('.challenge-deactivate-button').forEach(button => {
            button.addEventListener('click', async () => {
                if (!confirm('このチャレンジを無効化しますか？')) return;

                await request(`${deactivateUrlBase}/${button.dataset.id}/deactivate`, {
                    method: 'POST',
                });

                await loadChallenges();
            });
        });
    }

    function bindDeleteButtons() {
        document.querySelectorAll('.challenge-delete-button').forEach(button => {
            button.addEventListener('click', async () => {
                if (!confirm('このチャレンジを完全に削除しますか？')) return;

                await request(`${deleteUrlBase}/${button.dataset.id}`, {
                    method: 'DELETE',
                });

                await loadChallenges();
            });
        });
    }

    function getSelectedChallengeIds() {
        return Array.from(document.querySelectorAll('.challenge-row-check:checked'))
            .map(checkbox => Number(checkbox.value));
    }

    async function bulkDeactivateChallenges() {
        const ids = getSelectedChallengeIds();

        if (!ids.length) {
            alert('無効化するチャレンジを選択してください。');
            return;
        }

        if (!confirm('選択したチャレンジを一括無効化しますか？')) {
            return;
        }

        await request(bulkDeactivateUrl, {
            method: 'POST',
            body: JSON.stringify({ ids }),
        });

        if (checkAll) {
            checkAll.checked = false;
        }

        await loadChallenges();
    }

    async function bulkDeleteChallenges() {
        const ids = getSelectedChallengeIds();

        if (!ids.length) {
            alert('削除するチャレンジを選択してください。');
            return;
        }

        if (!confirm('選択したチャレンジを完全に削除しますか？')) {
            return;
        }

        await request(bulkDeleteUrl, {
            method: 'POST',
            body: JSON.stringify({ ids }),
        });

        if (checkAll) {
            checkAll.checked = false;
        }

        await loadChallenges();
    }

    function closeModal() {
        modal.classList.remove('show');
    }

    async function saveChallenge() {
        const formData = new FormData();

        formData.append('challenge_category_id', document.getElementById('challengeCategoryInput').value);
        formData.append('code', document.getElementById('challengeCodeInput').value);
        formData.append('name', document.getElementById('challengeNameInput').value);
        formData.append('difficulty', document.getElementById('challengeDifficultyInput').value);
        formData.append('description', document.getElementById('challengeDescriptionInput').value);
        formData.append('challenge_type', document.getElementById('challengeTypeInput').value);
        formData.append('requirement_description', document.getElementById('requirementDescriptionInput').value);
        formData.append('max_score', document.getElementById('maxScoreInput').value);
        formData.append('passing_score', document.getElementById('passingScoreInput').value);
        formData.append('badge_id', document.getElementById('badgeRewardInput').value);
        formData.append('title_id', document.getElementById('titleRewardInput').value);
        formData.append('point_amount', document.getElementById('pointRewardInput').value || 0);
        formData.append('is_active', document.getElementById('challengeActiveInput').checked ? 1 : 0);

        const iconFile = document.getElementById('challengeIconFileInput').files[0];

        if (iconFile) {
            formData.append('icon_file', iconFile);
        }

        try {
            if (editingId) {
                await requestForm(`${updateUrlBase}/${editingId}`, formData, 'PUT');
            } else {
                await requestForm(storeUrl, formData, 'POST');
            }

            closeModal();
            await loadChallenges();

        } catch (error) {
            alert(error.message);
        }
    }

    function setupRewardSearch(inputId, hiddenId, listId) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const list = document.getElementById(listId);

        if (!input || !hidden || !list) return;

        input.addEventListener('focus', () => {
            list.classList.add('show');
        });

        input.addEventListener('input', () => {
            const keyword = input.value.trim();

            list.querySelectorAll('button').forEach(button => {
                button.style.display = button.textContent.includes(keyword) ? 'block' : 'none';
            });
        });

        list.querySelectorAll('button').forEach(button => {
            button.addEventListener('click', () => {
                hidden.value = button.dataset.id;
                input.value = button.textContent.trim();
                list.classList.remove('show');

                if (
                    hiddenId === 'challengeCategoryFilter' ||
                    hiddenId === 'challengeBadgeFilter' ||
                    hiddenId === 'challengeTitleFilter'
                ) {
                    loadChallenges();
                }
            });
        });

        document.addEventListener('click', event => {
            if (!event.target.closest(`#${listId}`) && event.target !== input) {
                list.classList.remove('show');
            }
        });
    }

    function initializeSortable() {
        if (sortable) {
            sortable.destroy();
        }

        sortable = new Sortable(tbody, {
            handle: '.master-drag-handle',
            animation: 150,

            onEnd: async () => {
                const items = [];

                tbody.querySelectorAll('tr[data-id]').forEach((row, index) => {
                    items.push({
                        id: Number(row.dataset.id),
                        sort_order: index + 1,
                    });
                });

                try {
                    await request(reorderUrl, {
                        method: 'POST',
                        body: JSON.stringify({ items }),
                    });
                } catch (error) {
                    alert('並び順の保存に失敗しました。');
                }
            },
        });
    }

    document.getElementById('challengeIconFileInput')?.addEventListener('change', event => {
        const file = event.target.files[0];

        if (!file) return;

        const preview = document.getElementById('challengeIconPreview');
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
    });

    addButton.addEventListener('click', openCreateModal);
    modalClose.addEventListener('click', closeModal);
    modalCancel.addEventListener('click', closeModal);
    modalSave.addEventListener('click', saveChallenge);
    checkAll?.addEventListener('change', () => {
        document.querySelectorAll('.challenge-row-check').forEach(checkbox => {
            checkbox.checked = checkAll.checked;
        });
    });

    bulkDeactivateButton?.addEventListener('click', bulkDeactivateChallenges);
    bulkDeleteButton?.addEventListener('click', bulkDeleteChallenges);

    searchInput.addEventListener('input', loadChallenges);
    difficultyFilter.addEventListener('change', loadChallenges);
    statusFilter.addEventListener('change', loadChallenges);

    setupRewardSearch('challengeCategorySearchInput', 'challengeCategoryInput', 'challengeCategoryList');
    setupRewardSearch('badgeRewardSearchInput', 'badgeRewardInput', 'badgeRewardList');
    setupRewardSearch('titleRewardSearchInput', 'titleRewardInput', 'titleRewardList');

    setupRewardSearch('challengeCategoryFilterSearchInput', 'challengeCategoryFilter', 'challengeCategoryFilterList');
    setupRewardSearch('challengeBadgeFilterSearchInput', 'challengeBadgeFilter', 'challengeBadgeFilterList');
    setupRewardSearch('challengeTitleFilterSearchInput', 'challengeTitleFilter', 'challengeTitleFilterList');

    loadChallenges();
});