document.addEventListener('DOMContentLoaded', () => {
    const masterPage = document.querySelector('.master-page');

    if (!masterPage) return;

    const listUrl = masterPage.dataset.masterListUrl;
    const storeUrl = masterPage.dataset.masterStoreUrl;
    const updateUrlBase = masterPage.dataset.masterUpdateUrlBase;
    const csrfToken = masterPage.dataset.csrfToken;

    const masterMenus = document.querySelectorAll('.master-menu');
    const masterTitle = document.getElementById('masterTitle');
    const masterDescription = document.getElementById('masterDescription');
    const masterTableHead = document.getElementById('masterTableHead');
    const masterTableBody = document.getElementById('masterTableBody');
    const masterSearchInput = document.getElementById('masterSearchInput');

    const masterTotalCount = document.getElementById('masterTotalCount');
    const masterActiveCount = document.getElementById('masterActiveCount');
    const masterInactiveCount = document.getElementById('masterInactiveCount');
    const masterStatusFilter = document.getElementById('masterStatusFilter');

    const reorderUrl = masterPage.dataset.masterReorderUrl;
    const deleteUrlBase = masterPage.dataset.masterDeleteUrlBase;
    let draggedRowId = null;

    const addButton = document.getElementById('masterAddButton');
    const modal = document.getElementById('masterModal');
    const modalClose = document.getElementById('masterModalClose');
    const modalCancel = document.getElementById('masterModalCancel');
    const modalSave = document.getElementById('masterModalSave');

    const formArea = document.querySelector('.master-form');
    const modalTitle = document.getElementById('masterModalTitle');

    const seederButton = document.getElementById('masterSeederButton');
    const seederModal = document.getElementById('masterSeederModal');
    const seederClose = document.getElementById('masterSeederClose');
    const seederOutput = document.getElementById('masterSeederOutput');
    const seederCopy = document.getElementById('masterSeederCopy');



    let currentMasterKey = document.querySelector('.master-page')?.dataset.initialMaster || 'enrollment_statuses';
    let currentRows = [];
    let currentColumns = [];
    let editingId = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            ...options
        });

        if (!response.ok) {
            let message = '処理に失敗しました。';

            try {
                const error = await response.json();
                message = error.message || message;
            } catch (_) {}

            throw new Error(message);
        }

        return response.json();
    }

    async function loadMaster() {
        masterTableHead.innerHTML = '<th>読み込み中...</th>';
        masterTableBody.innerHTML = '<tr><td>読み込み中...</td></tr>';

        try {
            const url = `${listUrl}?master=${encodeURIComponent(currentMasterKey)}`;
            const data = await fetchJson(url);

            currentRows = data.rows || [];
            currentColumns = data.columns || [];

            masterTitle.textContent = data.meta.title;
            masterDescription.textContent = data.meta.description;

            renderMaster();
        } catch (error) {
            masterTableHead.innerHTML = '<th>エラー</th>';
            masterTableBody.innerHTML = `<tr><td>${escapeHtml(error.message)}</td></tr>`;
        }
    }

    function renderMaster() {
        const keyword = masterSearchInput.value.trim();

        const statusFilter = masterStatusFilter?.value ?? 'all';

        const filteredRows = currentRows.filter(row => {

            if (statusFilter !== 'all' && row.hasOwnProperty('is_active')) {

                if (statusFilter === 'active' && !row.is_active) {
                    return false;
                }

                if (statusFilter === 'inactive' && row.is_active) {
                    return false;
                }
            }
            if (!keyword) return true;

            return Object.values(row).some(value =>
                String(value ?? '').includes(keyword)
            );
        });

        renderTableHead();
        renderTableBody(filteredRows);
        renderSummary();
    }

    function renderTableHead() {
        masterTableHead.innerHTML = '';

        const dragTh = document.createElement('th');
        dragTh.className = 'master-drag-column';
        dragTh.innerHTML = '↕';

        if (canReorder()) {
            masterTableHead.appendChild(dragTh);
        }

        currentColumns.forEach(column => {
            const th = document.createElement('th');

            th.innerHTML = `
                ${escapeHtml(column.label)}
                ${column.required ? '<span class="master-required">*</span>' : ''}
            `;

            masterTableHead.appendChild(th);
        });

        const operationTh = document.createElement('th');
        operationTh.textContent = '操作';
        masterTableHead.appendChild(operationTh);
    }

    function renderTableBody(rows) {
        masterTableBody.innerHTML = '';

        if (rows.length === 0) {
            masterTableBody.innerHTML = `
                <tr>
                    <td colspan="${currentColumns.length + (canReorder() ? 2 : 1)}">
                        該当するマスタ値がありません。
                    </td>
                </tr>
            `;
            return;
        }

        rows.forEach(row => {
            const tr = document.createElement('tr');

            tr.draggable = canReorder();
            tr.dataset.id = row.id;
            tr.classList.add('master-draggable-row');

            tr.addEventListener('dragstart', () => {
                tr.classList.add('dragging');
            });

            tr.addEventListener('dragend', () => {
                tr.classList.remove('dragging');
            });

            tr.addEventListener('dragover', e => {
                e.preventDefault();

                const dragging = masterTableBody.querySelector('.dragging');

                if (!dragging || dragging === tr) {
                    return;
                }

                const rect = tr.getBoundingClientRect();
                const next = (e.clientY - rect.top) > rect.height / 2;

                masterTableBody.insertBefore(
                    dragging,
                    next ? tr.nextSibling : tr
                );
            });

            tr.addEventListener('drop', async e => {
                e.preventDefault();
                await saveRowOrder();
            });

            if (canReorder()) {
                const dragTd = document.createElement('td');
                dragTd.className = 'master-drag-handle';
                dragTd.innerHTML = '⋮⋮';

                tr.appendChild(dragTd);

                dragTd.addEventListener('mousedown', () => {
                    tr.draggable = true;
                });

                tr.addEventListener('mouseup', () => {
                    tr.draggable = false;
                });
            }

            currentColumns.forEach(column => {
                const td = document.createElement('td');
                const value = row[column.name];

                if (column.name === 'is_active') {
                    td.innerHTML = `
                        <span class="master-status ${value ? 'active' : 'inactive'}">
                            ${value ? '有効' : '無効'}
                        </span>
                    `;
                } else {
                    td.textContent = value ?? '';
                }

                tr.appendChild(td);
            });

            const operationTd = document.createElement('td');

            const deleteButtonHtml = currentMasterKey === 'title_tags'
                ? `
                    <button type="button"
                            class="master-secondary-button master-delete-button"
                            data-id="${row.id}"
                            data-used-count="${row.used_title_count || 0}">
                        削除
                    </button>
                `
                : '';

            operationTd.innerHTML = `
                <div class="master-action-buttons">
                    <button type="button" class="master-secondary-button master-edit-button" data-id="${row.id}">
                        編集
                    </button>
                    ${deleteButtonHtml}
                </div>
            `;

            tr.appendChild(operationTd);
            masterTableBody.appendChild(tr);
        });
    }

    function renderSummary() {
        masterTotalCount.textContent = currentRows.length;

        if (currentColumns.some(column => column.name === 'is_active')) {
            masterActiveCount.textContent = currentRows.filter(row => row.is_active).length;
            masterInactiveCount.textContent = currentRows.filter(row => !row.is_active).length;
        } else {
            masterActiveCount.textContent = '-';
            masterInactiveCount.textContent = '-';
        }
    }

    function canReorder() {
        return currentColumns.some(column =>
            column.name === 'sort_order' || column.name === 'display_order'
        );
    }

    async function saveRowOrder() {
        if (!canReorder()) {
            return;
        }

        const rows = [...masterTableBody.querySelectorAll('tr[data-id]')];

        const items = rows.map((tr, index) => ({
            id: Number(tr.dataset.id),
            sort_order: index + 1
        }));

        try {
            await fetchJson(reorderUrl, {
                method: 'POST',
                body: JSON.stringify({
                    master: currentMasterKey,
                    items
                })
            });

            await loadMaster();
        } catch (error) {
            alert(error.message);
        }
    }

    function openModal(row = null) {
        editingId = row ? row.id : null;
        modalTitle.textContent = row ? 'マスタ値を編集' : 'マスタ値を追加';

        formArea.innerHTML = '';

        currentColumns
            .filter(column => column.editable)
            .forEach(column => {
                const value = row ? row[column.name] : defaultValue(column);
                formArea.appendChild(createField(column, value));
            });

        modal.classList.add('show');
    }

    function defaultValue(column) {
        if (column.name === 'is_active') return true;
        if (column.name === 'sort_order' || column.name === 'display_order') return currentRows.length + 1;
        return '';
    }

    function createField(column, value) {
        const label = document.createElement('label');

        const required = column.required
            ? '<span class="master-required">*</span>'
            : '';

        if (column.type.includes('bool')) {
            label.className = 'master-checkbox';
            label.innerHTML = `
                <input type="checkbox" data-column="${escapeHtml(column.name)}" ${value ? 'checked' : ''}>
                ${escapeHtml(column.label)} ${required}
            `;
            return label;
        }

        if (column.type.includes('text')) {
            label.innerHTML = `
                ${escapeHtml(column.label)} ${required}
                <textarea data-column="${escapeHtml(column.name)}" rows="3">${escapeHtml(value)}</textarea>
            `;
            return label;
        }

        const inputType = column.type.includes('int') || column.type.includes('numeric')
            ? 'number'
            : column.type.includes('date')
                ? 'date'
                : 'text';

        label.innerHTML = `
            ${escapeHtml(column.label)} ${required}
            <input type="${inputType}" data-column="${escapeHtml(column.name)}" value="${escapeHtml(value)}">
        `;

        return label;
    }

    function closeModal() {
        modal.classList.remove('show');
    }

    async function saveModal() {
        const data = {};

        formArea.querySelectorAll('[data-column]').forEach(input => {
            const column = input.dataset.column;

            if (input.type === 'checkbox') {
                data[column] = input.checked;
            } else {
                data[column] = input.value;
            }
        });

        try {
            if (editingId) {
                await fetchJson(`${updateUrlBase}/${editingId}`, {
                    method: 'PUT',
                    body: JSON.stringify({
                        master: currentMasterKey,
                        data
                    })
                });
            } else {
                await fetchJson(storeUrl, {
                    method: 'POST',
                    body: JSON.stringify({
                        master: currentMasterKey,
                        data
                    })
                });
            }

            closeModal();
            await loadMaster();
        } catch (error) {
            alert(error.message);
        }
    }

    function openSeederModal() {
        const output = currentRows.map(row => {
            const lines = currentColumns
                .filter(column => column.editable)
                .map(column => {
                    const value = row[column.name];

                    if (typeof value === 'boolean') {
                        return `    '${column.name}' => ${value ? 'true' : 'false'},`;
                    }

                    if (typeof value === 'number') {
                        return `    '${column.name}' => ${value},`;
                    }

                    return `    '${column.name}' => '${String(value ?? '').replaceAll("'", "\\'")}',`;
                })
                .join('\n');

            return `[\n${lines}\n],`;
        }).join('\n');

        seederOutput.value = output;
        seederModal.classList.add('show');
    }

    masterMenus.forEach(menu => {
        menu.addEventListener('click', async () => {
            masterMenus.forEach(item => item.classList.remove('active'));
            menu.classList.add('active');

            currentMasterKey = menu.dataset.master;
            masterSearchInput.value = '';

            await loadMaster();
        });
    });

    masterSearchInput.addEventListener('input', renderMaster);
    masterStatusFilter?.addEventListener('change', renderMaster);

    addButton.addEventListener('click', () => openModal());

    modalClose.addEventListener('click', closeModal);
    modalCancel.addEventListener('click', closeModal);
    modalSave.addEventListener('click', saveModal);

    masterTableBody.addEventListener('click', async event => {
        const deleteButton = event.target.closest('.master-delete-button');

        if (deleteButton) {
            const id = deleteButton.dataset.id;
            const usedCount = Number(deleteButton.dataset.usedCount || 0);

            if (currentMasterKey === 'title_tags' && usedCount > 0) {
                alert('このタグは称号で使用中のため削除できません。');
                return;
            }

            if (!confirm('削除しますか？')) {
                return;
            }

            try {
                await fetchJson(`${deleteUrlBase}/${id}?master=${encodeURIComponent(currentMasterKey)}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                await loadMaster();
            } catch (error) {
                alert(error.message);
            }

            return;
        }

        const editButton = event.target.closest('.master-edit-button');

        if (!editButton) {
            return;
        }

        const row = currentRows.find(item => String(item.id) === String(editButton.dataset.id));

        if (row) {
            openModal(row);
        }
    });

    seederButton.addEventListener('click', openSeederModal);
    seederClose.addEventListener('click', () => seederModal.classList.remove('show'));

    seederCopy.addEventListener('click', async () => {
        await navigator.clipboard.writeText(seederOutput.value);
        alert('Seederコードをコピーしました。');
    });

    loadMaster();
});