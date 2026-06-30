let eventSalesAmountChartInstance = null;
let eventApplicationCountChartInstance = null;
let eventSalesColumnFilterActive = false;
let eventSalesActiveChartPeriod = null;

function escapeEventHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatEventYen(value) {
    return '¥' + Number(value || 0).toLocaleString();
}

function updateEventSaleTotals() {
    const beforeInput = document.getElementById('eventBeforeDiscountAmount');
    const discountInput = document.getElementById('eventDiscountAmount');
    const subtotal = Math.max(0, Number(beforeInput?.value || 0));
    const discount = Math.min(Math.max(0, Number(discountInput?.value || 0)), subtotal);
    const total = Math.max(0, subtotal - discount);

    if (discountInput && Number(discountInput.value || 0) > subtotal) {
        discountInput.value = subtotal;
    }

    const subtotalDisplay = document.getElementById('eventSubtotal');
    const discountDisplay = document.getElementById('eventDiscountTotal');
    const grandTotalDisplay = document.getElementById('eventGrandTotal');

    if (subtotalDisplay) subtotalDisplay.textContent = formatEventYen(subtotal);
    if (discountDisplay) discountDisplay.textContent = formatEventYen(discount);
    if (grandTotalDisplay) grandTotalDisplay.textContent = formatEventYen(total);
}

function setupEventStudentLookup() {
    const input = document.getElementById('eventStudentLookupInput');
    const hidden = document.getElementById('eventStudentId');
    const results = document.getElementById('eventStudentLookupResults');

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
            fetch(`/admin/operations/classroom-accounting/event-sales/students/search?q=${encodeURIComponent(keyword)}`, {
                headers: { Accept: 'application/json' },
            })
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
                        item.innerHTML = `
                            <strong>${escapeEventHtml(student.label)}</strong>
                            <span>${escapeEventHtml(student.school_name || '-')}</span>
                        `;
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

function setupEventLookup() {
    const input = document.getElementById('eventLookupInput');
    const eventId = document.getElementById('eventId');
    const scheduleId = document.getElementById('eventScheduleId');
    const priceId = document.getElementById('eventPriceId');
    const scheduleDisplay = document.getElementById('eventScheduleDisplay');
    const participationType = document.getElementById('eventParticipationType');
    const beforeInput = document.getElementById('eventBeforeDiscountAmount');
    const results = document.getElementById('eventLookupResults');

    if (!input || !eventId || !scheduleId || !priceId || !results) return;

    let timer = null;

    input.addEventListener('input', function () {
        const keyword = input.value.trim();
        eventId.value = '';
        scheduleId.value = '';
        priceId.value = '';
        if (scheduleDisplay) scheduleDisplay.value = '日程を選択';
        results.innerHTML = '';
        results.classList.remove('is-open');
        clearTimeout(timer);

        if (keyword.length < 1) return;

        timer = setTimeout(() => {
            fetch(`/admin/operations/classroom-accounting/event-sales/events/search?q=${encodeURIComponent(keyword)}`, {
                headers: { Accept: 'application/json' },
            })
                .then((response) => response.json())
                .then((events) => {
                    results.innerHTML = '';

                    if (!events.length) {
                        results.innerHTML = '<div class="tuition-student-lookup-empty">該当するイベントがありません</div>';
                        results.classList.add('is-open');
                        return;
                    }

                    events.forEach((event) => {
                        const priceText = event.default_price ? formatEventYen(event.default_price) : '¥0';
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'tuition-student-lookup-item';
                        item.innerHTML = `
                            <strong>${escapeEventHtml(event.label)}</strong>
                            <span>${escapeEventHtml(event.schedule_label || '日程未設定')} / ${escapeEventHtml(event.default_participation_type || '通常参加')} / ${priceText}</span>
                        `;
                        item.addEventListener('click', () => {
                            input.value = event.label;
                            eventId.value = event.id;
                            scheduleId.value = event.schedule_id || '';
                            priceId.value = event.default_price_id || '';
                            if (scheduleDisplay) scheduleDisplay.value = event.schedule_label || '日程未設定';
                            if (participationType) participationType.value = event.default_participation_type || '通常参加';
                            if (beforeInput) beforeInput.value = Number(event.default_price || 0);
                            results.innerHTML = '';
                            results.classList.remove('is-open');
                            updateEventSaleTotals();
                        });
                        results.appendChild(item);
                    });

                    results.classList.add('is-open');
                });
        }, 250);
    });
}

function setupEventCreateForm() {
    const beforeInput = document.getElementById('eventBeforeDiscountAmount');
    const discountInput = document.getElementById('eventDiscountAmount');
    const clearButton = document.querySelector('.shop-sale-clear-button');

    [beforeInput, discountInput].forEach((input) => {
        if (input) input.addEventListener('input', updateEventSaleTotals);
    });

    if (clearButton) {
        clearButton.addEventListener('click', () => {
            setTimeout(updateEventSaleTotals, 0);
        });
    }

    updateEventSaleTotals();
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
            document.querySelectorAll(`.event-sales-table tr > *:nth-child(${index})`).forEach((cell) => {
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
                ['ID', data.id],
                ['教室', data.school],
                ['生徒', data.student],
                ['生徒コード', data.studentCode],
                ['イベント名', data.eventTitle],
                ['イベント開催日', data.eventDate],
                ['参加区分', data.participationType],
                ['会計区分', data.accountCategory],
                ['取引予定日', data.scheduledDate],
                ['入出金方法', data.paymentMethod],
                ['取引日', data.transactionDate],
                ['割引種別', data.discountType],
                ['割引前金額', `¥${data.beforeDiscount || '0'}`],
                ['割引額', `¥${data.discountAmount || '0'}`],
                ['取引額(税込)', `¥${data.amount || '0'}`],
                ['入金状態', data.paymentStatus],
                ['申込状態', data.applicationStatus],
                ['申込メモ', data.applicationMemo],
                ['会計取引ID', data.accountTransactionId],
                ['登録者', data.createdBy],
                ['更新者', data.updatedBy],
                ['作成日時', data.createdAt],
                ['更新日時', data.updatedAt],
                ['取引メモ', data.transactionNote],
                ['割引メモ', data.discountNote],
            ];

            body.innerHTML = `
                <dl class="tuition-detail-grid event-detail-grid">
                    ${rows.map(([label, value]) => `
                        <dt>${escapeEventHtml(label)}</dt>
                        <dd>${escapeEventHtml(value || '-')}</dd>
                    `).join('')}
                </dl>
            `;
            modal.classList.add('is-open');
        });
    });

    closeButton.addEventListener('click', () => modal.classList.remove('is-open'));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) modal.classList.remove('is-open');
    });
}

function createEditInput(field, value, row) {
    if (field === 'payment_status') {
        return `
            <select class="tuition-edit-input" data-field="payment_status">
                <option value="unpaid" ${value === 'unpaid' ? 'selected' : ''}>未入金</option>
                <option value="paid" ${value === 'paid' ? 'selected' : ''}>入金済</option>
                <option value="cancelled" ${value === 'cancelled' ? 'selected' : ''}>取消</option>
            </select>
        `;
    }

    if (field === 'payment_method_id') {
        const options = (window.eventPaymentMethods || []).map((method) => {
            return `<option value="${method.id}" ${String(method.id) === String(value) ? 'selected' : ''}>${escapeEventHtml(method.name)}</option>`;
        }).join('');
        return `<select class="tuition-edit-input" data-field="payment_method_id"><option value="">未設定</option>${options}</select>`;
    }

    if (field === 'scheduled_date' || field === 'transaction_date') {
        return `<input type="date" class="tuition-edit-input" data-field="${field}" value="${escapeEventHtml(value || '')}">`;
    }

    if (field === 'before_discount_amount' || field === 'discount_amount') {
        return `<input type="number" min="0" class="tuition-edit-input" data-field="${field}" value="${escapeEventHtml(value || 0)}">`;
    }

    return `<input type="text" class="tuition-edit-input" data-field="${field}" value="${escapeEventHtml(value || '')}">`;
}

function setupInlineEdit() {
    document.querySelectorAll('.tuition-edit-button').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row) return;

            document.querySelectorAll('.event-sales-table tbody tr.is-editing').forEach((editingRow) => {
                if (editingRow === row) return;
                editingRow.querySelectorAll('[data-edit-field]').forEach((cell) => {
                    if (cell.dataset.originalHtml) {
                        cell.innerHTML = cell.dataset.originalHtml;
                    }
                });
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
                cell.innerHTML = createEditInput(field, value, row);
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

            row.querySelectorAll('[data-edit-field]').forEach((cell) => {
                cell.innerHTML = cell.dataset.originalHtml || cell.innerHTML;
            });
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
            const page = document.querySelector('.event-sales-page');
            if (!row || !page) return;

            const payload = {};
            row.querySelectorAll('.tuition-edit-input').forEach((input) => {
                payload[input.dataset.field] = input.value;
            });

            payload.discount_type_id = row.dataset.discountTypeId || null;
            payload._token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            payload._method = 'PUT';

            fetch(`${page.dataset.updateBase}/${row.dataset.rowId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': payload._token,
                },
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
        document.querySelectorAll('.tuition-row-check').forEach((checkbox) => {
            checkbox.checked = checkAll.checked;
        });
    });
}


function getEventVisibleTableRows() {
    const table = document.querySelector('.event-sales-table');
    if (!table) return [];
    return Array.from(table.querySelectorAll('tbody tr')).filter((row) => {
        if (row.querySelector('td[colspan]')) return false;
        return !row.classList.contains('is-column-filter-hidden');
    });
}

function updateEventTableFilteredSummary(isFiltered = false) {
    const summary = document.getElementById('eventTableSummary');
    const total = Number(summary?.dataset.total || 0);
    const visible = getEventVisibleTableRows().length;
    const pagination = document.querySelector('.event-sales-page .tuition-pagination');

    if (summary) {
        summary.textContent = isFiltered ? `${visible}件 / 全${total.toLocaleString()}件` : `全 ${total.toLocaleString()} 件`;
    }

    if (pagination) {
        pagination.classList.toggle('is-column-filtered', isFiltered);
        let note = pagination.querySelector('.event-filtered-pagination-note');
        if (isFiltered) {
            if (!note) {
                note = document.createElement('div');
                note.className = 'event-filtered-pagination-note';
                pagination.appendChild(note);
            }
            note.textContent = `${visible}件 / 全${total.toLocaleString()}件`;
        } else if (note) {
            note.remove();
        }
    }
}

function getEventMonthLabel(rawDate) {
    const text = String(rawDate || '').trim();
    if (!text || text === '-') return null;
    const match = text.match(/^(\d{4})[-\/](\d{1,2})/);
    if (!match) return null;
    return `${match[1]}/${String(match[2]).padStart(2, '0')}`;
}

function parseEventAmount(value) {
    return Number(String(value || '0').replace(/[¥,\s]/g, '')) || 0;
}


function getEventChartActivePeriod() {
    const active = document.querySelector('.tuition-chart-periods a.active[data-chart-period]');
    return eventSalesActiveChartPeriod || active?.dataset.chartPeriod || 'all';
}

function setEventChartEmptyState(salesEmpty, countEmpty) {
    const salesEmptyEl = document.getElementById('eventSalesAmountEmpty');
    const countEmptyEl = document.getElementById('eventApplicationCountEmpty');
    const salesCanvas = document.getElementById('eventSalesAmountChart');
    const countCanvas = document.getElementById('eventApplicationCountChart');

    if (salesEmptyEl) salesEmptyEl.classList.toggle('is-visible', Boolean(salesEmpty));
    if (countEmptyEl) countEmptyEl.classList.toggle('is-visible', Boolean(countEmpty));
    if (salesCanvas) salesCanvas.classList.toggle('is-empty', Boolean(salesEmpty));
    if (countCanvas) countCanvas.classList.toggle('is-empty', Boolean(countEmpty));
}

function parseEventDate(rawDate) {
    const text = String(rawDate || '').trim();
    if (!text || text === '-') return null;
    const match = text.match(/^(\d{4})[-\/](\d{1,2})(?:[-\/](\d{1,2}))?/);
    if (!match) return null;
    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3] || 1));
}

function getEventRowChartDate(row) {
    return parseEventDate(row?.dataset?.chartDate || row?.querySelector('td:nth-child(9)')?.textContent);
}

function getEventPeriodBounds(rows, period) {
    const dates = rows
        .map((row) => getEventRowChartDate(row))
        .filter(Boolean)
        .sort((a, b) => a.getTime() - b.getTime());

    if (!dates.length) return { start: null, end: null };

    const first = dates[0];
    const last = dates[dates.length - 1];
    const end = new Date(last.getFullYear(), last.getMonth(), 1);

    if (!period || period === 'all') {
        return {
            start: new Date(first.getFullYear(), first.getMonth(), 1),
            end,
        };
    }

    const start = new Date(end.getFullYear(), end.getMonth(), 1);
    if (period === '1year') start.setFullYear(start.getFullYear() - 1);
    if (period === '3years') start.setFullYear(start.getFullYear() - 3);
    if (period === '5years') start.setFullYear(start.getFullYear() - 5);

    return { start, end };
}

function getEventMonthKey(date) {
    if (!date) return '';
    return `${date.getFullYear()}/${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function getEventMonthLabelsBetween(start, end) {
    if (!start || !end) return [];
    const labels = [];
    const cursor = new Date(start.getFullYear(), start.getMonth(), 1);
    const finish = new Date(end.getFullYear(), end.getMonth(), 1);

    while (cursor.getTime() <= finish.getTime()) {
        labels.push(getEventMonthKey(cursor));
        cursor.setMonth(cursor.getMonth() + 1);
    }

    return labels;
}

function getEventRowsForChartPeriod(rows, period) {
    const { start, end } = getEventPeriodBounds(rows, period);
    if (!start || !end) return [];
    return rows.filter((row) => {
        const date = getEventRowChartDate(row);
        if (!date) return false;
        const month = new Date(date.getFullYear(), date.getMonth(), 1);
        return month >= start && month <= end;
    });
}

function hasEventColumnFilterHiddenRows() {
    const table = document.querySelector('.event-sales-table');
    if (!table) return false;
    return Boolean(table.querySelector('tbody tr.is-column-filter-hidden'));
}

function updateEventChartsFromVisibleRows(isFiltered = false, period = null) {
    if (!eventSalesAmountChartInstance && !eventApplicationCountChartInstance) return;

    const activePeriod = period || getEventChartActivePeriod();

    if (!isFiltered) {
        const salesData = window.eventSalesChartData || { labels: [], amounts: [] };
        const countData = window.eventApplicationChartData || { labels: [], counts: [] };
        const salesLabels = salesData.labels || [];
        const salesAmounts = salesData.amounts || [];
        const countLabels = countData.labels || [];
        const countCounts = countData.counts || [];

        if (eventSalesAmountChartInstance) {
            eventSalesAmountChartInstance.data.labels = salesLabels;
            eventSalesAmountChartInstance.data.datasets[0].data = salesAmounts;
            eventSalesAmountChartInstance.update();
        }
        if (eventApplicationCountChartInstance) {
            eventApplicationCountChartInstance.data.labels = countLabels;
            eventApplicationCountChartInstance.data.datasets[0].data = countCounts;
            eventApplicationCountChartInstance.update();
        }
        setEventChartEmptyState(salesAmounts.length === 0 || salesAmounts.every((value) => Number(value || 0) === 0), countCounts.length === 0 || countCounts.every((value) => Number(value || 0) === 0));
        return;
    }

    const baseRows = getEventVisibleTableRows();
    const { start, end } = getEventPeriodBounds(baseRows, activePeriod);
    const visibleRows = getEventRowsForChartPeriod(baseRows, activePeriod);
    const labels = getEventMonthLabelsBetween(start, end);
    const monthly = new Map(labels.map((label) => [label, { amount: 0, count: 0 }]));

    visibleRows.forEach((row) => {
        const date = getEventRowChartDate(row);
        const label = getEventMonthKey(date);
        if (!label || !monthly.has(label)) return;
        const amount = parseEventAmount(row.dataset.chartAmount || row.querySelector('td:nth-child(15)')?.textContent);
        monthly.get(label).amount += amount;
        monthly.get(label).count += 1;
    });

    const amounts = labels.map((label) => monthly.get(label)?.amount || 0);
    const counts = labels.map((label) => monthly.get(label)?.count || 0);

    if (eventSalesAmountChartInstance) {
        eventSalesAmountChartInstance.data.labels = labels;
        eventSalesAmountChartInstance.data.datasets[0].data = amounts;
        eventSalesAmountChartInstance.update();
    }
    if (eventApplicationCountChartInstance) {
        eventApplicationCountChartInstance.data.labels = labels;
        eventApplicationCountChartInstance.data.datasets[0].data = counts;
        eventApplicationCountChartInstance.update();
    }

    setEventChartEmptyState(amounts.length === 0 || amounts.every((value) => Number(value || 0) === 0), counts.length === 0 || counts.every((value) => Number(value || 0) === 0));
}

function setupEventColumnFilters() {
    const table = document.querySelector('.event-sales-table');
    const popover = document.getElementById('eventColumnFilterPopover');
    const searchInput = document.getElementById('eventColumnFilterSearch');
    const list = document.getElementById('eventColumnFilterList');
    const selectAllButton = document.getElementById('eventColumnFilterSelectAll');
    const clearButton = document.getElementById('eventColumnFilterClear');
    const applyButton = document.getElementById('eventColumnFilterApply');

    if (!table || !popover || !searchInput || !list || !selectAllButton || !clearButton || !applyButton) return;

    const filters = new Map();
    let currentColumn = null;
    let currentValues = [];

    const rows = () => Array.from(table.querySelectorAll('tbody tr')).filter((row) => !row.querySelector('td[colspan]'));

    const normalize = (value) => String(value || '-').replace(/\s+/g, ' ').trim() || '-';

    const getCellText = (row, columnIndex) => {
        const cell = row.querySelector(`td:nth-child(${columnIndex})`);
        return normalize(cell ? cell.textContent : '-');
    };

    const buildValues = (columnIndex) => {
        const counts = new Map();
        rows().forEach((row) => {
            const value = getCellText(row, columnIndex);
            counts.set(value, (counts.get(value) || 0) + 1);
        });

        return Array.from(counts.entries())
            .map(([value, count]) => ({ value, count }))
            .sort((a, b) => a.value.localeCompare(b.value, 'ja'));
    };

    const renderList = () => {
        const keyword = normalize(searchInput.value).toLowerCase();
        const selected = filters.get(currentColumn) || new Set(currentValues.map((item) => item.value));
        const filtered = currentValues.filter((item) => item.value.toLowerCase().includes(keyword === '-' ? '' : keyword));

        list.innerHTML = filtered.map((item) => `
            <label class="event-column-filter-item">
                <input type="checkbox" value="${escapeEventHtml(item.value)}" ${selected.has(item.value) ? 'checked' : ''}>
                <span>${escapeEventHtml(item.value)}</span>
                <span class="event-column-filter-count">${item.count}</span>
            </label>
        `).join('');
    };

    const applyFilters = () => {
        rows().forEach((row) => {
            let visible = true;
            filters.forEach((selected, columnIndex) => {
                if (selected.size === 0) {
                    visible = false;
                    return;
                }
                if (!selected.has(getCellText(row, columnIndex))) {
                    visible = false;
                }
            });
            row.classList.toggle('is-column-filter-hidden', !visible);
        });

        let hasActiveFilter = false;
        document.querySelectorAll('.event-column-filter-button').forEach((button) => {
            const column = Number(button.dataset.filterColumn);
            const allCount = buildValues(column).length;
            const selectedCount = filters.get(column)?.size ?? allCount;
            const active = selectedCount < allCount;
            if (active) hasActiveFilter = true;
            button.classList.toggle('is-active', active);
        });

        eventSalesColumnFilterActive = hasActiveFilter;
        updateEventTableFilteredSummary(hasActiveFilter);
        updateEventChartsFromVisibleRows(hasActiveFilter, getEventChartActivePeriod());
    };

    const closePopover = () => {
        popover.classList.remove('is-open');
        popover.setAttribute('aria-hidden', 'true');
        document.querySelectorAll('.event-column-filter-button').forEach((button) => button.classList.remove('is-current'));
    };

    document.querySelectorAll('.event-column-filter-button').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            currentColumn = Number(button.dataset.filterColumn);
            currentValues = buildValues(currentColumn);
            if (!filters.has(currentColumn)) {
                filters.set(currentColumn, new Set(currentValues.map((item) => item.value)));
            }

            searchInput.value = '';
            renderList();

            const rect = button.getBoundingClientRect();
            const left = Math.min(rect.left, window.innerWidth - 292);
            popover.style.left = `${Math.max(12, left)}px`;
            popover.style.top = `${Math.min(rect.bottom + 8, window.innerHeight - 390)}px`;
            popover.classList.add('is-open');
            popover.setAttribute('aria-hidden', 'false');
        });
    });

    searchInput.addEventListener('input', renderList);

    selectAllButton.addEventListener('click', () => {
        list.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => checkbox.checked = true);
    });

    clearButton.addEventListener('click', () => {
        list.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => checkbox.checked = false);
    });

    applyButton.addEventListener('click', () => {
        if (!currentColumn) return;
        const selected = new Set(Array.from(list.querySelectorAll('input[type="checkbox"]:checked')).map((checkbox) => checkbox.value));
        filters.set(currentColumn, selected);
        applyFilters();
        closePopover();
    });

    document.addEventListener('click', (event) => {
        if (!popover.classList.contains('is-open')) return;
        if (popover.contains(event.target)) return;
        if (event.target.closest('.event-column-filter-button')) return;
        closePopover();
    });
}

function setupEventSalesCharts() {
    if (typeof Chart === 'undefined') return;

    const salesCanvas = document.getElementById('eventSalesAmountChart');
    const countCanvas = document.getElementById('eventApplicationCountChart');
    const salesData = window.eventSalesChartData || { labels: [], amounts: [] };
    const countData = window.eventApplicationChartData || { labels: [], counts: [] };

    if (salesCanvas) {
        eventSalesAmountChartInstance = new Chart(salesCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: salesData.labels || [],
                datasets: [{
                    label: '売上額（税込）',
                    data: salesData.amounts || [],
                    borderColor: '#2f9df6',
                    backgroundColor: 'rgba(47, 157, 246, 0.12)',
                    pointBackgroundColor: '#2f9df6',
                    pointBorderColor: '#2f9df6',
                    borderWidth: 3,
                    fill: false,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'line',
                        },
                    },
                },
                scales: { y: { beginAtZero: true, suggestedMin: 0, ticks: { precision: 0 } } },
            },
        });
    }

    if (countCanvas) {
        eventApplicationCountChartInstance = new Chart(countCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: countData.labels || [],
                datasets: [{
                    label: '申込数（件）',
                    data: countData.counts || [],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true, suggestedMin: 0, ticks: { precision: 0 } } },
            },
        });
    }

    setEventChartEmptyState(
        !(salesData.amounts || []).length || (salesData.amounts || []).every((value) => Number(value || 0) === 0),
        !(countData.counts || []).length || (countData.counts || []).every((value) => Number(value || 0) === 0)
    );
}


function setupEventChartPeriodButtons() {
    const chartPeriods = document.querySelector('.tuition-chart-periods');
    if (!chartPeriods) return;

    chartPeriods.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-chart-period]');
        if (!link || !chartPeriods.contains(link)) return;

        const period = link.dataset.chartPeriod || 'all';
        eventSalesActiveChartPeriod = period;

        chartPeriods.querySelectorAll('a[data-chart-period]').forEach((item) => item.classList.remove('active'));
        link.classList.add('active');

        const isColumnFiltered = eventSalesColumnFilterActive || hasEventColumnFilterHiddenRows();
        if (isColumnFiltered) {
            event.preventDefault();
            event.stopPropagation();
            updateEventChartsFromVisibleRows(true, period);
            return;
        }

        const href = link.getAttribute('href');
        if (href) {
            event.preventDefault();
            window.location.href = href;
        }
    });
}

function setupEventChartToggle() {
    const button = document.getElementById('eventChartToggle');
    const body = document.getElementById('eventChartBody');
    if (!button || !body) return;

    button.addEventListener('click', () => {
        body.classList.toggle('is-hidden');
        button.textContent = body.classList.contains('is-hidden') ? '+' : '−';
    });
}


function setupEventMemoTooltip() {
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

function setupFloatingTooltipFallback() {
    if (typeof setupFloatingTooltip === 'function') {
        setupFloatingTooltip();
    }
}

setupEventStudentLookup();
setupEventLookup();
setupEventCreateForm();
setupCheckAll();
setupColumnSetting();
setupEventColumnFilters();
setupDetailModal();
setupInlineEdit();
setupFloatingTooltipFallback();
setupEventMemoTooltip();
setupEventSalesCharts();
setupEventChartPeriodButtons();
setupEventChartToggle();
