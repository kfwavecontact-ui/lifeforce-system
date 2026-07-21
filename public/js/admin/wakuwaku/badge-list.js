document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.badge-list-page');
    if (!page) return;

    const csrfToken = page.dataset.csrfToken;
    const urls = {
        list: page.dataset.listUrl,
        store: page.dataset.storeUrl,
        updateBase: page.dataset.updateUrlBase,
        reorder: page.dataset.reorderUrl,
        duplicateBase: page.dataset.duplicateUrlBase,
        toggleActiveBase: page.dataset.toggleActiveUrlBase,
        deleteBase: page.dataset.deleteUrlBase,
        bulkActivate: page.dataset.bulkActivateUrl,
        bulkDeactivate: page.dataset.bulkDeactivateUrl,
    };

    const tableBody = document.getElementById('badgeTableBody');
    const checkAll = document.getElementById('badgeCheckAll');
    const selectedCount = document.getElementById('badgeSelectedCount');
    const modal = document.getElementById('badgeModal');
    const modalTitle = document.getElementById('badgeModalTitle');
    const conditionPopover = document.getElementById('badgeConditionPopover');
    const conditionPopoverBody = document.getElementById('badgeConditionPopoverBody');
    const actionMenu = document.createElement('div');
    actionMenu.className = 'badge-action-menu badge-action-menu-portal';
    actionMenu.hidden = true;
    document.body.appendChild(actionMenu);
    let badges = [];
    let searchTimer = null;
    let sortableInstance = null;
    let croppedImageBlob = null;
    const toast = document.getElementById('badgeToast');

    const inputs = {
        id: document.getElementById('badgeIdInput'), code: document.getElementById('badgeCodeInput'),
        name: document.getElementById('badgeNameInput'), category: document.getElementById('badgeCategoryInput'),
        series: document.getElementById('badgeSeriesInput'), level: document.getElementById('badgeLevelInput'),
        description: document.getElementById('badgeDescriptionInput'), imageFile: document.getElementById('badgeImageFileInput'),
        imagePreview: document.getElementById('badgeImagePreview'), pointReward: document.getElementById('badgePointRewardInput'),
        limited: document.getElementById('badgeLimitedInput'), startDate: document.getElementById('badgeStartDateInput'),
        endDate: document.getElementById('badgeEndDateInput'), active: document.getElementById('badgeActiveInput'),
        acquisitionMessage: document.getElementById('badgeAcquisitionMessageInput'), grantMethod: document.getElementById('badgeGrantMethodInput'),
        allowRegrant: document.getElementById('badgeAllowRegrantInput'), notifyOnGrant: document.getElementById('badgeNotifyOnGrantInput'),
        categorySearch: document.getElementById('badgeCategorySearchInput'), seriesSearch: document.getElementById('badgeSeriesSearchInput'),
        conditionOperator: () => document.querySelector('input[name="badgeConditionOperator"]:checked')?.value || 'and',
    };

    const filters = {
        keyword: document.getElementById('badgeSearchInput'), category: document.getElementById('badgeCategoryFilter'),
        series: document.getElementById('badgeSeriesFilter'), level: document.getElementById('badgeLevelFilter'),
        grantMethod: document.getElementById('badgeGrantMethodFilter'), limited: document.getElementById('badgeLimitedFilter'),
        status: document.getElementById('badgeStatusFilter'),
        requirementStatus: document.getElementById('badgeRequirementStatusFilter'),
        usageStatus: document.getElementById('badgeUsageStatusFilter'),
        pointMin: document.getElementById('badgePointMinFilter'),
        pointMax: document.getElementById('badgePointMaxFilter'),
        sort: document.getElementById('badgeSortFilter'),
    };

    // 列ヘッダーから選択したソート値を保持する。
    // selectに存在しない値を直接代入すると空文字になるため、専用変数で管理する。
    let currentSort = filters.sort?.value || 'display_order';

    const requirementRows = document.getElementById('badgeRequirementRows');
    const requirementRowTemplate = document.getElementById('badgeRequirementRowTemplate');
    const requirementAddButton = document.getElementById('badgeRequirementAddButton');

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

    const showToast = (message, type = 'success') => {
        if (!toast) return;
        toast.textContent = message;
        toast.className = `badge-toast is-visible is-${type}`;
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
    };

    const setTrend = (id, current, previous, suffix = '件') => {
        const target = document.getElementById(id);
        if (!target) return;
        const difference = Number(current || 0) - Number(previous || 0);
        target.className = `badge-summary-trend ${difference > 0 ? 'is-up' : difference < 0 ? 'is-down' : 'is-flat'}`;
        target.textContent = difference === 0 ? '前月比 ±0' : `前月比 ${difference > 0 ? '+' : ''}${difference}${suffix}`;
    };

    const showModal = () => { modal.style.display = 'flex'; modal.classList.add('open', 'is-open'); };
    const hideModal = () => { modal.style.display = 'none'; modal.classList.remove('open', 'is-open'); };

    const selectedIds = () => Array.from(document.querySelectorAll('.badge-row-check:checked')).map(input => Number(input.value));
    const updateSelectedCount = () => {
        const count = selectedIds().length;
        if (selectedCount) { selectedCount.textContent = `${count}件選択中`; selectedCount.classList.toggle('is-active', count > 0); }
        if (checkAll) checkAll.checked = badges.length > 0 && count === badges.length;
    };

    const buildQuery = () => {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, input]) => {
            const queryKey = { category: 'category_id', series: 'series_id', grantMethod: 'grant_method', requirementStatus: 'requirement_status', usageStatus: 'usage_status', pointMin: 'point_min', pointMax: 'point_max' }[key] || key;
            const emptyValueKeys = ['keyword', 'pointMin', 'pointMax'];
            const fallbackValue = emptyValueKeys.includes(key) ? '' : 'all';
            const value = key === 'sort' ? currentSort : (input?.value ?? fallbackValue);
            params.set(queryKey, value);
        });
        return params.toString();
    };

    const levelLabel = level => `<span class="badge-level-label" data-level="${escapeHtml(level || 1)}">Lv${escapeHtml(level || 1)}</span>`;

    const requirementText = item => {
        const name = item.requirement_type_name || item.requirement_type || '条件';
        return item.requirement_value ? `${name}：${item.requirement_value}` : name;
    };

    const closeActionMenus = () => {
        actionMenu.hidden = true;
        actionMenu.classList.remove('is-open');
        actionMenu.innerHTML = '';
    };

    const openActionMenu = (trigger, index) => {
        const badge = badges[index];
        if (!badge) return;
        actionMenu.innerHTML = `
            <button type="button" class="badge-edit-button" data-index="${index}">編集</button>
            <button type="button" class="badge-duplicate-button" data-id="${badge.id}">複製</button>
            <button type="button" class="badge-toggle-active-button" data-id="${badge.id}">${badge.is_active ? '無効化' : '有効化'}</button>
            <button type="button" class="badge-delete-button is-danger" data-id="${badge.id}">削除</button>`;
        const rect = trigger.getBoundingClientRect();
        actionMenu.hidden = false;
        actionMenu.classList.add('is-open');
        const menuWidth = 140;
        const menuHeight = actionMenu.offsetHeight || 170;
        const left = Math.min(Math.max(8, rect.right - menuWidth), window.innerWidth - menuWidth - 8);
        const top = rect.bottom + menuHeight + 8 <= window.innerHeight
            ? rect.bottom + 6
            : Math.max(8, rect.top - menuHeight - 6);
        actionMenu.style.left = `${left}px`;
        actionMenu.style.top = `${top}px`;
    };

    const renderRows = () => {
        if (!badges.length) {
            tableBody.innerHTML = '<tr><td colspan="13" class="badge-table-message">該当するバッジがありません。</td></tr>';
            updateSelectedCount();
            return;
        }

        tableBody.innerHTML = badges.map((badge, index) => {
            const image = badge.image_path
                ? `<img src="${escapeHtml(badge.image_path)}" class="badge-table-image" alt="${escapeHtml(badge.name)}">`
                : '<span class="badge-default-image" aria-label="デフォルト画像"><i class="fas fa-medal"></i></span>';
            const requirements = badge.requirements || [];
            const conditionTitle = requirements.length ? requirements.map(requirementText).join(' / ') : '獲得条件は未設定です';
            const methodLabel = { auto: '自動', manual: '手動', both: '自動・手動' }[badge.grant_method] || '-';

            return `<tr data-id="${badge.id}">
                <td><input type="checkbox" class="badge-row-check" value="${badge.id}"></td>
                <td><div class="badge-order-controls"><button type="button" class="badge-order-button badge-move-up" data-index="${index}" title="上へ移動"><i class="fas fa-chevron-up"></i></button><span class="badge-drag-handle" title="ドラッグして並び替え"><i class="fas fa-grip-vertical"></i></span><button type="button" class="badge-order-button badge-move-down" data-index="${index}" title="下へ移動"><i class="fas fa-chevron-down"></i></button></div></td>
                <td>${badge.id}</td>
                <td><div class="badge-cell">${image}<div><strong class="badge-name">${escapeHtml(badge.name)}</strong><div class="badge-code">${escapeHtml(badge.code || '')}</div><div class="badge-subline"><span>${escapeHtml(methodLabel)}</span>${badge.is_limited ? '<span class="badge-limited-label">限定</span>' : ''}</div></div></div></td>
                <td><span class="badge-category">${escapeHtml(badge.category_name || '-')}</span></td>
                <td>${badge.series_name ? `<span class="badge-series-name">${escapeHtml(badge.series_name)}</span>` : '<span class="badge-unset">未設定</span>'}</td>
                <td>${levelLabel(badge.level)}</td>
                <td><button type="button" class="badge-condition-button ${requirements.length ? '' : 'is-empty'}" data-index="${index}" title="獲得条件を見る：${escapeHtml(conditionTitle)}" aria-label="獲得条件を見る"><i class="fas fa-clipboard-list"></i></button></td>
                <td>${Number(badge.point_reward || 0).toLocaleString()}</td>
                <td>${Number(badge.active_holder_count || 0).toLocaleString()}</td>
                <td>${Number(badge.cumulative_grant_count || 0).toLocaleString()}</td>
                <td><span class="badge-status-group"><span class="badge-status ${badge.is_active ? 'is-active' : 'is-inactive'}">${badge.is_active ? '有効' : '無効'}</span>${badge.is_limited ? '<span class="badge-status is-limited">限定</span>' : ''}</span></td>
                <td class="badge-operation-cell"><button type="button" class="badge-action-trigger" data-index="${index}" aria-label="操作メニュー"><i class="fas fa-ellipsis-v"></i><span>操作</span></button></td>
            </tr>`;
        }).join('');
        updateSelectedCount();
        setupSortable();
    };

    const loadBadges = async () => {
        tableBody.innerHTML = '<tr><td colspan="13" class="badge-table-message">読み込み中...</td></tr>';
        try {
            const response = await fetch(`${urls.list}?${buildQuery()}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || '一覧の取得に失敗しました。');
            badges = data.rows || [];
            document.getElementById('badgeTotalCount').textContent = data.summary.total || 0;
            document.getElementById('badgeCurrentHoldingCount').textContent = data.summary.current_holdings || 0;
            document.getElementById('badgeMonthlyGrantCount').textContent = data.summary.monthly_grants || 0;
            document.getElementById('badgeMonthlyRemovalCount').textContent = data.summary.monthly_removals || 0;
            document.getElementById('badgeTotalTrend').textContent = '登録済みマスタ';
            document.getElementById('badgeHoldingTrend').textContent = '現在付与中';
            setTrend('badgeGrantTrend', data.summary.monthly_grants, data.summary.previous_month_grants);
            setTrend('badgeRemovalTrend', data.summary.monthly_removals, data.summary.previous_month_removals);
            renderRows();
            updateHeaderSortIndicators();
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="13" class="badge-table-message">${escapeHtml(error.message)}</td></tr>`;
        }
    };

    const addRequirementRow = (typeId = '', value = '') => {
        if (!requirementRows || !requirementRowTemplate) return;
        if (requirementRows.querySelectorAll('.badge-requirement-row').length >= 3) return;
        const fragment = requirementRowTemplate.content.cloneNode(true);
        const row = fragment.querySelector('.badge-requirement-row');
        fragment.querySelector('.badgeRequirementTypeInput').value = typeId || '';
        fragment.querySelector('.badgeRequirementValueInput').value = value || '';
        fragment.querySelector('.badgeRequirementRemoveButton').addEventListener('click', () => {
            row.remove();
            if (!requirementRows.querySelector('.badge-requirement-row')) addRequirementRow();
            updateRequirementAddButton();
        });
        requirementRows.appendChild(fragment);
        updateRequirementAddButton();
    };

    const updateRequirementAddButton = () => {
        if (requirementAddButton) requirementAddButton.disabled = requirementRows.querySelectorAll('.badge-requirement-row').length >= 3;
    };

    const getRequirementPayload = () => Array.from(requirementRows.querySelectorAll('.badge-requirement-row')).map(row => ({
        badge_requirement_type_id: row.querySelector('.badgeRequirementTypeInput')?.value || '',
        requirement_value: row.querySelector('.badgeRequirementValueInput')?.value || '',
    })).filter(item => item.badge_requirement_type_id);

    const resetForm = () => {
        inputs.id.value = ''; inputs.code.value = '保存時に自動採番'; inputs.name.value = ''; inputs.category.value = ''; inputs.series.value = '';
        inputs.level.value = '1'; inputs.description.value = ''; inputs.acquisitionMessage.value = ''; inputs.grantMethod.value = 'both';
        inputs.allowRegrant.checked = true; inputs.notifyOnGrant.checked = true; inputs.imageFile.value = ''; croppedImageBlob = null;
        inputs.imagePreview.src = ''; inputs.imagePreview.style.display = 'none';
        document.querySelector('input[name="badgeConditionOperator"][value="and"]').checked = true; inputs.pointReward.value = 0;
        inputs.limited.checked = false; inputs.startDate.value = ''; inputs.endDate.value = ''; inputs.active.checked = true;
        inputs.categorySearch.value = ''; inputs.seriesSearch.value = ''; requirementRows.innerHTML = ''; addRequirementRow();
    };

    const openCreate = () => { resetForm(); modalTitle.textContent = 'バッジを追加'; showModal(); };
    const openEdit = badge => {
        resetForm();
        inputs.id.value = badge.id; inputs.code.value = badge.code || ''; inputs.name.value = badge.name || '';
        inputs.category.value = badge.category_id || ''; inputs.series.value = badge.series_id || ''; inputs.level.value = badge.level || 1;
        inputs.description.value = badge.description || ''; inputs.acquisitionMessage.value = badge.acquisition_message || '';
        inputs.grantMethod.value = badge.grant_method || 'both'; inputs.allowRegrant.checked = !!badge.allow_regrant;
        inputs.notifyOnGrant.checked = !!badge.notify_on_grant; inputs.pointReward.value = badge.point_reward || 0;
        inputs.limited.checked = !!badge.is_limited; inputs.startDate.value = badge.start_date || ''; inputs.endDate.value = badge.end_date || '';
        inputs.active.checked = !!badge.is_active; inputs.categorySearch.value = badge.category_name || ''; inputs.seriesSearch.value = badge.series_name || '';
        requirementRows.innerHTML = '';
        (badge.requirements?.length ? badge.requirements : [{ badge_requirement_type_id: '', requirement_value: '' }]).forEach(item => addRequirementRow(item.badge_requirement_type_id, item.requirement_value));
        if (badge.image_path) { inputs.imagePreview.src = badge.image_path; inputs.imagePreview.style.display = 'block'; }
        modalTitle.textContent = 'バッジを編集'; showModal();
    };

    const saveBadge = async () => {
        const formData = new FormData();
        const values = {
            badge_category_id: inputs.category.value, badge_series_id: inputs.series.value,
            name: inputs.name.value, level: inputs.level.value, description: inputs.description.value,
            acquisition_message: inputs.acquisitionMessage.value, grant_method: inputs.grantMethod.value,
            allow_regrant: inputs.allowRegrant.checked ? 1 : 0, notify_on_grant: inputs.notifyOnGrant.checked ? 1 : 0,
            point_reward: inputs.pointReward.value || 0, is_limited: inputs.limited.checked ? 1 : 0,
            start_date: inputs.startDate.value, end_date: inputs.endDate.value, is_active: inputs.active.checked ? 1 : 0,
            condition_operator: inputs.conditionOperator(),
        };
        Object.entries(values).forEach(([key, value]) => formData.append(key, value));
        getRequirementPayload().forEach((item, index) => {
            formData.append(`requirements[${index}][badge_requirement_type_id]`, item.badge_requirement_type_id);
            formData.append(`requirements[${index}][requirement_value]`, item.requirement_value);
        });
        if (croppedImageBlob) {
            formData.append('image_file', croppedImageBlob, 'badge.png');
        } else if (inputs.imageFile.files[0]) {
            formData.append('image_file', inputs.imageFile.files[0]);
        }
        let url = urls.store;
        if (inputs.id.value) { url = `${urls.updateBase}/${inputs.id.value}`; formData.append('_method', 'PUT'); }
        const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: formData });
        const data = await response.json();
        if (!response.ok) { showToast(data.message || '保存に失敗しました。', 'error'); return; }
        const savedName = inputs.name.value; hideModal(); await loadBadges(); showToast(`${savedName}を${inputs.id.value ? '更新' : '登録'}しました。`);
    };

    const postJson = async (url, payload = {}) => {
        const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(payload) });
        const data = await response.json();
        if (!response.ok) { showToast(data.message || '処理に失敗しました。', 'error'); return false; }
        showToast(data.message || '処理が完了しました。'); return true;
    };

    const deleteRequest = async url => {
        const response = await fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) { showToast(data.message || '削除に失敗しました。', 'error'); return false; }
        showToast(data.message || '削除しました。'); return true;
    };

    const reorder = async () => {
        const items = Array.from(tableBody.querySelectorAll('tr[data-id]')).map((row, index) => ({ id: Number(row.dataset.id), display_order: index + 1 }));
        if (items.length && await postJson(urls.reorder, { items })) await loadBadges();
    };
    const setupSortable = () => { if (typeof Sortable === 'undefined') return; sortableInstance?.destroy(); sortableInstance = Sortable.create(tableBody, { handle: '.badge-drag-handle', animation: 150, onEnd: reorder }); };

    const setupSearchList = (searchInput, hiddenInput, listElement) => {
        if (!searchInput || !hiddenInput || !listElement) return;
        const buttons = Array.from(listElement.querySelectorAll('button'));
        searchInput.addEventListener('focus', () => listElement.classList.add('is-open'));
        searchInput.addEventListener('input', () => {
            listElement.classList.add('is-open'); const keyword = searchInput.value.toLowerCase();
            buttons.forEach(button => button.style.display = button.textContent.toLowerCase().includes(keyword) ? '' : 'none');
        });
        buttons.forEach(button => button.addEventListener('click', () => {
            hiddenInput.value = button.dataset.id; searchInput.value = button.textContent.trim(); listElement.classList.remove('is-open');
            if (hiddenInput.id.includes('Filter')) loadBadges();
        }));
        document.addEventListener('click', event => { if (!listElement.contains(event.target) && event.target !== searchInput) listElement.classList.remove('is-open'); });
    };

    setupSearchList(document.getElementById('badgeCategoryFilterSearchInput'), filters.category, document.getElementById('badgeCategoryFilterList'));
    setupSearchList(document.getElementById('badgeSeriesFilterSearchInput'), filters.series, document.getElementById('badgeSeriesFilterList'));
    setupSearchList(inputs.categorySearch, inputs.category, document.getElementById('badgeCategoryList'));
    setupSearchList(inputs.seriesSearch, inputs.series, document.getElementById('badgeSeriesList'));

    document.getElementById('badgeAddButton')?.addEventListener('click', openCreate);
    document.getElementById('badgeModalClose')?.addEventListener('click', hideModal);
    document.getElementById('badgeModalCancel')?.addEventListener('click', hideModal);
    document.getElementById('badgeModalSave')?.addEventListener('click', saveBadge);
    requirementAddButton?.addEventListener('click', () => addRequirementRow());
    document.getElementById('badgeConditionPopoverClose')?.addEventListener('click', () => conditionPopover.hidden = true);

    filters.keyword.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadBadges, 500); });
    [filters.level, filters.grantMethod, filters.limited, filters.status, filters.requirementStatus, filters.usageStatus].forEach(input => input?.addEventListener('change', loadBadges));
    [filters.pointMin, filters.pointMax].forEach(input => input?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadBadges, 500); }));

    document.getElementById('badgeFilterClearButton')?.addEventListener('click', () => {
        filters.keyword.value = '';
        filters.category.value = 'all'; filters.series.value = 'all';
        filters.level.value = 'all'; filters.grantMethod.value = 'all'; filters.limited.value = 'all'; filters.status.value = 'all';
        filters.requirementStatus.value = 'all'; filters.usageStatus.value = 'all'; filters.pointMin.value = ''; filters.pointMax.value = ''; currentSort = 'display_order'; filters.sort.value = 'display_order';
        document.getElementById('badgeCategoryFilterSearchInput').value = '';
        document.getElementById('badgeSeriesFilterSearchInput').value = '';
        updateHeaderSortIndicators(); loadBadges(); showToast('検索条件をクリアしました。');
    });


    const headerSortValue = (key, direction) => ({
        id: `id_${direction}`,
        name: `name_${direction}`,
        category: `category_${direction}`,
        series: `series_${direction}`,
        level: `level_${direction}`,
        points: `points_${direction}`,
        holders: `holders_${direction}`,
        grants: `grants_${direction}`,
        status: `status_${direction}`,
    }[key]);

    const updateHeaderSortIndicators = () => {
        document.querySelectorAll('.badge-sortable-header').forEach(header => {
            const indicator = header.querySelector('.badge-sort-indicator');
            if (!indicator) return;
            const current = currentSort;
            const asc = headerSortValue(header.dataset.sortKey, 'asc');
            const desc = headerSortValue(header.dataset.sortKey, 'desc');
            header.classList.toggle('is-sorted', current === asc || current === desc);
            indicator.textContent = current === asc ? '↑' : current === desc ? '↓' : '↕';
        });
    };

    document.querySelectorAll('.badge-sortable-header').forEach(header => {
        header.addEventListener('click', () => {
            const key = header.dataset.sortKey;
            const asc = headerSortValue(key, 'asc');
            const desc = headerSortValue(key, 'desc');
            currentSort = currentSort === asc ? desc : asc;
            updateHeaderSortIndicators();
            loadBadges();
        });
    });

    filters.sort?.addEventListener('change', () => {
        currentSort = filters.sort.value || 'display_order';
        updateHeaderSortIndicators();
        loadBadges();
    });

    document.querySelectorAll('.badge-modal-tab').forEach(tab => tab.addEventListener('click', () => {
        document.querySelectorAll('.badge-modal-tab').forEach(item => item.classList.toggle('is-active', item === tab));
        document.querySelectorAll('.badge-modal-panel').forEach(panel => panel.classList.toggle('is-active', panel.dataset.panel === tab.dataset.tab));
    }));

    const cropModal = document.getElementById('badgeCropModal');
    const cropCanvas = document.getElementById('badgeCropCanvas');
    const cropContext = cropCanvas?.getContext('2d');
    const cropZoom = document.getElementById('badgeCropZoom');
    const cropState = { image: null, zoom: 1, baseScale: 1, x: 0, y: 0, dragging: false, startX: 0, startY: 0, originX: 0, originY: 0 };

    const clampCropPosition = () => {
        if (!cropState.image) return;
        const scale = cropState.baseScale * cropState.zoom;
        const width = cropState.image.width * scale;
        const height = cropState.image.height * scale;
        cropState.x = Math.min(0, Math.max(cropCanvas.width - width, cropState.x));
        cropState.y = Math.min(0, Math.max(cropCanvas.height - height, cropState.y));
    };

    const renderCrop = () => {
        if (!cropContext || !cropState.image) return;
        clampCropPosition();
        cropContext.clearRect(0, 0, cropCanvas.width, cropCanvas.height);
        const scale = cropState.baseScale * cropState.zoom;
        cropContext.drawImage(cropState.image, cropState.x, cropState.y, cropState.image.width * scale, cropState.image.height * scale);
    };

    const openCropModal = file => {
        const image = new Image();
        image.onload = () => {
            cropState.image = image;
            cropState.zoom = 1;
            cropZoom.value = '1';
            cropState.baseScale = Math.max(cropCanvas.width / image.width, cropCanvas.height / image.height);
            const width = image.width * cropState.baseScale;
            const height = image.height * cropState.baseScale;
            cropState.x = (cropCanvas.width - width) / 2;
            cropState.y = (cropCanvas.height - height) / 2;
            renderCrop();
            cropModal.style.display = 'flex';
            cropModal.classList.add('open', 'is-open');
            cropModal.setAttribute('aria-hidden', 'false');
        };
        image.src = URL.createObjectURL(file);
    };

    const closeCropModal = resetFile => {
        cropModal.style.display = 'none';
        cropModal.classList.remove('open', 'is-open');
        cropModal.setAttribute('aria-hidden', 'true');
        if (resetFile) inputs.imageFile.value = '';
    };

    inputs.imageFile.addEventListener('change', () => {
        const file = inputs.imageFile.files[0];
        if (!file) return;
        croppedImageBlob = null;
        openCropModal(file);
    });

    cropZoom?.addEventListener('input', () => {
        if (!cropState.image) return;
        const previousScale = cropState.baseScale * cropState.zoom;
        const centerSourceX = (cropCanvas.width / 2 - cropState.x) / previousScale;
        const centerSourceY = (cropCanvas.height / 2 - cropState.y) / previousScale;
        cropState.zoom = Number(cropZoom.value);
        const nextScale = cropState.baseScale * cropState.zoom;
        cropState.x = cropCanvas.width / 2 - centerSourceX * nextScale;
        cropState.y = cropCanvas.height / 2 - centerSourceY * nextScale;
        renderCrop();
    });

    cropCanvas?.addEventListener('pointerdown', event => {
        cropState.dragging = true;
        cropState.startX = event.clientX;
        cropState.startY = event.clientY;
        cropState.originX = cropState.x;
        cropState.originY = cropState.y;
        cropCanvas.setPointerCapture(event.pointerId);
    });
    cropCanvas?.addEventListener('pointermove', event => {
        if (!cropState.dragging) return;
        cropState.x = cropState.originX + event.clientX - cropState.startX;
        cropState.y = cropState.originY + event.clientY - cropState.startY;
        renderCrop();
    });
    cropCanvas?.addEventListener('pointerup', () => { cropState.dragging = false; });
    cropCanvas?.addEventListener('pointercancel', () => { cropState.dragging = false; });

    document.getElementById('badgeCropApply')?.addEventListener('click', () => {
        if (!cropState.image) return;
        const output = document.createElement('canvas');
        output.width = 800;
        output.height = 800;
        const outputContext = output.getContext('2d');
        const scale = cropState.baseScale * cropState.zoom;
        const sourceX = Math.max(0, -cropState.x / scale);
        const sourceY = Math.max(0, -cropState.y / scale);
        const sourceSize = cropCanvas.width / scale;
        outputContext.drawImage(cropState.image, sourceX, sourceY, sourceSize, sourceSize, 0, 0, 800, 800);
        output.toBlob(blob => {
            croppedImageBlob = blob;
            inputs.imagePreview.src = URL.createObjectURL(blob);
            inputs.imagePreview.style.display = 'block';
            closeCropModal(false);
        }, 'image/png', 0.95);
    });
    document.getElementById('badgeCropCancel')?.addEventListener('click', () => closeCropModal(true));
    document.getElementById('badgeCropClose')?.addEventListener('click', () => closeCropModal(true));

    document.getElementById('badgeCsvExportButton')?.addEventListener('click', () => {
        const params = new URLSearchParams(buildQuery());
        params.set('export', '1');
        window.location.href = `${urls.list}?${params.toString()}`;
        showToast('現在の絞り込み条件でCSVを出力します。');
    });

    tableBody.addEventListener('change', event => { if (event.target.matches('.badge-row-check')) updateSelectedCount(); });
    tableBody.addEventListener('click', async event => {
        const moveButton = event.target.closest('.badge-order-button');
        if (moveButton) {
            const index = Number(moveButton.dataset.index);
            const nextIndex = moveButton.classList.contains('badge-move-up') ? index - 1 : index + 1;
            if (nextIndex >= 0 && nextIndex < badges.length) {
                [badges[index], badges[nextIndex]] = [badges[nextIndex], badges[index]];
                renderRows(); await reorder(); showToast('並び順を更新しました。');
            }
            return;
        }
        const actionTrigger = event.target.closest('.badge-action-trigger');
        if (actionTrigger) {
            const index = Number(actionTrigger.dataset.index);
            const sameMenu = actionMenu.classList.contains('is-open') && actionMenu.dataset.index === String(index);
            closeActionMenus();
            if (!sameMenu) {
                actionMenu.dataset.index = String(index);
                openActionMenu(actionTrigger, index);
            }
            return;
        }
        const conditionButton = event.target.closest('.badge-condition-button');
        if (conditionButton) {
            const badge = badges[Number(conditionButton.dataset.index)]; const requirements = badge.requirements || [];
            conditionPopoverBody.innerHTML = requirements.length ? `<div class="badge-condition-list">${requirements.map(item => `<div class="badge-condition-item">${escapeHtml(requirementText(item))}</div>`).join('')}</div>` : '<div class="badge-condition-empty">獲得条件は未設定です。</div>';
            const rect = conditionButton.getBoundingClientRect(); conditionPopover.style.left = `${Math.min(rect.left, window.innerWidth - 356)}px`; conditionPopover.style.top = `${Math.min(rect.bottom + 8, window.innerHeight - 220)}px`; conditionPopover.hidden = false; return;
        }
        const edit = event.target.closest('.badge-edit-button'); if (edit) { openEdit(badges[Number(edit.dataset.index)]); closeActionMenus(); return; }
        const duplicate = event.target.closest('.badge-duplicate-button'); if (duplicate && await postJson(`${urls.duplicateBase}/${duplicate.dataset.id}/duplicate`)) { closeActionMenus(); await loadBadges(); return; }
        const toggle = event.target.closest('.badge-toggle-active-button'); if (toggle && await postJson(`${urls.toggleActiveBase}/${toggle.dataset.id}/toggle-active`)) { closeActionMenus(); await loadBadges(); return; }
        const remove = event.target.closest('.badge-delete-button');
        if (remove) { if (!confirm('このバッジを削除しますか？')) return; if (await deleteRequest(`${urls.deleteBase}/${remove.dataset.id}`)) { closeActionMenus(); await loadBadges(); } }
    });

    checkAll?.addEventListener('change', () => { document.querySelectorAll('.badge-row-check').forEach(input => input.checked = checkAll.checked); updateSelectedCount(); });
    document.getElementById('badgeBulkActivateButton')?.addEventListener('click', async () => {
        const ids = selectedIds(); if (!ids.length) return showToast('対象を選択してください。', 'error'); if (await postJson(urls.bulkActivate, { ids })) await loadBadges();
    });
    document.getElementById('badgeBulkDeactivateButton')?.addEventListener('click', async () => {
        const ids = selectedIds(); if (!ids.length) return showToast('対象を選択してください。', 'error'); if (await postJson(urls.bulkDeactivate, { ids })) await loadBadges();
    });

    document.addEventListener('click', async event => {
        if (!event.target.closest('.badge-action-trigger') && !event.target.closest('.badge-action-menu-portal')) closeActionMenus();
        if (!event.target.closest('.badge-condition-button') && !event.target.closest('#badgeConditionPopover')) conditionPopover.hidden = true;

        const edit = event.target.closest('.badge-action-menu-portal .badge-edit-button');
        if (edit) { openEdit(badges[Number(edit.dataset.index)]); closeActionMenus(); return; }
        const duplicate = event.target.closest('.badge-action-menu-portal .badge-duplicate-button');
        if (duplicate && await postJson(`${urls.duplicateBase}/${duplicate.dataset.id}/duplicate`)) { closeActionMenus(); await loadBadges(); return; }
        const toggle = event.target.closest('.badge-action-menu-portal .badge-toggle-active-button');
        if (toggle && await postJson(`${urls.toggleActiveBase}/${toggle.dataset.id}/toggle-active`)) { closeActionMenus(); await loadBadges(); return; }
        const remove = event.target.closest('.badge-action-menu-portal .badge-delete-button');
        if (remove) {
            if (!confirm('このバッジを削除しますか？')) return;
            if (await deleteRequest(`${urls.deleteBase}/${remove.dataset.id}`)) { closeActionMenus(); await loadBadges(); }
        }
    });

    window.addEventListener('resize', closeActionMenus);
    document.querySelector('.badge-table-scroll')?.addEventListener('scroll', closeActionMenus);

    loadBadges();
});
