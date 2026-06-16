document.addEventListener('DOMContentLoaded', () => {
    console.log('badge-management.js loaded');
    const page = document.querySelector('.badge-management-page');
    if (!page) return;

    const csrfToken = page.dataset.csrfToken;

    const urls = {
        list: page.dataset.listUrl,
        store: page.dataset.storeUrl,
        updateBase: page.dataset.updateUrlBase,
        reorder: page.dataset.reorderUrl,
        duplicateBase: page.dataset.duplicateUrlBase,
        deactivateBase: page.dataset.deactivateUrlBase,
        deleteBase: page.dataset.deleteUrlBase,
        bulkDeactivate: page.dataset.bulkDeactivateUrl,
        bulkDelete: page.dataset.bulkDeleteUrl,
    };

    const tableBody = document.getElementById('badgeTableBody');
    const checkAll = document.getElementById('badgeCheckAll');

    const modal = document.getElementById('badgeModal');
    const modalTitle = document.getElementById('badgeModalTitle');

    let badges = [];

    const inputs = {
        id: document.getElementById('badgeIdInput'),
        code: document.getElementById('badgeCodeInput'),
        name: document.getElementById('badgeNameInput'),
        category: document.getElementById('badgeCategoryInput'),
        series: document.getElementById('badgeSeriesInput'),
        level: document.getElementById('badgeLevelInput'),
        description: document.getElementById('badgeDescriptionInput'),
        imageFile: document.getElementById('badgeImageFileInput'),
        imagePreview: document.getElementById('badgeImagePreview'),
        pointReward: document.getElementById('badgePointRewardInput'),
        limited: document.getElementById('badgeLimitedInput'),
        startDate: document.getElementById('badgeStartDateInput'),
        endDate: document.getElementById('badgeEndDateInput'),
        active: document.getElementById('badgeActiveInput'),
        categorySearch: document.getElementById('badgeCategorySearchInput'),
        seriesSearch: document.getElementById('badgeSeriesSearchInput'),
    };

    const requirementRows = document.getElementById('badgeRequirementRows');
    const requirementRowTemplate = document.getElementById('badgeRequirementRowTemplate');
    const requirementAddButton = document.getElementById('badgeRequirementAddButton');

    const filters = {
        keyword: document.getElementById('badgeSearchInput'),
        category: document.getElementById('badgeCategoryFilter'),
        series: document.getElementById('badgeSeriesFilter'),
        level: document.getElementById('badgeLevelFilter'),
        status: document.getElementById('badgeStatusFilter'),
    };

   const showModal = () => {
        modal.style.display = 'flex';
        modal.classList.add('open');
        modal.classList.add('is-open');
    };

    const hideModal = () => {
        modal.style.display = 'none';
        modal.classList.remove('open');
        modal.classList.remove('is-open');
    };

    const escapeHtml = (value) => {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    const formatRequirement = (type, value) => {
        if (!type && !value) return '-';

        if (type === 'teacher_approval') {
            return '講師承認';
        }

        if (type === 'minimum_points') {
            return `${value || 0}Pt到達`;
        }

        if (type === 'content_clear') {
            return `コンテンツクリア：${value || '-'}`;
        }

        if (type === 'challenge_pass') {
            return `チャレンジ合格：${value || '-'}`;
        }

        if (type === 'event_join') {
            return `イベント参加：${value || '-'}`;
        }

        if (type === 'manual') {
            return '手動付与';
        }

        return value ? `${type}：${value}` : type;
    };

    const formatRequirementHtml = (type, value) => {
        if (!type && !value) {
            return '<span class="badge-requirement-empty">-</span>';
        }

        const labelMap = {
            teacher_approval: '講師承認',
            minimum_points: 'Pt到達',
            content_clear: 'コンテンツクリア',
            challenge_pass: 'チャレンジ合格',
            event_join: 'イベント参加',
            manual: '手動付与',
        };

        const label = labelMap[type] || type || '-';

        if (type === 'teacher_approval' || type === 'manual') {
            return `<div class="badge-requirement"><span class="badge-requirement-label">${escapeHtml(label)}</span></div>`;
        }

        return `
            <div class="badge-requirement">
                <span class="badge-requirement-label">${escapeHtml(label)}</span>
                <span class="badge-requirement-value">${escapeHtml(value || '-')}</span>
            </div>
        `;
    };

    const renderRequirements = (requirements) => {
        if (!requirements.length) {
            return '<span class="badge-requirement-empty">-</span>';
        }

        return `
            <div class="badge-requirement-list">
                ${requirements.map(item => `
                    <span class="badge-requirement-tag">
                        ${escapeHtml(item.requirement_type_name)}
                        ${item.requirement_value ? '：' + escapeHtml(item.requirement_value) : ''}
                    </span>
                `).join('')}
            </div>
        `;
    };

    const selectedIds = () => {
        return Array.from(document.querySelectorAll('.badge-row-check:checked'))
            .map(input => Number(input.value));
    };

    const buildQuery = () => {
        const params = new URLSearchParams();

        params.set('keyword', filters.keyword.value || '');
        params.set('category_id', filters.category.value || 'all');
        params.set('series_id', filters.series.value || 'all');
        params.set('level', filters.level.value || 'all');
        params.set('status', filters.status.value || 'all');

        return params.toString();
    };

    const loadBadges = async () => {
        tableBody.innerHTML = '<tr><td colspan="13">読み込み中...</td></tr>';

        const response = await fetch(`${urls.list}?${buildQuery()}`, {
            headers: { 'Accept': 'application/json' },
        });

        const data = await response.json();

        badges = data.rows || [];

        document.getElementById('badgeTotalCount').textContent = data.summary.total;
        document.getElementById('badgeActiveCount').textContent = data.summary.active;
        document.getElementById('badgeInactiveCount').textContent = data.summary.inactive;

        renderRows();
    };

    const renderRows = () => {
        if (badges.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="13">データがありません。</td></tr>';
            return;
        }

        tableBody.innerHTML = badges.map((badge, index) => {
            const requirement = formatRequirement(badge.requirement_type, badge.requirement_value);

            const imageHtml = badge.image_path
                ? `<img src="${escapeHtml(badge.image_path)}" class="badge-table-image" alt="">`
                : '<span class="badge-no-image">なし</span>';

            return `
                <tr data-id="${badge.id}">
                    <td><input type="checkbox" class="badge-row-check" value="${badge.id}"></td>
                    <td>
                        <span class="badge-drag-handle" title="並び替え">⋮⋮</span>
                        <input type="hidden"
                               class="badge-order-input"
                               value="${badge.display_order}"
                               data-id="${badge.id}">
                    </td>
                    <td>${badge.id}</td>
                    <td>
                        <strong>${escapeHtml(badge.name)}</strong>
                        <div class="badge-code">${escapeHtml(badge.code || '')}</div>
                    </td>
                    <td>${imageHtml}</td>
                    <td>${escapeHtml(badge.category_name || '-')}</td>
                    <td>${escapeHtml(badge.series_name || '-')}</td>
                    <td>Lv${escapeHtml(badge.level)}</td>
                        <td>
                            <span class="badge-limited-status ${badge.is_limited ? 'is-limited' : 'is-normal'}">
                                ${badge.is_limited ? '限定' : '一般'}
                            </span>
                        </td>
                    <td>${renderRequirements(badge.requirements || [])}</td>
                    <td>${escapeHtml(badge.point_reward ?? 0)}Pt</td>
                    <td>
                        <span class="master-status ${badge.is_active ? 'is-active' : 'is-inactive'}">
                            ${badge.is_active ? '有効' : '無効'}
                        </span>
                    </td>
                    <td>
                        <div class="master-action-buttons">
                            <button type="button" class="master-link-button badge-edit-button" data-index="${index}">編集</button>
                            <button type="button" class="master-link-button badge-duplicate-button" data-id="${badge.id}">複製</button>
                            <button type="button" class="master-link-button badge-deactivate-button" data-id="${badge.id}">無効化</button>
                            <button type="button" class="master-danger-link-button badge-delete-button" data-id="${badge.id}">削除</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        setupSortable();
    };

    const addRequirementRow = (typeId = '', value = '') => {
        if (!requirementRows || !requirementRowTemplate) return;

        const currentCount = requirementRows.querySelectorAll('.badge-requirement-row').length;

        if (currentCount >= 3) {
            return;
        }

        const fragment = requirementRowTemplate.content.cloneNode(true);
        const row = fragment.querySelector('.badge-requirement-row');
        const typeInput = fragment.querySelector('.badgeRequirementTypeInput');
        const valueInput = fragment.querySelector('.badgeRequirementValueInput');
        const removeButton = fragment.querySelector('.badgeRequirementRemoveButton');

        typeInput.value = typeId || '';
        valueInput.value = value || '';

        removeButton.addEventListener('click', () => {
            row.remove();

            if (requirementRows.querySelectorAll('.badge-requirement-row').length === 0) {
                addRequirementRow();
            }

            updateRequirementAddButton();
        });

        requirementRows.appendChild(fragment);
        updateRequirementAddButton();
    };

    const updateRequirementAddButton = () => {
        if (!requirementAddButton || !requirementRows) return;

        const currentCount = requirementRows.querySelectorAll('.badge-requirement-row').length;
        requirementAddButton.disabled = currentCount >= 3;
    };

    const getRequirementPayload = () => {
        if (!requirementRows) return [];

        return Array.from(requirementRows.querySelectorAll('.badge-requirement-row'))
            .map(row => ({
                badge_requirement_type_id: row.querySelector('.badgeRequirementTypeInput')?.value || '',
                requirement_value: row.querySelector('.badgeRequirementValueInput')?.value || '',
            }))
            .filter(item => item.badge_requirement_type_id);
    };

    const resetForm = () => {
        inputs.id.value = '';
        inputs.code.value = '';
        inputs.name.value = '';
        inputs.category.value = '';
        inputs.series.value = '';
        inputs.level.value = '1';
        inputs.description.value = '';
        inputs.imageFile.value = '';
        inputs.imagePreview.src = '';
        inputs.imagePreview.style.display = 'none';
        requirementRows.innerHTML = '';
        addRequirementRow();
        inputs.pointReward.value = 0;
        inputs.limited.checked = false;
        inputs.startDate.value = '';
        inputs.endDate.value = '';
        inputs.active.checked = true;
        inputs.categorySearch.value = '';
        inputs.seriesSearch.value = '';

    };

    const openCreate = () => {
        resetForm();
        modalTitle.textContent = 'バッジを追加';
        showModal();
    };

    const openEdit = (badge) => {
        resetForm();

        inputs.id.value = badge.id;
        inputs.code.value = badge.code || '';
        inputs.name.value = badge.name || '';
        inputs.category.value = badge.category_id || '';
        inputs.series.value = badge.series_id || '';
        inputs.level.value = badge.level || 1;
        inputs.description.value = badge.description || '';
        requirementRows.innerHTML = '';
        if (badge.requirements && badge.requirements.length > 0) {
            badge.requirements.forEach(requirement => {
                addRequirementRow(
                    requirement.badge_requirement_type_id,
                    requirement.requirement_value
                );
            });
        } else {
            addRequirementRow();
        }
        inputs.pointReward.value = badge.point_reward || 0;
        inputs.limited.checked = !!badge.is_limited;
        inputs.startDate.value = badge.start_date || '';
        inputs.endDate.value = badge.end_date || '';
        inputs.active.checked = !!badge.is_active;

        if (badge.image_path) {
            inputs.imagePreview.src = badge.image_path;
            inputs.imagePreview.style.display = 'block';
        }

        modalTitle.textContent = 'バッジを編集';
        showModal();
    };

    const saveBadge = async () => {
        const formData = new FormData();

        formData.append('badge_category_id', inputs.category.value);
        formData.append('badge_series_id', inputs.series.value);
        formData.append('code', inputs.code.value);
        formData.append('name', inputs.name.value);
        formData.append('level', inputs.level.value);
        formData.append('description', inputs.description.value);
        getRequirementPayload().forEach((requirement, index) => {
            formData.append(`requirements[${index}][badge_requirement_type_id]`, requirement.badge_requirement_type_id);
            formData.append(`requirements[${index}][requirement_value]`, requirement.requirement_value);
        });
        formData.append('point_reward', inputs.pointReward.value || 0);
        formData.append('is_limited', inputs.limited.checked ? 1 : 0);
        formData.append('start_date', inputs.startDate.value);
        formData.append('end_date', inputs.endDate.value);
        formData.append('is_active', inputs.active.checked ? 1 : 0);

        if (inputs.imageFile.files[0]) {
            formData.append('image_file', inputs.imageFile.files[0]);
        }

        const id = inputs.id.value;
        let url = urls.store;

        if (id) {
            url = `${urls.updateBase}/${id}`;
            formData.append('_method', 'PUT');
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok) {
            alert(data.message || '保存に失敗しました。');
            return;
        }

        hideModal();
        await loadBadges();
    };

    const postJson = async (url, payload = {}) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok) {
            alert(data.message || '処理に失敗しました。');
            return false;
        }

        return true;
    };

    const deleteRequest = async (url) => {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (!response.ok) {
            alert(data.message || '削除に失敗しました。');
            return false;
        }

        return true;
    };

    const reorder = async () => {
        const items = Array.from(tableBody.querySelectorAll('tr')).map((row, index) => ({
            id: Number(row.dataset.id),
            display_order: index + 1,
        })).filter(item => item.id);

        if (await postJson(urls.reorder, { items })) {
            await loadBadges();
        }
    };

    const setupSortable = () => {
        if (typeof Sortable === 'undefined') return;

        Sortable.create(tableBody, {
            handle: '.badge-drag-handle',
            animation: 150,
            onEnd: reorder,
        });
    };

    const setupSearchList = (searchInput, hiddenInput, listElement) => {
        if (!searchInput || !hiddenInput || !listElement) return;

        const buttons = Array.from(listElement.querySelectorAll('button'));

        searchInput.addEventListener('focus', () => {
            listElement.classList.add('is-open');
        });

        searchInput.addEventListener('input', () => {
            listElement.classList.add('is-open');

            const keyword = searchInput.value.toLowerCase();

            buttons.forEach(button => {
                button.style.display = button.textContent.toLowerCase().includes(keyword) ? '' : 'none';
            });
        });

        buttons.forEach(button => {
            button.addEventListener('click', () => {
                hiddenInput.value = button.dataset.id;
                searchInput.value = button.textContent.trim();
                listElement.classList.remove('is-open');

                if (hiddenInput.id.includes('Filter')) {
                    loadBadges();
                }
            });
        });

        document.addEventListener('click', (event) => {
            if (!listElement.contains(event.target) && event.target !== searchInput) {
                listElement.classList.remove('is-open');
            }
        });
    };

    setupSearchList(
        document.getElementById('badgeCategoryFilterSearchInput'),
        document.getElementById('badgeCategoryFilter'),
        document.getElementById('badgeCategoryFilterList')
    );

    setupSearchList(
        document.getElementById('badgeSeriesFilterSearchInput'),
        document.getElementById('badgeSeriesFilter'),
        document.getElementById('badgeSeriesFilterList')
    );

    setupSearchList(
        document.getElementById('badgeCategorySearchInput'),
        document.getElementById('badgeCategoryInput'),
        document.getElementById('badgeCategoryList')
    );

    setupSearchList(
        document.getElementById('badgeSeriesSearchInput'),
        document.getElementById('badgeSeriesInput'),
        document.getElementById('badgeSeriesList')
    );

    const badgeAddButton = document.getElementById('badgeAddButton');

    console.log('badgeAddButton found:', badgeAddButton);

    if (badgeAddButton) {
        badgeAddButton.addEventListener('click', () => {
            console.log('badge add clicked');
            openCreate();
        });
    }
    document.getElementById('badgeModalClose')?.addEventListener('click', hideModal);
    document.getElementById('badgeModalCancel')?.addEventListener('click', hideModal);
    document.getElementById('badgeModalSave')?.addEventListener('click', saveBadge);

    requirementAddButton?.addEventListener('click', () => {
        addRequirementRow();
    });

    filters.keyword.addEventListener('input', loadBadges);
    filters.level.addEventListener('change', loadBadges);
    filters.status.addEventListener('change', loadBadges);

    inputs.imageFile.addEventListener('change', () => {
        const file = inputs.imageFile.files[0];

        if (!file) {
            inputs.imagePreview.src = '';
            inputs.imagePreview.style.display = 'none';
            return;
        }

        inputs.imagePreview.src = URL.createObjectURL(file);
        inputs.imagePreview.style.display = 'block';
    });

    tableBody.addEventListener('click', async (event) => {
        const editButton = event.target.closest('.badge-edit-button');
        if (editButton) {
            openEdit(badges[Number(editButton.dataset.index)]);
            return;
        }

        const duplicateButton = event.target.closest('.badge-duplicate-button');
        if (duplicateButton) {
            if (await postJson(`${urls.duplicateBase}/${duplicateButton.dataset.id}/duplicate`)) {
                await loadBadges();
            }
            return;
        }

        const deactivateButton = event.target.closest('.badge-deactivate-button');
        if (deactivateButton) {
            if (await postJson(`${urls.deactivateBase}/${deactivateButton.dataset.id}/deactivate`)) {
                await loadBadges();
            }
            return;
        }

        const deleteButton = event.target.closest('.badge-delete-button');
        if (deleteButton) {
            if (!confirm('削除しますか？')) return;

            if (await deleteRequest(`${urls.deleteBase}/${deleteButton.dataset.id}`)) {
                await loadBadges();
            }
        }
    });

    checkAll?.addEventListener('change', () => {
        document.querySelectorAll('.badge-row-check').forEach(input => {
            input.checked = checkAll.checked;
        });
    });

    document.getElementById('badgeBulkDeactivateButton')?.addEventListener('click', async () => {
        const ids = selectedIds();
        if (ids.length === 0) {
            alert('対象を選択してください。');
            return;
        }

        if (await postJson(urls.bulkDeactivate, { ids })) {
            await loadBadges();
        }
    });

    document.getElementById('badgeBulkDeleteButton')?.addEventListener('click', async () => {
        const ids = selectedIds();
        if (ids.length === 0) {
            alert('対象を選択してください。');
            return;
        }

        if (!confirm('選択したバッジを削除しますか？')) return;

        if (await postJson(urls.bulkDelete, { ids })) {
            await loadBadges();
        }
    });

    loadBadges();
});