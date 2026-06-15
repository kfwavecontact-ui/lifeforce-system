document.addEventListener('DOMContentLoaded', () => {

    const page = document.querySelector('.course-management-page');

    if (!page) {
        return;
    }

    const csrfToken = page.dataset.csrfToken;
    const listUrl = page.dataset.listUrl;
    const storeUrl = page.dataset.storeUrl;
    const updateUrlBase = page.dataset.updateUrlBase;
    const reorderUrl = page.dataset.reorderUrl;

    const tbody = document.getElementById('courseTableBody');

    const totalCount = document.getElementById('courseTotalCount');
    const activeCount = document.getElementById('courseActiveCount');
    const inactiveCount = document.getElementById('courseInactiveCount');

    const searchInput = document.getElementById('courseSearchInput');
    const statusFilter = document.getElementById('courseStatusFilter');

    const addButton = document.getElementById('courseAddButton');

    const modal = document.getElementById('courseModal');
    const modalTitle = document.getElementById('courseModalTitle');

    const modalClose = document.getElementById('courseModalClose');
    const modalCancel = document.getElementById('courseModalCancel');
    const modalSave = document.getElementById('courseModalSave');

    let rows = [];
    let editingId = null;

    async function request(url, options = {}) {

        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            ...options
        });

        if (!response.ok) {
            throw new Error('処理に失敗しました。');
        }

        return response.json();
    }

    async function loadData() {

        const params = new URLSearchParams({
            keyword: searchInput.value,
            status: statusFilter.value
        });

        const data = await request(
            `${listUrl}?${params.toString()}`
        );

        rows = data.rows;

        totalCount.textContent = data.summary.total;
        activeCount.textContent = data.summary.active;
        inactiveCount.textContent = data.summary.inactive;

        renderTable();
    }

    function renderTable() {

        tbody.innerHTML = '';

        if (!rows.length) {

            tbody.innerHTML = `
                <tr>
                    <td colspan="12">
                        データがありません。
                    </td>
                </tr>
            `;

            return;
        }

        rows.forEach(row => {

            const tr = document.createElement('tr');

            tr.classList.add('master-draggable-row');
            tr.dataset.id = row.id;

            tr.innerHTML = `
                <td class="master-drag-handle">⋮⋮</td>
                <td>${row.id}</td>
                <td>${row.code ?? ''}</td>
                <td>${row.course_name ?? ''}</td>
                <td>${row.description ?? ''}</td>
                <td>${row.attendance_type ?? ''}</td>
                <td class="course-price">
                    ${Number(row.monthly_fee).toLocaleString()}円
                </td>
                <td>${row.sort_order}</td>
                <td>
                    ${
                        row.is_recommended
                        ? '<span class="course-recommended">★</span>'
                        : '<span class="course-recommended off"></span>'
                    }
                </td>
                <td>
                    <span class="master-status ${row.is_active ? 'active' : 'inactive'}">
                        ${row.is_active ? '有効' : '無効'}
                    </span>
                </td>
                <td>${row.contract_count}名</td>
                <td>
                    <button
                        type="button"
                        class="master-secondary-button course-edit-button"
                        data-id="${row.id}">
                        編集
                    </button>
                </td>
            `;

            tbody.appendChild(tr);
        });

        bindEditButtons();
        bindDragEvents();
    }

    function bindEditButtons() {

        document
            .querySelectorAll('.course-edit-button')
            .forEach(button => {

                button.addEventListener('click', () => {

                    const row = rows.find(
                        item => item.id == button.dataset.id
                    );

                    if (!row) {
                        return;
                    }

                    editingId = row.id;

                    modalTitle.textContent = 'コースを編集';

                    document.getElementById('coursePriceIdInput').value = row.id;
                    document.getElementById('courseIdInput').value = row.course_id;

                    document.getElementById('courseCodeInput').value = row.code ?? '';
                    document.getElementById('courseNameInput').value = row.course_name ?? '';
                    document.getElementById('attendanceTypeInput').value = row.attendance_type ?? '';
                    document.getElementById('monthlyFeeInput').value = row.monthly_fee ?? '';
                    document.getElementById('courseDescriptionInput').value = row.description ?? '';

                    document.getElementById('recommendedInput').checked = row.is_recommended;
                    document.getElementById('activeInput').checked = row.is_active;

                    modal.classList.add('show');
                });
            });
    }

    function openCreateModal() {

        editingId = null;

        modalTitle.textContent = 'コースを追加';

        document.getElementById('coursePriceIdInput').value = '';
        document.getElementById('courseIdInput').value = '';

        document.getElementById('courseCodeInput').value = '';
        document.getElementById('courseNameInput').value = '';
        document.getElementById('attendanceTypeInput').value = '';
        document.getElementById('monthlyFeeInput').value = '';
        document.getElementById('courseDescriptionInput').value = '';

        document.getElementById('recommendedInput').checked = false;
        document.getElementById('activeInput').checked = true;

        modal.classList.add('show');
    }

    function closeModal() {
        modal.classList.remove('show');
    }

    async function saveCourse() {

        const payload = {
            course_id: document.getElementById('courseIdInput').value,
            code: document.getElementById('courseCodeInput').value,
            course_name: document.getElementById('courseNameInput').value,
            attendance_type: document.getElementById('attendanceTypeInput').value,
            monthly_fee: document.getElementById('monthlyFeeInput').value,
            description: document.getElementById('courseDescriptionInput').value,
            is_recommended: document.getElementById('recommendedInput').checked,
            is_active: document.getElementById('activeInput').checked
        };

        if (editingId) {

            await request(
                `${updateUrlBase}/${editingId}`,
                {
                    method: 'PUT',
                    body: JSON.stringify(payload)
                }
            );

        } else {

            await request(
                storeUrl,
                {
                    method: 'POST',
                    body: JSON.stringify(payload)
                }
            );
        }

        closeModal();
        await loadData();
    }

    function bindDragEvents() {

        let dragging = null;

        tbody.querySelectorAll('tr').forEach(row => {

            row.draggable = true;

            row.addEventListener('dragstart', () => {
                dragging = row;
                row.classList.add('dragging');
            });

            row.addEventListener('dragend', async () => {

                row.classList.remove('dragging');

                const items = [
                    ...tbody.querySelectorAll('tr[data-id]')
                ].map((tr, index) => ({
                    id: Number(tr.dataset.id),
                    sort_order: index + 1
                }));

                await request(
                    reorderUrl,
                    {
                        method: 'POST',
                        body: JSON.stringify({ items })
                    }
                );

                await loadData();
            });

            row.addEventListener('dragover', event => {

                event.preventDefault();

                if (!dragging || dragging === row) {
                    return;
                }

                const rect = row.getBoundingClientRect();

                const next =
                    event.clientY - rect.top >
                    rect.height / 2;

                tbody.insertBefore(
                    dragging,
                    next ? row.nextSibling : row
                );
            });
        });
    }

    addButton.addEventListener('click', openCreateModal);

    modalClose.addEventListener('click', closeModal);
    modalCancel.addEventListener('click', closeModal);

    modalSave.addEventListener('click', saveCourse);

    searchInput.addEventListener('input', loadData);
    statusFilter.addEventListener('change', loadData);

    loadData();

});