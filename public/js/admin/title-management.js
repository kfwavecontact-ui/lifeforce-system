document.addEventListener('DOMContentLoaded', () => {
    console.log('title-management.js loaded');
    const page = document.querySelector('.title-management-page');
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
        eventSearch: page.dataset.eventSearchUrl,
    };

    const tableBody = document.getElementById('titleTableBody');
    const checkAll = document.getElementById('titleCheckAll');

    const modal = document.getElementById('titleModal');
    const modalTitle = document.getElementById('titleModalTitle');

    let titles = [];
    let selectedEvents = [];

    const inputs = {
        id: document.getElementById('titleIdInput'),
        name: document.getElementById('titleNameInput'),
        description: document.getElementById('titleDescriptionInput'),
        rarity: document.getElementById('titleRarityInput'),
        imageFile: document.getElementById('titleImageFileInput'),
        imagePreview: document.getElementById('titleImagePreview'),
        pointReward: document.getElementById('titlePointRewardInput'),
        active: document.getElementById('titleActiveInput'),
        eventSearch: document.getElementById('titleEventSearchInput'),
        selectedEvents: document.getElementById('titleSelectedEvents'),

    };

    const filters = {
        keyword: document.getElementById('titleSearchInput'),
        tag: document.getElementById('titleTagFilter'),
        event: document.getElementById('titleEventFilter'),
        rarity: document.getElementById('titleRarityFilter'),
        status: document.getElementById('titleStatusFilter'),
    };

    if (inputs.eventSearch) {
        inputs.eventSearch.disabled = false;
        inputs.eventSearch.readOnly = false;
    }

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

    const renderSelectedEvents = () => {
        if (!inputs.selectedEvents) return;

        if (selectedEvents.length === 0) {
            inputs.selectedEvents.innerHTML = '<span class="title-empty-text">対象イベントなし</span>';
            return;
        }

        inputs.selectedEvents.innerHTML = selectedEvents.map(event => `
            <span class="title-selected-event-badge">
                ${escapeHtml(event.title)}
                <button type="button" class="title-selected-event-remove" data-id="${event.id}">×</button>
            </span>
        `).join('');
    };

    const selectedIds = () => {
        return Array.from(document.querySelectorAll('.title-row-check:checked'))
            .map(input => Number(input.value));
    };

    const buildQuery = () => {
        const params = new URLSearchParams();

        const tagKeyword = document.getElementById('titleTagFilterSearchInput')?.value.trim() || '';
        const eventKeyword = document.getElementById('titleEventFilterSearchInput')?.value.trim() || '';

        params.set('keyword', filters.keyword?.value || '');
        params.set('tag_keyword', tagKeyword);
        params.set('event_keyword', eventKeyword);
        params.set('rarity', filters.rarity?.value || 'all');
        params.set('status', filters.status?.value || 'all');

        return params.toString();
    };

    const loadTitles = async () => {
        tableBody.innerHTML = '<tr><td colspan="13">読み込み中...</td></tr>';

        const response = await fetch(`${urls.list}?${buildQuery()}`, {
            headers: { 'Accept': 'application/json' },
        });

        const data = await response.json();

        titles = data.rows || [];

        document.getElementById('titleTotalCount').textContent = data.summary.total;
        document.getElementById('titleActiveCount').textContent = data.summary.active;
        document.getElementById('titleInactiveCount').textContent = data.summary.inactive;

        renderRows();
    };

    const renderRows = () => {
        if (titles.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="13">データがありません。</td></tr>';
            return;
        }

        tableBody.innerHTML = titles.map((title, index) => {
            const imageHtml = title.image_path
                ? `<img src="${escapeHtml(title.image_path)}" class="title-table-image" alt="">`
                : '<span class="title-no-image">なし</span>';

            const hasUnsetDate = title.events?.some(
                event => event.schedule_status === '日付未設定'
            );

            const eventTooltip = title.events && title.events.length
                ? title.events.map(event => `${event.title}（${event.start_at || '日付未設定'}）`).join('\n')
                : '';

            const eventHtml = title.events && title.events.length
                ? `
                    <div class="title-event-list" data-tooltip="${escapeHtml(eventTooltip)}">
                        ${title.events.slice(0, 2).map(event => `
                            <span class="title-event-summary">${escapeHtml(event.title)}</span>
                        `).join('')}
                        ${title.events.length > 2
                            ? `<span class="title-event-more">ほか${title.events.length - 2}件</span>`
                            : ''
                        }
                    </div>
                `
                : '<span class="title-empty-text">-</span>';

            const tagHtml = title.tags && title.tags.length
                ? `
                    <div class="title-tag-list">
                        ${title.tags.map(tag => `
                            <span class="title-tag-badge">${escapeHtml(tag.name)}</span>
                        `).join('')}
                    </div>
                `
                : '<span class="title-empty-text">-</span>';

            return `
                <tr data-id="${title.id}">
                    <td><input type="checkbox" class="title-row-check" value="${title.id}"></td>
                    <td>
                        <span class="title-drag-handle" title="並び替え">⋮⋮</span>
                        <input type="hidden"
                            class="title-order-input"
                            value="${title.display_order}"
                            data-id="${title.id}">
                    </td>
                    <td>${title.id}</td>
                    <td>
                        <strong>${escapeHtml(title.name)}</strong>
                    </td>
                    <td>${imageHtml}</td>
                    <td>
                        <span class="title-rarity-badge title-rarity-${escapeHtml(title.rarity)}">
                            ${escapeHtml(title.rarity_name || title.rarity || '-')}
                        </span>
                    </td>
                    <td>${escapeHtml(title.description || '-')}</td>
                    <td>${eventHtml}</td>
                    <td>${tagHtml}</td>
                    <td>${escapeHtml(title.point_reward ?? 0)}Pt</td>
                    <td>${escapeHtml(title.acquired_count ?? 0)}人</td>
                    <td>
                        <span class="master-status ${title.is_active ? 'is-active' : 'is-inactive'}">
                            ${title.is_active ? '有効' : '無効'}
                        </span>
                    </td>
                    <td>
                        <div class="master-action-buttons">
                            <button type="button" class="master-link-button title-edit-button" data-index="${index}">編集</button>
                            <button type="button" class="master-link-button title-duplicate-button" data-id="${title.id}">複製</button>
                            <button type="button" class="master-link-button title-deactivate-button" data-id="${title.id}">無効化</button>
                            <button type="button" class="master-danger-link-button title-delete-button" data-id="${title.id}">削除</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        setupSortable();
    };

    const resetForm = () => {
        inputs.id.value = '';
        inputs.name.value = '';
        inputs.description.value = '';
        inputs.rarity.value = 'normal';
        inputs.imageFile.value = '';
        inputs.imagePreview.src = '';
        inputs.imagePreview.style.display = 'none';
        inputs.pointReward.value = 0;

        document.querySelectorAll('.titleTagInput').forEach(input => {
            input.value = '';
        });

        inputs.active.checked = true;

        selectedEvents = [];
        renderSelectedEvents();
        if (inputs.eventSearch) {
            inputs.eventSearch.value = '';
        }
    };

    const openCreate = () => {
        resetForm();
        modalTitle.textContent = '称号を追加';
        showModal();
    };

    const openEdit = (title) => {
        resetForm();

        inputs.id.value = title.id;
        inputs.name.value = title.name || '';
        inputs.description.value = title.description || '';
        inputs.rarity.value = title.rarity || 'normal';
        inputs.pointReward.value = title.point_reward || 0;

        document.querySelectorAll('.titleTagInput').forEach((input, index) => {
            input.value = title.tags?.[index]?.id || '';
        });

        selectedEvents = title.events || [];
        renderSelectedEvents();

        inputs.active.checked = !!title.is_active;

        if (title.image_path) {
            inputs.imagePreview.src = title.image_path;
            inputs.imagePreview.style.display = 'block';
        }

        modalTitle.textContent = '称号を編集';
        showModal();
    };

    const saveTitle = async () => {
        const formData = new FormData();

        formData.append('name', inputs.name.value);
        formData.append('description', inputs.description.value);
        formData.append('rarity', inputs.rarity.value);
        formData.append('point_reward', inputs.pointReward.value || 0);

        Array.from(document.querySelectorAll('.titleTagInput'))
            .map(input => input.value)
            .filter(value => value)
            .filter((value, index, self) => self.indexOf(value) === index)
            .slice(0, 4)
            .forEach((value, index) => {
                formData.append(`tag_ids[${index}]`, value);
            });

        selectedEvents.forEach((event, index) => {
            formData.append(`event_ids[${index}]`, event.id);
        });

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
        await loadTitles();
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
            await loadTitles();
        }
    };

    const setupSortable = () => {
        if (typeof Sortable === 'undefined') return;

        Sortable.create(tableBody, {
            handle: '.title-drag-handle',
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
                    loadTitles();
                }
            });
        });

        document.addEventListener('click', (event) => {
            if (!listElement.contains(event.target) && event.target !== searchInput) {
                listElement.classList.remove('is-open');
            }
        });
    };

    const titleAddButton = document.getElementById('titleAddButton');

    console.log('titleAddButton found:', titleAddButton);

    if (titleAddButton) {
        titleAddButton.addEventListener('click', () => {
            console.log('title add clicked');
            openCreate();
        });
    }
    document.getElementById('titleModalClose')?.addEventListener('click', hideModal);
    document.getElementById('titleModalCancel')?.addEventListener('click', hideModal);
    document.getElementById('titleModalSave')?.addEventListener('click', saveTitle);


    filters.keyword?.addEventListener('input', loadTitles);
    document.getElementById('titleTagFilterSearchInput')?.addEventListener('input', loadTitles);
    document.getElementById('titleEventFilterSearchInput')?.addEventListener('input', loadTitles);
    filters.rarity?.addEventListener('change', loadTitles);
    filters.status?.addEventListener('change', loadTitles);

    let eventSearchTimer = null;

    inputs.eventSearch?.addEventListener('input', () => {
        clearTimeout(eventSearchTimer);

        const keyword = inputs.eventSearch.value.trim();

        eventSearchTimer = setTimeout(async () => {
            if (!keyword) return;

            const response = await fetch(`${urls.eventSearch}?keyword=${encodeURIComponent(keyword)}`, {
                headers: { 'Accept': 'application/json' },
            });

            const data = await response.json();

            const existingList = document.getElementById('titleEventSearchResults');
            if (existingList) existingList.remove();

            const list = document.createElement('div');
            list.id = 'titleEventSearchResults';
            list.className = 'title-event-search-results';

            if (!data.rows || data.rows.length === 0) {
                list.innerHTML = '<div class="title-event-search-empty">該当するイベントがありません</div>';
            } else {
                list.innerHTML = data.rows.map(event => `
                    <button type="button" data-id="${event.id}" data-title="${escapeHtml(event.title)}">
                        ${escapeHtml(event.title)}
                    </button>
                `).join('');
            }

            inputs.eventSearch.parentElement.appendChild(list);
        }, 300);
    });

    document.addEventListener('click', (event) => {
        const eventButton = event.target.closest('#titleEventSearchResults button');

        if (eventButton) {
            const id = Number(eventButton.dataset.id);
            const title = eventButton.dataset.title;

            if (!selectedEvents.some(item => Number(item.id) === id)) {
                selectedEvents.push({ id, title });
            }

            renderSelectedEvents();

            const list = document.getElementById('titleEventSearchResults');
            if (list) list.remove();

            if (inputs.eventSearch) {
                inputs.eventSearch.value = '';
            }

            return;
        }

        if (!event.target.closest('#titleEventSearchResults') && event.target !== inputs.eventSearch) {
            const list = document.getElementById('titleEventSearchResults');
            if (list) list.remove();
        }
    });

    inputs.selectedEvents?.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.title-selected-event-remove');
        if (!removeButton) return;

        selectedEvents = selectedEvents.filter(item => Number(item.id) !== Number(removeButton.dataset.id));
        renderSelectedEvents();
    });

    inputs.imageFile?.addEventListener('change', () => {
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
        const editButton = event.target.closest('.title-edit-button');
        if (editButton) {
            openEdit(titles[Number(editButton.dataset.index)]);
            return;
        }

        const duplicateButton = event.target.closest('.title-duplicate-button');
        if (duplicateButton) {
            if (await postJson(`${urls.duplicateBase}/${duplicateButton.dataset.id}/duplicate`)) {
                await loadTitles();
            }
            return;
        }

        const deactivateButton = event.target.closest('.title-deactivate-button');
        if (deactivateButton) {
            if (await postJson(`${urls.deactivateBase}/${deactivateButton.dataset.id}/deactivate`)) {
                await loadTitles();
            }
            return;
        }

        const deleteButton = event.target.closest('.title-delete-button');
        if (deleteButton) {
            if (!confirm('削除しますか？')) return;

            if (await deleteRequest(`${urls.deleteBase}/${deleteButton.dataset.id}`)) {
                await loadTitles();
            }
        }
    });

    checkAll?.addEventListener('change', () => {
        document.querySelectorAll('.title-row-check').forEach(input => {
            input.checked = checkAll.checked;
        });
    });

    document.getElementById('titleBulkDeactivateButton')?.addEventListener('click', async () => {
        const ids = selectedIds();
        if (ids.length === 0) {
            alert('対象を選択してください。');
            return;
        }

        if (await postJson(urls.bulkDeactivate, { ids })) {
            await loadTitles();
        }
    });

    document.getElementById('titleBulkDeleteButton')?.addEventListener('click', async () => {
        const ids = selectedIds();
        if (ids.length === 0) {
            alert('対象を選択してください。');
            return;
        }

        if (!confirm('選択した称号を削除しますか？')) return;

        if (await postJson(urls.bulkDelete, { ids })) {
            await loadTitles();
        }
    });

    loadTitles();
});