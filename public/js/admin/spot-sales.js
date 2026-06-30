let spotSalesAmountChartInstance = null;
let spotCountChartInstance = null;
let spotSalesColumnFilterActive = false;
let spotSalesActiveChartPeriod = null;

function escapeSpotHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatSpotYen(value) {
    return '¥' + Number(value || 0).toLocaleString();
}

function updateSpotSaleTotals() {
    const beforeInput = document.getElementById('spotBeforeDiscountAmount');
    const discountInput = document.getElementById('spotDiscountAmount');
    const subtotal = Math.max(0, Number(beforeInput?.value || 0));
    const discount = Math.min(Math.max(0, Number(discountInput?.value || 0)), subtotal);
    const total = Math.max(0, subtotal - discount);

    if (discountInput && Number(discountInput.value || 0) > subtotal) {
        discountInput.value = subtotal;
    }

    const subtotalDisplay = document.getElementById('spotSubtotal');
    const discountDisplay = document.getElementById('spotDiscountTotal');
    const grandTotalDisplay = document.getElementById('spotGrandTotal');

    if (subtotalDisplay) subtotalDisplay.textContent = formatSpotYen(subtotal);
    if (discountDisplay) discountDisplay.textContent = formatSpotYen(discount);
    if (grandTotalDisplay) grandTotalDisplay.textContent = formatSpotYen(total);
}

function setupSpotStudentLookup() {
    const input = document.getElementById('spotStudentLookupInput');
    const hidden = document.getElementById('spotStudentId');
    const results = document.getElementById('spotStudentLookupResults');
    if (!input || !hidden || !results) return;

    let timer = null;
    input.addEventListener('input', function () {
        const keyword = input.value.trim();
        hidden.value = '';
        results.innerHTML = '';
        results.classList.remove('is-open');
        clearTimeout(timer);
        if (keyword.length < 1) return;

        timer = setTimeout(() => {
            fetch(`/admin/operations/classroom-accounting/spot-sales/students/search?q=${encodeURIComponent(keyword)}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((students) => {
                    results.innerHTML = '';
                    if (!students.length) {
                        results.innerHTML = '<div class="tuition-student-lookup-empty">該当する生徒がいません</div>';
                        results.classList.add('is-open');
                        return;
                    }
                    students.forEach((student) => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'tuition-student-lookup-item';
                        item.innerHTML = `<strong>${escapeSpotHtml(student.label)}</strong><span>${escapeSpotHtml(student.school_name || '-')}</span>`;
                        item.addEventListener('click', () => {
                            input.value = student.label;
                            hidden.value = student.id;
                            results.innerHTML = '';
                            results.classList.remove('is-open');
                        });
                        results.appendChild(item);
                    });
                    results.classList.add('is-open');
                });
        }, 250);
    });
}

function setupSpotCreateForm() {
    const beforeInput = document.getElementById('spotBeforeDiscountAmount');
    const discountInput = document.getElementById('spotDiscountAmount');
    const clearButton = document.querySelector('.shop-sale-clear-button');
    [beforeInput, discountInput].forEach((input) => {
        if (input) input.addEventListener('input', updateSpotSaleTotals);
    });
    if (clearButton) clearButton.addEventListener('click', () => setTimeout(updateSpotSaleTotals, 0));
    updateSpotSaleTotals();
}

function setupColumnSetting() {
    const openButton = document.getElementById('tuitionColumnSettingButton');
    const modal = document.getElementById('tuitionColumnModal');
    const closeButton = document.getElementById('tuitionColumnClose');
    if (!openButton || !modal || !closeButton) return;

    openButton.addEventListener('click', () => modal.classList.add('is-open'));
    closeButton.addEventListener('click', () => modal.classList.remove('is-open'));
    modal.querySelectorAll('input[type="checkbox"][data-column]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const index = Number(checkbox.dataset.column) + 1;
            document.querySelectorAll(`.spot-sales-table tr > *:nth-child(${index})`).forEach((cell) => {
                cell.style.display = checkbox.checked ? '' : 'none';
            });
        });
    });
}

function setupDetailModal() {
    const modal = document.getElementById('tuitionDetailModal');
    const body = document.getElementById('tuitionDetailBody');
    const closeButton = document.getElementById('tuitionDetailClose');
    if (!modal || !body || !closeButton) return;

    document.querySelectorAll('.tuition-detail-button').forEach((button) => {
        button.addEventListener('click', () => {
            const data = button.dataset;
            const rows = [
                ['ID', data.id], ['教室', data.school], ['生徒', data.student], ['生徒コード', data.studentCode],
                ['売上分類', data.category], ['取引名', data.saleTitle], ['数量', data.quantity], ['会計区分', data.accountCategory],
                ['取引予定日', data.scheduledDate], ['入出金方法', data.paymentMethod], ['取引日', data.transactionDate],
                ['割引種別', data.discountType], ['割引前金額', `¥${data.beforeDiscount}`], ['割引額', `¥${data.discountAmount}`],
                ['取引額(税込)', `¥${data.amount}`], ['入金状態', data.paymentStatus], ['取引メモ', data.transactionNote],
                ['割引メモ', data.discountNote], ['作成者', data.createdBy], ['更新者', data.updatedBy],
            ];
            body.innerHTML = `<dl class="tuition-detail-grid event-detail-grid">${rows.map(([label, value]) => `<dt>${escapeSpotHtml(label)}</dt><dd>${escapeSpotHtml(value || '-')}</dd>`).join('')}</dl>`;
            modal.classList.add('is-open');
        });
    });

    closeButton.addEventListener('click', () => modal.classList.remove('is-open'));
    modal.addEventListener('click', (event) => { if (event.target === modal) modal.classList.remove('is-open'); });
}

function createEditInput(field, value) {
    if (field === 'payment_status') {
        return `<select class="tuition-edit-input" data-field="payment_status"><option value="unpaid" ${value === 'unpaid' ? 'selected' : ''}>未入金</option><option value="paid" ${value === 'paid' ? 'selected' : ''}>入金済</option><option value="cancelled" ${value === 'cancelled' ? 'selected' : ''}>取消</option></select>`;
    }
    if (field === 'payment_method_id') {
        const options = (window.spotPaymentMethods || []).map((method) => `<option value="${method.id}" ${String(method.id) === String(value) ? 'selected' : ''}>${escapeSpotHtml(method.name)}</option>`).join('');
        return `<select class="tuition-edit-input" data-field="payment_method_id"><option value="">未設定</option>${options}</select>`;
    }
    if (field === 'scheduled_date' || field === 'transaction_date') {
        return `<input type="date" class="tuition-edit-input" data-field="${field}" value="${escapeSpotHtml(value || '')}">`;
    }
    if (['quantity', 'before_discount_amount', 'discount_amount'].includes(field)) {
        return `<input type="number" min="0" class="tuition-edit-input" data-field="${field}" value="${escapeSpotHtml(value || 0)}">`;
    }
    return `<input type="text" class="tuition-edit-input" data-field="${field}" value="${escapeSpotHtml(value || '')}">`;
}

function setupInlineEdit() {
    document.querySelectorAll('.tuition-edit-button').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row) return;
            document.querySelectorAll('.spot-sales-table tbody tr.is-editing').forEach((editingRow) => {
                if (editingRow === row) return;
                editingRow.querySelectorAll('[data-edit-field]').forEach((cell) => { if (cell.dataset.originalHtml) cell.innerHTML = cell.dataset.originalHtml; });
                editingRow.classList.remove('is-editing');
                editingRow.querySelector('.tuition-edit-button')?.style.removeProperty('display');
                editingRow.querySelector('.tuition-detail-button')?.style.removeProperty('display');
                const saveButton = editingRow.querySelector('.tuition-save-button');
                const cancelButton = editingRow.querySelector('.tuition-cancel-button');
                if (saveButton) saveButton.style.display = 'none';
                if (cancelButton) cancelButton.style.display = 'none';
            });
            row.classList.add('is-editing');
            row.querySelectorAll('[data-edit-field]').forEach((cell) => {
                const field = cell.dataset.editField;
                const value = cell.dataset.value || '';
                cell.dataset.originalHtml = cell.innerHTML;
                cell.innerHTML = createEditInput(field, value);
            });
            button.style.display = 'none';
            row.querySelector('.tuition-detail-button').style.display = 'none';
            row.querySelector('.tuition-save-button').style.display = '';
            row.querySelector('.tuition-cancel-button').style.display = '';
        });
    });

    document.querySelectorAll('.tuition-cancel-button').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row) return;
            row.querySelectorAll('[data-edit-field]').forEach((cell) => { cell.innerHTML = cell.dataset.originalHtml || cell.innerHTML; });
            row.classList.remove('is-editing');
            row.querySelector('.tuition-edit-button').style.display = '';
            row.querySelector('.tuition-detail-button').style.display = '';
            row.querySelector('.tuition-save-button').style.display = 'none';
            row.querySelector('.tuition-cancel-button').style.display = 'none';
        });
    });

    document.querySelectorAll('.tuition-save-button').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            const page = document.querySelector('.spot-sales-page');
            if (!row || !page) return;
            const payload = {};
            row.querySelectorAll('.tuition-edit-input').forEach((input) => { payload[input.dataset.field] = input.value; });
            payload.discount_type_id = row.dataset.discountTypeId || null;
            payload._token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            payload._method = 'PUT';
            fetch(`${page.dataset.updateBase}/${row.dataset.rowId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': payload._token },
                body: JSON.stringify(payload),
            })
                .then(async (response) => {
                    if (!response.ok) {
                        const error = await response.json().catch(() => ({ message: '更新に失敗しました。' }));
                        throw new Error(error.message || '更新に失敗しました。');
                    }
                    return response.json();
                })
                .then(() => window.location.reload())
                .catch((error) => alert(error.message));
        });
    });
}

function setupCheckAll() {
    const checkAll = document.getElementById('tuitionCheckAll');
    if (!checkAll) return;
    checkAll.addEventListener('change', () => {
        document.querySelectorAll('.tuition-row-check').forEach((checkbox) => { checkbox.checked = checkAll.checked; });
    });
}

function setupChartToggle() {
    const button = document.getElementById('eventChartToggle');
    const body = document.getElementById('eventChartBody');
    if (!button || !body) return;
    button.addEventListener('click', () => {
        const hidden = body.style.display === 'none';
        body.style.display = hidden ? '' : 'none';
        button.textContent = hidden ? '−' : '+';
    });
}


function getSelectedSpotChartPeriod() {
    const active = document.querySelector('.tuition-chart-periods a.active[data-chart-period]');
    return spotSalesActiveChartPeriod || active?.dataset.chartPeriod || 'all';
}

function parseSpotChartDate(rawDate) {
    const text = String(rawDate || '').trim();
    if (!text || text === '-') return null;
    const match = text.match(/^(\d{4})[-\/](\d{1,2})(?:[-\/](\d{1,2}))?/);
    if (!match) return null;
    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3] || 1));
}

function getSpotMonthKey(date) {
    if (!date) return '';
    return `${date.getFullYear()}/${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function getSpotPeriodBounds(rows, period) {
    const now = new Date();
    const end = new Date(now.getFullYear(), now.getMonth(), 1);

    if (!period || period === '1year') {
        const start = new Date(end.getFullYear(), end.getMonth(), 1);
        start.setMonth(start.getMonth() - 11);
        return { start, end };
    }

    if (period === '3years') {
        const start = new Date(end.getFullYear(), end.getMonth(), 1);
        start.setMonth(start.getMonth() - 35);
        return { start, end };
    }

    if (period === '5years') {
        const start = new Date(end.getFullYear(), end.getMonth(), 1);
        start.setMonth(start.getMonth() - 59);
        return { start, end };
    }

    const dates = rows
        .map((row) => parseSpotChartDate(row.date || row.dataset?.chartDate))
        .filter(Boolean)
        .sort((a, b) => a.getTime() - b.getTime());

    if (!dates.length) {
        const start = new Date(end.getFullYear(), end.getMonth(), 1);
        start.setMonth(start.getMonth() - 11);
        return { start, end };
    }

    const first = dates[0];
    return { start: new Date(first.getFullYear(), first.getMonth(), 1), end };
}

function getSpotMonthLabelsBetween(start, end) {
    if (!start || !end) return [];
    const labels = [];
    const cursor = new Date(start.getFullYear(), start.getMonth(), 1);
    const finish = new Date(end.getFullYear(), end.getMonth(), 1);

    while (cursor.getTime() <= finish.getTime()) {
        labels.push(getSpotMonthKey(cursor));
        cursor.setMonth(cursor.getMonth() + 1);
    }

    return labels;
}

function getSpotVisibleTableRows() {
    return Array.from(document.querySelectorAll('.spot-sales-table tbody tr'))
        .filter((row) => row.dataset.rowId && row.style.display !== 'none');
}

function buildSpotChartDataFromVisibleRows(period = getSelectedSpotChartPeriod()) {
    const rows = getSpotVisibleTableRows().map((row) => ({
        date: row.dataset.chartDate,
        amount: Number(row.dataset.chartAmount || 0),
    }));

    const { start, end } = getSpotPeriodBounds(rows, period);
    const labels = getSpotMonthLabelsBetween(start, end);
    const monthly = new Map(labels.map((label) => [label, { month: label, amount: 0, count: 0 }]));

    rows.forEach((row) => {
        const date = parseSpotChartDate(row.date);
        if (!date) return;
        const monthDate = new Date(date.getFullYear(), date.getMonth(), 1);
        if (!start || !end || monthDate < start || monthDate > end) return;
        const label = getSpotMonthKey(monthDate);
        if (!monthly.has(label)) return;
        const item = monthly.get(label);
        item.amount += row.amount;
        item.count += 1;
    });

    return Array.from(monthly.values());
}

function buildSpotServerChartRows() {
    const amountRows = window.spotSalesChartData || [];
    const countRows = window.spotCountChartData || [];
    const countMap = new Map(countRows.map((row) => [row.month, Number(row.count || 0)]));

    return amountRows.map((row) => ({
        month: row.month,
        amount: Number(row.amount || 0),
        count: countMap.get(row.month) || 0,
    }));
}

function updateSpotTableSummary() {
    const summary = document.getElementById('eventTableSummary');
    if (!summary) return;

    const visibleCount = getSpotVisibleTableRows().length;
    const totalAll = Number(summary.dataset.totalAll || summary.dataset.totalFiltered || visibleCount);

    summary.textContent = `${visibleCount.toLocaleString()} 件 / 全 ${totalAll.toLocaleString()} 件`;
}

function setSpotChartEmptyState(amountRows, countRows) {
    const amountEmpty = document.getElementById('spotSalesAmountEmpty');
    const countEmpty = document.getElementById('spotCountEmpty');
    const amountCanvas = document.getElementById('spotSalesAmountChart');
    const countCanvas = document.getElementById('spotCountChart');
    const amountIsEmpty = !amountRows.length || amountRows.every((row) => Number(row.amount || 0) === 0);
    const countIsEmpty = !countRows.length || countRows.every((row) => Number(row.count || 0) === 0);

    if (amountEmpty) amountEmpty.classList.toggle('is-visible', amountIsEmpty);
    if (countEmpty) countEmpty.classList.toggle('is-visible', countIsEmpty);
    if (amountCanvas) amountCanvas.classList.toggle('is-empty', amountIsEmpty);
    if (countCanvas) countCanvas.classList.toggle('is-empty', countIsEmpty);
}

function renderSpotCharts(chartRows = null) {
    if (typeof Chart === 'undefined') return;
    const amountCanvas = document.getElementById('spotSalesAmountChart');
    const countCanvas = document.getElementById('spotCountChart');
    const rows = chartRows || (spotSalesColumnFilterActive ? buildSpotChartDataFromVisibleRows() : buildSpotServerChartRows());
    const amountData = rows.map((row) => ({ month: row.month, amount: Number(row.amount || 0) }));
    const countData = rows.map((row) => ({ month: row.month, count: Number(row.count || 0) }));

    if (spotSalesAmountChartInstance) {
        spotSalesAmountChartInstance.destroy();
        spotSalesAmountChartInstance = null;
    }
    if (spotCountChartInstance) {
        spotCountChartInstance.destroy();
        spotCountChartInstance = null;
    }

    setSpotChartEmptyState(amountData, countData);

    if (amountCanvas) {
        spotSalesAmountChartInstance = new Chart(amountCanvas, {
            type: 'line',
            data: { labels: amountData.map((row) => row.month), datasets: [{ label: '売上額', data: amountData.map((row) => row.amount), tension: 0.25 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: (value) => formatSpotYen(value) } } } },
        });
    }

    if (countCanvas) {
        spotCountChartInstance = new Chart(countCanvas, {
            type: 'bar',
            data: { labels: countData.map((row) => row.month), datasets: [{ label: '件数', data: countData.map((row) => row.count) }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            stepSize: 1,
                            callback: (value) => Number.isInteger(Number(value)) ? Number(value).toLocaleString() : '',
                        },
                    },
                },
            },
        });
    }
}

function setupSpotChartPeriodButtons() {
    const chartPeriods = document.querySelector('.tuition-chart-periods');
    if (!chartPeriods) return;

    chartPeriods.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-chart-period]');
        if (!link || !chartPeriods.contains(link)) return;

        const period = link.dataset.chartPeriod || 'all';
        spotSalesActiveChartPeriod = period;

        chartPeriods.querySelectorAll('a[data-chart-period]').forEach((item) => item.classList.remove('active'));
        link.classList.add('active');

        if (spotSalesColumnFilterActive) {
            event.preventDefault();
            event.stopPropagation();
            renderSpotCharts(buildSpotChartDataFromVisibleRows(period));
            return;
        }

        const href = link.getAttribute('href');
        if (href) {
            event.preventDefault();
            window.location.href = href;
        }
    });
}

function setupSpotMemoTooltip() {
    const cells = document.querySelectorAll('.event-memo-cell[data-tooltip]');
    if (!cells.length) return;

    let tooltip = document.getElementById('eventMemoFloatingTooltip');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'eventMemoFloatingTooltip';
        tooltip.className = 'event-memo-floating-tooltip';
        document.body.appendChild(tooltip);
    }

    const hide = () => {
        tooltip.classList.remove('is-visible');
        tooltip.textContent = '';
    };

    cells.forEach((cell) => {
        cell.addEventListener('mouseenter', () => {
            const text = String(cell.dataset.tooltip || '').trim();
            if (!text || text === '-') return;
            tooltip.textContent = text;
            tooltip.classList.add('is-visible');
        });

        cell.addEventListener('mousemove', (event) => {
            if (!tooltip.classList.contains('is-visible')) return;
            const padding = 14;
            const rect = tooltip.getBoundingClientRect();
            let left = event.clientX + 14;
            let top = event.clientY + 14;
            if (left + rect.width + padding > window.innerWidth) {
                left = Math.max(padding, event.clientX - rect.width - 14);
            }
            if (top + rect.height + padding > window.innerHeight) {
                top = Math.max(padding, event.clientY - rect.height - 14);
            }
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
        });

        cell.addEventListener('mouseleave', hide);
    });
}

function setupColumnFilter() {
    const popover = document.getElementById('eventColumnFilterPopover');
    const list = document.getElementById('eventColumnFilterList');
    const search = document.getElementById('eventColumnFilterSearch');
    const apply = document.getElementById('eventColumnFilterApply');
    const clear = document.getElementById('eventColumnFilterClear');
    const selectAll = document.getElementById('eventColumnFilterSelectAll');
    let activeColumn = null;
    if (!popover || !list) return;

    const close = () => { popover.classList.remove('is-open'); popover.setAttribute('aria-hidden', 'true'); };
    const valuesForColumn = (index) => Array.from(document.querySelectorAll(`.spot-sales-table tbody tr td:nth-child(${index})`)).map((cell) => cell.innerText.trim()).filter(Boolean);
    const render = (values) => {
        const keyword = (search?.value || '').toLowerCase();
        const uniqueValues = Array.from(new Set(values)).filter((value) => value.toLowerCase().includes(keyword));
        list.innerHTML = uniqueValues.map((value) => `<label><input type="checkbox" value="${escapeSpotHtml(value)}" checked> ${escapeSpotHtml(value)}</label>`).join('');
    };

    document.querySelectorAll('.event-column-filter-button').forEach((button) => {
        button.addEventListener('click', (event) => {
            activeColumn = Number(button.dataset.filterColumn);
            const values = valuesForColumn(activeColumn);
            render(values);
            const rect = button.getBoundingClientRect();
            popover.style.left = `${rect.left + window.scrollX}px`;
            popover.style.top = `${rect.bottom + window.scrollY + 8}px`;
            popover.classList.add('is-open');
            popover.setAttribute('aria-hidden', 'false');
            event.stopPropagation();
        });
    });
    if (search) search.addEventListener('input', () => { if (activeColumn) render(valuesForColumn(activeColumn)); });
    if (selectAll) selectAll.addEventListener('click', () => list.querySelectorAll('input').forEach((input) => { input.checked = true; }));
    if (clear) clear.addEventListener('click', () => list.querySelectorAll('input').forEach((input) => { input.checked = false; }));
    if (apply) apply.addEventListener('click', () => {
        if (!activeColumn) return;
        const allowed = Array.from(list.querySelectorAll('input:checked')).map((input) => input.value);
        spotSalesColumnFilterActive = true;
        document.querySelectorAll('.spot-sales-table tbody tr').forEach((row) => {
            const cell = row.querySelector(`td:nth-child(${activeColumn})`);
            row.style.display = !cell || allowed.includes(cell.innerText.trim()) ? '' : 'none';
        });
        close();
        updateSpotTableSummary();
        renderSpotCharts(buildSpotChartDataFromVisibleRows(getSelectedSpotChartPeriod()));
    });
    document.addEventListener('click', (event) => { if (!popover.contains(event.target) && !event.target.closest('.event-column-filter-button')) close(); });
}

document.addEventListener('DOMContentLoaded', () => {
    setupSpotStudentLookup();
    setupSpotCreateForm();
    setupColumnSetting();
    setupDetailModal();
    setupInlineEdit();
    setupCheckAll();
    setupChartToggle();
    setupColumnFilter();
    setupSpotChartPeriodButtons();
    setupSpotMemoTooltip();
    updateSpotTableSummary();
    renderSpotCharts();
});
