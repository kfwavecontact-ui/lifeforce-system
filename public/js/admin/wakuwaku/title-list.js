document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.title-list-page');
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

    const tableBody = document.getElementById('titleTableBody');
    const checkAll = document.getElementById('titleCheckAll');
    const selectedCount = document.getElementById('titleSelectedCount');
    const modal = document.getElementById('titleModal');
    const modalTitle = document.getElementById('titleModalTitle');
    const conditionPopover = document.getElementById('titleConditionPopover');
    const conditionPopoverBody = document.getElementById('titleConditionPopoverBody');
    const actionMenu = document.createElement('div');
    actionMenu.className = 'title-action-menu title-action-menu-portal';
    actionMenu.hidden = true;
    document.body.appendChild(actionMenu);
    let titles = [];
    let searchTimer = null;
    let sortableInstance = null;
    let croppedImageBlob = null;
    const toast = document.getElementById('titleToast');

    const inputs = {
        id: document.getElementById('titleIdInput'), code: document.getElementById('titleCodeInput'),
        name: document.getElementById('titleNameInput'), category: document.getElementById('titleCategoryInput'),
        series: document.getElementById('titleSeriesInput'), level: document.getElementById('titleLevelInput'),
        description: document.getElementById('titleDescriptionInput'), imageFile: document.getElementById('titleImageFileInput'),
        imagePreview: document.getElementById('titleImagePreview'), pointReward: document.getElementById('titlePointRewardInput'),
        limited: document.getElementById('titleLimitedInput'), startDate: document.getElementById('titleStartDateInput'),
        endDate: document.getElementById('titleEndDateInput'), active: document.getElementById('titleActiveInput'),
        acquisitionMessage: document.getElementById('titleAcquisitionMessageInput'), grantMethod: document.getElementById('titleGrantMethodInput'),
        allowRegrant: document.getElementById('titleAllowRegrantInput'), notifyOnGrant: document.getElementById('titleNotifyOnGrantInput'),
        categorySearch: document.getElementById('titleCategorySearchInput'), seriesSearch: document.getElementById('titleSeriesSearchInput'),
        conditionOperator: () => document.querySelector('input[name="titleConditionOperator"]:checked')?.value || 'and',
    };

    const filters = {
        keyword: document.getElementById('titleSearchInput'), category: document.getElementById('titleCategoryFilter'),
        series: document.getElementById('titleSeriesFilter'), level: document.getElementById('titleLevelFilter'),
        grantMethod: document.getElementById('titleGrantMethodFilter'), limited: document.getElementById('titleLimitedFilter'),
        status: document.getElementById('titleStatusFilter'),
        requirementStatus: document.getElementById('titleRequirementStatusFilter'),
        usageStatus: document.getElementById('titleUsageStatusFilter'),
        pointMin: document.getElementById('titlePointMinFilter'),
        pointMax: document.getElementById('titlePointMaxFilter'),
        sort: document.getElementById('titleSortFilter'),
    };

    // 列ヘッダーから選択したソート値を保持する。
    // selectに存在しない値を直接代入すると空文字になるため、専用変数で管理する。
    let currentSort = filters.sort?.value || 'display_order';

    const requirementRows = document.getElementById('titleRequirementRows');
    const requirementRowTemplate = document.getElementById('titleRequirementRowTemplate');
    const requirementAddButton = document.getElementById('titleRequirementAddButton');

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

    const showToast = (message, type = 'success') => {
        if (!toast) return;
        toast.textContent = message;
        toast.className = `title-toast is-visible is-${type}`;
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
    };

    const setTrend = (id, current, previous, suffix = '件') => {
        const target = document.getElementById(id);
        if (!target) return;
        const difference = Number(current || 0) - Number(previous || 0);
        target.className = `title-summary-trend ${difference > 0 ? 'is-up' : difference < 0 ? 'is-down' : 'is-flat'}`;
        target.textContent = difference === 0 ? '前月比 ±0' : `前月比 ${difference > 0 ? '+' : ''}${difference}${suffix}`;
    };

    const showModal = () => { modal.style.display = 'flex'; modal.classList.add('open', 'is-open'); };
    const hideModal = () => { modal.style.display = 'none'; modal.classList.remove('open', 'is-open'); };

    const selectedIds = () => Array.from(document.querySelectorAll('.title-row-check:checked')).map(input => Number(input.value));
    const updateSelectedCount = () => {
        const count = selectedIds().length;
        if (selectedCount) { selectedCount.textContent = `${count}件選択中`; selectedCount.classList.toggle('is-active', count > 0); }
        if (checkAll) checkAll.checked = titles.length > 0 && count === titles.length;
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

    const levelLabel = level => { const rank = Math.min(5, Math.max(1, Number(level) || 1)); return `<span class="title-level-label" data-level="${rank}">★${rank}</span>`; };

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
        const title = titles[index];
        if (!title) return;
        actionMenu.innerHTML = `
            <button type="button" class="title-edit-button" data-index="${index}">編集</button>
            <button type="button" class="title-duplicate-button" data-id="${title.id}">複製</button>
            <button type="button" class="title-toggle-active-button" data-id="${title.id}">${title.is_active ? '無効化' : '有効化'}</button>
            <button type="button" class="title-delete-button is-danger" data-id="${title.id}">削除</button>`;
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
        if (!titles.length) {
            tableBody.innerHTML = '<tr><td colspan="13" class="title-table-message">該当する称号がありません。</td></tr>';
            updateSelectedCount();
            return;
        }

        tableBody.innerHTML = titles.map((title, index) => {
            const image = title.image_path
                ? `<img src="${escapeHtml(title.image_path)}" class="title-table-image" alt="${escapeHtml(title.name)}">`
                : '<span class="title-default-image" aria-label="デフォルト画像"><i class="fas fa-crown"></i></span>';
            const requirements = title.requirements || [];
            const conditionTitle = requirements.length ? requirements.map(requirementText).join(' / ') : '獲得条件は未設定です';
            const methodLabel = { auto: '自動', manual: '手動', both: '自動・手動' }[title.grant_method] || '-';

            return `<tr data-id="${title.id}">
                <td><input type="checkbox" class="title-row-check" value="${title.id}"></td>
                <td><div class="title-order-controls"><button type="button" class="title-order-button title-move-up" data-index="${index}" title="上へ移動"><i class="fas fa-chevron-up"></i></button><span class="title-drag-handle" title="ドラッグして並び替え"><i class="fas fa-grip-vertical"></i></span><button type="button" class="title-order-button title-move-down" data-index="${index}" title="下へ移動"><i class="fas fa-chevron-down"></i></button></div></td>
                <td>${title.id}</td>
                <td><div class="title-cell">${image}<div><strong class="title-name">${escapeHtml(title.name)}</strong><div class="title-code">${escapeHtml(title.code || '')}</div><div class="title-subline"><span>${escapeHtml(methodLabel)}</span>${title.is_limited ? '<span class="title-limited-label">限定</span>' : ''}</div></div></div></td>
                <td><span class="title-category">${escapeHtml(title.category_name || '-')}</span></td>
                <td>${title.series_name ? `<span class="title-series-name">${escapeHtml(title.series_name)}</span>` : '<span class="title-unset">未設定</span>'}</td>
                <td>${levelLabel(title.level)}</td>
                <td><button type="button" class="title-condition-button ${requirements.length ? '' : 'is-empty'}" data-index="${index}" title="獲得条件を見る：${escapeHtml(conditionTitle)}" aria-label="獲得条件を見る"><i class="fas fa-clipboard-list"></i></button></td>
                <td>${Number(title.point_reward || 0).toLocaleString()}</td>
                <td>${Number(title.active_holder_count || 0).toLocaleString()}</td>
                <td>${Number(title.cumulative_grant_count || 0).toLocaleString()}</td>
                <td><span class="title-status-group"><span class="title-status ${title.is_active ? 'is-active' : 'is-inactive'}">${title.is_active ? '有効' : '無効'}</span>${title.is_limited ? '<span class="title-status is-limited">限定</span>' : ''}</span></td>
                <td class="title-operation-cell"><button type="button" class="title-action-trigger" data-index="${index}" aria-label="操作メニュー"><i class="fas fa-ellipsis-v"></i><span>操作</span></button></td>
            </tr>`;
        }).join('');
        updateSelectedCount();
        setupSortable();
    };

    const loadTitles = async () => {
        tableBody.innerHTML = '<tr><td colspan="13" class="title-table-message">読み込み中...</td></tr>';
        try {
            const response = await fetch(`${urls.list}?${buildQuery()}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || '一覧の取得に失敗しました。');
            titles = data.rows || [];
            document.getElementById('titleTotalCount').textContent = data.summary.total || 0;
            document.getElementById('titleCurrentHoldingCount').textContent = data.summary.current_holdings || 0;
            document.getElementById('titleMonthlyGrantCount').textContent = data.summary.monthly_grants || 0;
            document.getElementById('titleMonthlyRemovalCount').textContent = data.summary.monthly_removals || 0;
            document.getElementById('titleTotalTrend').textContent = '登録済みマスタ';
            document.getElementById('titleHoldingTrend').textContent = '現在付与中';
            setTrend('titleGrantTrend', data.summary.monthly_grants, data.summary.previous_month_grants);
            setTrend('titleRemovalTrend', data.summary.monthly_removals, data.summary.previous_month_removals);
            renderRows();
            updateHeaderSortIndicators();
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="13" class="title-table-message">${escapeHtml(error.message)}</td></tr>`;
        }
    };

    const addRequirementRow = (typeId = '', value = '') => {
        if (!requirementRows || !requirementRowTemplate) return;
        if (requirementRows.querySelectorAll('.title-requirement-row').length >= 3) return;
        const fragment = requirementRowTemplate.content.cloneNode(true);
        const row = fragment.querySelector('.title-requirement-row');
        fragment.querySelector('.titleRequirementTypeInput').value = typeId || '';
        fragment.querySelector('.titleRequirementValueInput').value = value || '';
        fragment.querySelector('.titleRequirementRemoveButton').addEventListener('click', () => {
            row.remove();
            if (!requirementRows.querySelector('.title-requirement-row')) addRequirementRow();
            updateRequirementAddButton();
        });
        requirementRows.appendChild(fragment);
        updateRequirementAddButton();
    };

    const updateRequirementAddButton = () => {
        if (requirementAddButton) requirementAddButton.disabled = requirementRows.querySelectorAll('.title-requirement-row').length >= 3;
    };

    const getRequirementPayload = () => Array.from(requirementRows.querySelectorAll('.title-requirement-row')).map(row => ({
        title_requirement_type_id: row.querySelector('.titleRequirementTypeInput')?.value || '',
        requirement_value: (() => { const raw = row.querySelector('.titleRequirementValueInput')?.value ?? ''; const digits = String(raw).replace(/[^0-9-]/g, ''); return digits === '' ? '' : Math.max(0, Number.parseInt(digits, 10)); })(),
    })).filter(item => item.title_requirement_type_id);

    const resetForm = () => {
        inputs.id.value = ''; inputs.code.value = '保存時に自動採番'; inputs.name.value = ''; inputs.category.value = ''; inputs.series.value = '';
        inputs.level.value = '1'; inputs.description.value = ''; inputs.acquisitionMessage.value = ''; inputs.grantMethod.value = 'both';
        inputs.allowRegrant.checked = true; inputs.notifyOnGrant.checked = true; inputs.imageFile.value = ''; croppedImageBlob = null;
        inputs.imagePreview.src = ''; inputs.imagePreview.style.display = 'none';
        document.querySelector('input[name="titleConditionOperator"][value="and"]').checked = true; inputs.pointReward.value = 0;
        inputs.limited.checked = false; inputs.startDate.value = ''; inputs.endDate.value = ''; inputs.active.checked = true;
        inputs.categorySearch.value = ''; inputs.seriesSearch.value = ''; requirementRows.innerHTML = ''; addRequirementRow();
    };

    const openCreate = () => { resetForm(); modalTitle.textContent = '称号を追加'; showModal(); };
    const openEdit = title => {
        resetForm();
        inputs.id.value = title.id; inputs.code.value = title.code || ''; inputs.name.value = title.name || '';
        inputs.category.value = title.category_id || ''; inputs.series.value = title.series_id || ''; inputs.level.value = title.level || 1;
        inputs.description.value = title.description || ''; inputs.acquisitionMessage.value = title.acquisition_message || '';
        inputs.grantMethod.value = title.grant_method || 'both'; inputs.allowRegrant.checked = !!title.allow_regrant;
        inputs.notifyOnGrant.checked = !!title.notify_on_grant; inputs.pointReward.value = title.point_reward || 0;
        inputs.limited.checked = !!title.is_limited; inputs.startDate.value = title.start_date || ''; inputs.endDate.value = title.end_date || '';
        inputs.active.checked = !!title.is_active; inputs.categorySearch.value = title.category_name || ''; inputs.seriesSearch.value = title.series_name || '';
        requirementRows.innerHTML = '';
        (title.requirements?.length ? title.requirements : [{ title_requirement_type_id: '', requirement_value: '' }]).forEach(item => addRequirementRow(item.title_requirement_type_id, item.requirement_value));
        if (title.image_path) { inputs.imagePreview.src = title.image_path; inputs.imagePreview.style.display = 'block'; }
        modalTitle.textContent = '称号を編集'; showModal();
    };

    const saveTitle = async () => {
        const formData = new FormData();
        const values = {
            title_category_id: inputs.category.value, title_series_id: inputs.series.value,
            name: inputs.name.value, level: inputs.level.value, description: inputs.description.value,
            acquisition_message: inputs.acquisitionMessage.value, grant_method: inputs.grantMethod.value,
            allow_regrant: inputs.allowRegrant.checked ? 1 : 0, notify_on_grant: inputs.notifyOnGrant.checked ? 1 : 0,
            point_reward: inputs.pointReward.value || 0, is_limited: inputs.limited.checked ? 1 : 0,
            start_date: inputs.startDate.value, end_date: inputs.endDate.value, is_active: inputs.active.checked ? 1 : 0,
            condition_operator: inputs.conditionOperator(),
        };
        Object.entries(values).forEach(([key, value]) => formData.append(key, value));
        getRequirementPayload().forEach((item, index) => {
            formData.append(`requirements[${index}][title_requirement_type_id]`, item.title_requirement_type_id);
            formData.append(`requirements[${index}][requirement_value]`, item.requirement_value);
        });
        if (croppedImageBlob) {
            formData.append('image_file', croppedImageBlob, 'title.png');
        } else if (inputs.imageFile.files[0]) {
            formData.append('image_file', inputs.imageFile.files[0]);
        }
        let url = urls.store;
        if (inputs.id.value) { url = `${urls.updateBase}/${inputs.id.value}`; formData.append('_method', 'PUT'); }
        const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: formData });
        const data = await response.json();
        if (!response.ok) { showToast(data.message || '保存に失敗しました。', 'error'); return; }
        const savedName = inputs.name.value; hideModal(); await loadTitles(); showToast(`${savedName}を${inputs.id.value ? '更新' : '登録'}しました。`);
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
        if (items.length && await postJson(urls.reorder, { items })) await loadTitles();
    };
    const setupSortable = () => { if (typeof Sortable === 'undefined') return; sortableInstance?.destroy(); sortableInstance = Sortable.create(tableBody, { handle: '.title-drag-handle', animation: 150, onEnd: reorder }); };

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
            if (hiddenInput.id.includes('Filter')) loadTitles();
        }));
        document.addEventListener('click', event => { if (!listElement.contains(event.target) && event.target !== searchInput) listElement.classList.remove('is-open'); });
    };

    setupSearchList(document.getElementById('titleCategoryFilterSearchInput'), filters.category, document.getElementById('titleCategoryFilterList'));
    setupSearchList(document.getElementById('titleSeriesFilterSearchInput'), filters.series, document.getElementById('titleSeriesFilterList'));
    setupSearchList(inputs.categorySearch, inputs.category, document.getElementById('titleCategoryList'));
    setupSearchList(inputs.seriesSearch, inputs.series, document.getElementById('titleSeriesList'));

    document.getElementById('titleAddButton')?.addEventListener('click', openCreate);
    document.getElementById('titleModalClose')?.addEventListener('click', hideModal);
    document.getElementById('titleModalCancel')?.addEventListener('click', hideModal);
    document.getElementById('titleModalSave')?.addEventListener('click', saveTitle);
    requirementAddButton?.addEventListener('click', () => addRequirementRow());
    document.getElementById('titleConditionPopoverClose')?.addEventListener('click', () => conditionPopover.hidden = true);

    filters.keyword.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadTitles, 500); });
    [filters.level, filters.grantMethod, filters.limited, filters.status, filters.requirementStatus, filters.usageStatus].forEach(input => input?.addEventListener('change', loadTitles));
    [filters.pointMin, filters.pointMax].forEach(input => input?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadTitles, 500); }));

    document.getElementById('titleFilterClearButton')?.addEventListener('click', () => {
        filters.keyword.value = '';
        filters.category.value = 'all'; filters.series.value = 'all';
        filters.level.value = 'all'; filters.grantMethod.value = 'all'; filters.limited.value = 'all'; filters.status.value = 'all';
        filters.requirementStatus.value = 'all'; filters.usageStatus.value = 'all'; filters.pointMin.value = ''; filters.pointMax.value = ''; currentSort = 'display_order'; filters.sort.value = 'display_order';
        document.getElementById('titleCategoryFilterSearchInput').value = '';
        document.getElementById('titleSeriesFilterSearchInput').value = '';
        updateHeaderSortIndicators(); loadTitles(); showToast('検索条件をクリアしました。');
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
        document.querySelectorAll('.title-sortable-header').forEach(header => {
            const indicator = header.querySelector('.title-sort-indicator');
            if (!indicator) return;
            const current = currentSort;
            const asc = headerSortValue(header.dataset.sortKey, 'asc');
            const desc = headerSortValue(header.dataset.sortKey, 'desc');
            header.classList.toggle('is-sorted', current === asc || current === desc);
            indicator.textContent = current === asc ? '↑' : current === desc ? '↓' : '↕';
        });
    };

    document.querySelectorAll('.title-sortable-header').forEach(header => {
        header.addEventListener('click', () => {
            const key = header.dataset.sortKey;
            const asc = headerSortValue(key, 'asc');
            const desc = headerSortValue(key, 'desc');
            currentSort = currentSort === asc ? desc : asc;
            updateHeaderSortIndicators();
            loadTitles();
        });
    });

    filters.sort?.addEventListener('change', () => {
        currentSort = filters.sort.value || 'display_order';
        updateHeaderSortIndicators();
        loadTitles();
    });

    document.querySelectorAll('.title-modal-tab').forEach(tab => tab.addEventListener('click', () => {
        document.querySelectorAll('.title-modal-tab').forEach(item => item.classList.toggle('is-active', item === tab));
        document.querySelectorAll('.title-modal-panel').forEach(panel => panel.classList.toggle('is-active', panel.dataset.panel === tab.dataset.tab));
    }));

    const cropModal = document.getElementById('titleCropModal');
    const cropCanvas = document.getElementById('titleCropCanvas');
    const cropContext = cropCanvas?.getContext('2d');
    const cropZoom = document.getElementById('titleCropZoom');
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

    document.getElementById('titleCropApply')?.addEventListener('click', () => {
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
    document.getElementById('titleCropCancel')?.addEventListener('click', () => closeCropModal(true));
    document.getElementById('titleCropClose')?.addEventListener('click', () => closeCropModal(true));

    document.getElementById('titleCsvExportButton')?.addEventListener('click', () => {
        const params = new URLSearchParams(buildQuery());
        params.set('export', '1');
        window.location.href = `${urls.list}?${params.toString()}`;
        showToast('現在の絞り込み条件でCSVを出力します。');
    });

    tableBody.addEventListener('change', event => { if (event.target.matches('.title-row-check')) updateSelectedCount(); });
    tableBody.addEventListener('click', async event => {
        const moveButton = event.target.closest('.title-order-button');
        if (moveButton) {
            const index = Number(moveButton.dataset.index);
            const nextIndex = moveButton.classList.contains('title-move-up') ? index - 1 : index + 1;
            if (nextIndex >= 0 && nextIndex < titles.length) {
                [titles[index], titles[nextIndex]] = [titles[nextIndex], titles[index]];
                renderRows(); await reorder(); showToast('並び順を更新しました。');
            }
            return;
        }
        const actionTrigger = event.target.closest('.title-action-trigger');
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
        const conditionButton = event.target.closest('.title-condition-button');
        if (conditionButton) {
            const title = titles[Number(conditionButton.dataset.index)]; const requirements = title.requirements || [];
            conditionPopoverBody.innerHTML = requirements.length ? `<div class="title-condition-list">${requirements.map(item => `<div class="title-condition-item">${escapeHtml(requirementText(item))}</div>`).join('')}</div>` : '<div class="title-condition-empty">獲得条件は未設定です。</div>';
            const rect = conditionButton.getBoundingClientRect(); conditionPopover.style.left = `${Math.min(rect.left, window.innerWidth - 356)}px`; conditionPopover.style.top = `${Math.min(rect.bottom + 8, window.innerHeight - 220)}px`; conditionPopover.hidden = false; return;
        }
        const edit = event.target.closest('.title-edit-button'); if (edit) { openEdit(titles[Number(edit.dataset.index)]); closeActionMenus(); return; }
        const duplicate = event.target.closest('.title-duplicate-button'); if (duplicate && await postJson(`${urls.duplicateBase}/${duplicate.dataset.id}/duplicate`)) { closeActionMenus(); await loadTitles(); return; }
        const toggle = event.target.closest('.title-toggle-active-button'); if (toggle && await postJson(`${urls.toggleActiveBase}/${toggle.dataset.id}/toggle-active`)) { closeActionMenus(); await loadTitles(); return; }
        const remove = event.target.closest('.title-delete-button');
        if (remove) { if (!confirm('この称号を削除しますか？')) return; if (await deleteRequest(`${urls.deleteBase}/${remove.dataset.id}`)) { closeActionMenus(); await loadTitles(); } }
    });

    checkAll?.addEventListener('change', () => { document.querySelectorAll('.title-row-check').forEach(input => input.checked = checkAll.checked); updateSelectedCount(); });
    document.getElementById('titleBulkActivateButton')?.addEventListener('click', async () => {
        const ids = selectedIds(); if (!ids.length) return showToast('対象を選択してください。', 'error'); if (await postJson(urls.bulkActivate, { ids })) await loadTitles();
    });
    document.getElementById('titleBulkDeactivateButton')?.addEventListener('click', async () => {
        const ids = selectedIds(); if (!ids.length) return showToast('対象を選択してください。', 'error'); if (await postJson(urls.bulkDeactivate, { ids })) await loadTitles();
    });

    document.addEventListener('click', async event => {
        if (!event.target.closest('.title-action-trigger') && !event.target.closest('.title-action-menu-portal')) closeActionMenus();
        if (!event.target.closest('.title-condition-button') && !event.target.closest('#titleConditionPopover')) conditionPopover.hidden = true;

        const edit = event.target.closest('.title-action-menu-portal .title-edit-button');
        if (edit) { openEdit(titles[Number(edit.dataset.index)]); closeActionMenus(); return; }
        const duplicate = event.target.closest('.title-action-menu-portal .title-duplicate-button');
        if (duplicate && await postJson(`${urls.duplicateBase}/${duplicate.dataset.id}/duplicate`)) { closeActionMenus(); await loadTitles(); return; }
        const toggle = event.target.closest('.title-action-menu-portal .title-toggle-active-button');
        if (toggle && await postJson(`${urls.toggleActiveBase}/${toggle.dataset.id}/toggle-active`)) { closeActionMenus(); await loadTitles(); return; }
        const remove = event.target.closest('.title-action-menu-portal .title-delete-button');
        if (remove) {
            if (!confirm('この称号を削除しますか？')) return;
            if (await deleteRequest(`${urls.deleteBase}/${remove.dataset.id}`)) { closeActionMenus(); await loadTitles(); }
        }
    });

    window.addEventListener('resize', closeActionMenus);
    document.querySelector('.title-table-scroll')?.addEventListener('scroll', closeActionMenus);

    loadTitles();
});
