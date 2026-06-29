document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('.tuition-sales-table');

    initTuitionCharts();
    initTuitionChartToggles();
    initTuitionStudentLookup();

    if (!table) {
        return;
    }

    initTuitionColumnFilters(table);
    initTuitionDetailModal();
    initTuitionColumnSettings(table);
    initTuitionCheckAll();
    initTuitionInlineEdit(table);
});

function initTuitionCharts() {
    const salesCanvas = document.getElementById('tuitionSalesChart');
    const compositionCanvas = document.getElementById('tuitionCompositionChart');

    if (salesCanvas && window.tuitionSalesChartData && typeof Chart !== 'undefined') {
        new Chart(salesCanvas, {
            data: {
                labels: window.tuitionSalesChartData.labels || [],
                datasets: [
                    ...(window.tuitionSalesChartData.datasets || []).map((dataset) => ({
                        type: 'bar',
                        label: dataset.label,
                        data: dataset.data,
                        stack: 'sales',
                    })),
                    {
                        type: 'line',
                        label: '回収率',
                        data: window.tuitionSalesChartData.collectionRates || [],
                        yAxisID: 'collectionRate',
                        borderWidth: 3,
                        tension: 0.35,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        stacked: true,
                    },
                    collectionRate: {
                        position: 'right',
                        beginAtZero: true,
                        min: 0,
                        max: 100,
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            callback(value) {
                                return value + '%';
                            },
                        },
                        title: {
                            display: true,
                            text: '回収率（%）',
                        },
                    },
                },
            },
        });
    }

    if (compositionCanvas && window.courseCompositionData && typeof Chart !== 'undefined') {
        new Chart(compositionCanvas, {
            type: 'doughnut',
            data: {
                labels: window.courseCompositionData.labels || [],
                datasets: [
                    {
                        data: window.courseCompositionData.data || [],
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        display: false,
                    },
                },
            },
        });
    }
}

function initTuitionChartToggles() {
    [
        ['tuitionSalesChartToggle', 'tuitionSalesChartBody'],
        ['tuitionCompositionChartToggle', 'tuitionCompositionChartBody'],
    ].forEach(([buttonId, bodyId]) => {
        const button = document.getElementById(buttonId);
        const body = document.getElementById(bodyId);

        if (!button || !body) {
            return;
        }

        button.addEventListener('click', () => {
            body.classList.toggle('is-collapsed');

            const icon = button.querySelector('i');

            if (icon) {
                icon.className = body.classList.contains('is-collapsed')
                    ? 'fas fa-plus'
                    : 'fas fa-minus';
            }
        });
    });
}

function initTuitionColumnFilters(table) {
    const buttons = document.querySelectorAll('.tuition-filter-button');
    let activePanel = null;

    const columnMap = {
        school: 2,
        student: 3,
        course_name: 5,
        attendance_type: 6,
        account_category: 7,
        payment_method: 9,
        discount_type: 12,
        payment_status: 16,
    };

    const rows = () => Array.from(table.querySelectorAll('tbody tr'));

    const closePanel = () => {
        if (activePanel) {
            activePanel.remove();
            activePanel = null;
        }
    };

    const getCellValue = (row, columnIndex) => {
        return row.children[columnIndex]?.innerText.trim() ?? '';
    };

    const getValueCounts = (columnIndex) => {
        const counts = new Map();

        rows().forEach((row) => {
            const value = getCellValue(row, columnIndex);

            if (!value || value.includes('授業料・入会金売上データがありません')) {
                return;
            }

            counts.set(value, (counts.get(value) ?? 0) + 1);
        });

        return [...counts.entries()].sort((a, b) => a[0].localeCompare(b[0], 'ja'));
    };

    const getActiveFilters = () => {
        const activeFilters = {};

        buttons.forEach((button) => {
            const selected = button.dataset.selected
                ? button.dataset.selected.split('|').filter(Boolean)
                : [];

            if (selected.length > 0) {
                activeFilters[button.dataset.filter] = selected;
            }
        });

        return activeFilters;
    };

    const applyFilter = () => {
        const activeFilters = getActiveFilters();

        rows().forEach((row) => {
            let visible = true;

            Object.entries(activeFilters).forEach(([filter, values]) => {
                const columnIndex = columnMap[filter];
                const cellValue = getCellValue(row, columnIndex);

                if (!values.includes(cellValue)) {
                    visible = false;
                }
            });

            row.style.display = visible ? '' : 'none';
        });
    };

    const updateFilterButton = (button, count) => {
        button.classList.toggle('is-filtered', count > 0);

        const badge = button.querySelector('.tuition-filter-count');

        if (!badge) {
            return;
        }

        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline-flex';
        } else {
            badge.textContent = '';
            badge.style.display = 'none';
        }
    };

    buttons.forEach((button) => {
        button.innerHTML = '<i class="fas fa-filter"></i><span class="tuition-filter-count" style="display:none;"></span>';

        button.addEventListener('click', (event) => {
            event.stopPropagation();
            closePanel();

            const filter = button.dataset.filter;
            const columnIndex = columnMap[filter];

            if (typeof columnIndex === 'undefined') {
                return;
            }

            const valueCounts = getValueCounts(columnIndex);
            const allValues = valueCounts.map(([value]) => value);
            const selectedValues = button.dataset.selected
                ? button.dataset.selected.split('|').filter(Boolean)
                : [];

            const panel = document.createElement('div');
            panel.className = 'tuition-filter-panel';

            panel.innerHTML = `
                <div class="tuition-filter-panel-title">フィルター</div>
                <input type="text" class="tuition-filter-search" placeholder="検索...">
                <div class="tuition-filter-panel-actions">
                    <button type="button" data-action="all">すべて選択</button>
                    <button type="button" data-action="clear">クリア</button>
                </div>
                <div class="tuition-filter-options">
                    ${valueCounts.map(([value, count]) => {
                        const checked = selectedValues.length === 0 || selectedValues.includes(value);

                        return `
                            <label>
                                <input type="checkbox" value="${escapeTuitionHtml(value)}" ${checked ? 'checked' : ''}>
                                <span class="tuition-filter-option-name">${escapeTuitionHtml(value)}</span>
                                <span class="tuition-filter-option-count">${count}</span>
                            </label>
                        `;
                    }).join('')}
                </div>
                <div class="tuition-filter-panel-footer">
                    <button type="button" data-action="apply">適用</button>
                </div>
            `;

            document.body.appendChild(panel);

            const rect = button.getBoundingClientRect();
            panel.style.top = `${rect.bottom + window.scrollY + 6}px`;
            panel.style.left = `${Math.max(12, rect.left + window.scrollX - 220)}px`;

            activePanel = panel;

            panel.addEventListener('click', (panelEvent) => {
                panelEvent.stopPropagation();
            });

            const searchInput = panel.querySelector('.tuition-filter-search');

            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    const keyword = searchInput.value.trim().toLowerCase();

                    panel.querySelectorAll('.tuition-filter-options label').forEach((label) => {
                        const text = label.innerText.trim().toLowerCase();
                        label.style.display = text.includes(keyword) ? '' : 'none';
                    });
                });
            }

            panel.querySelector('[data-action="all"]')?.addEventListener('click', () => {
                panel.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                    input.checked = true;
                });
            });

            panel.querySelector('[data-action="clear"]')?.addEventListener('click', () => {
                panel.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                    input.checked = false;
                });
            });

            panel.querySelector('[data-action="apply"]')?.addEventListener('click', () => {
                const checkedValues = Array.from(panel.querySelectorAll('input[type="checkbox"]:checked'))
                    .map((input) => input.value);

                const isAllSelected = checkedValues.length === allValues.length;

                button.dataset.selected = isAllSelected ? '' : checkedValues.join('|');

                updateFilterButton(button, isAllSelected ? 0 : checkedValues.length);
                applyFilter();
                closePanel();
            });
        });
    });

    document.addEventListener('click', closePanel);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePanel();
        }
    });
}

function initTuitionDetailModal() {
    const modal = document.getElementById('tuitionDetailModal');
    const body = document.getElementById('tuitionDetailBody');
    const close = document.getElementById('tuitionDetailClose');

    if (!modal || !body) {
        return;
    }

    const row = (label, value) => `
        <div class="tuition-detail-row">
            <span>${escapeTuitionHtml(label)}</span>
            <strong>${escapeTuitionHtml(value || '-')}</strong>
        </div>
    `;

    document.querySelectorAll('.tuition-detail-button').forEach((button) => {
        button.addEventListener('click', () => {
            body.innerHTML = `
                <div class="tuition-detail-section">
                    <h4>基本情報</h4>
                    <div class="tuition-detail-grid">
                        ${row('会計区分', button.dataset.category)}
                        ${row('教室', button.dataset.school)}
                        ${row('生徒', button.dataset.student)}
                        ${row('生徒コード', button.dataset.studentCode)}
                        ${row('コース', button.dataset.course)}
                        ${row('通塾種別', button.dataset.attendance)}
                    </div>
                </div>

                <div class="tuition-detail-section">
                    <h4>取引情報</h4>
                    <div class="tuition-detail-grid">
                        ${row('取引予定日', button.dataset.scheduledDate)}
                        ${row('取引日', button.dataset.transactionDate)}
                        ${row('入出金方法', button.dataset.paymentMethod)}
                        ${row('入金状態', button.dataset.paymentStatus)}
                    </div>
                </div>

                <div class="tuition-detail-section">
                    <h4>金額情報</h4>
                    <div class="tuition-detail-grid">
                        ${row('割引前金額', `¥${button.dataset.beforeDiscount}`)}
                        ${row('割引種別', button.dataset.discountType)}
                        ${row('割引額', `¥${button.dataset.discountAmount}`)}
                        ${row('取引額(税込)', `¥${button.dataset.amount}`)}
                    </div>
                </div>

                <div class="tuition-detail-section">
                    <h4>メモ</h4>

                    <div class="tuition-detail-grid">
                        ${row('取引メモ', button.dataset.transactionNote)}
                        ${row('割引メモ', button.dataset.discountNote)}
                    </div>
                </div>

                <div class="tuition-detail-section">
                    <h4>システム情報</h4>
                    <div class="tuition-detail-grid">
                        ${row('作成日時', button.dataset.createdAt)}
                        ${row('作成者', button.dataset.createdBy)}
                        ${row('更新日時', button.dataset.updatedAt)}
                        ${row('更新者', button.dataset.updatedBy)}

                        ${row('Invoice ID', button.dataset.invoiceId)}
                        ${row('Invoice Item ID', button.dataset.invoiceItemId)}
                        ${row('Account Transaction ID', button.dataset.accountTransactionId)}
                        ${row('Source', 'invoice_item')}
                    </div>
                </div>

                <div class="tuition-detail-footer">
                    <button type="button" class="tuition-history-button" onclick="alert('変更履歴は準備中です。')">
                        変更履歴
                    </button>
                </div>
            `;

            modal.classList.add('is-open');
        });
    });

    const closeModal = () => modal.classList.remove('is-open');

    close?.addEventListener('click', closeModal);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
}

function initTuitionColumnSettings(table) {
    const modal = document.getElementById('tuitionColumnModal');
    const button = document.getElementById('tuitionColumnSettingButton');
    const close = document.getElementById('tuitionColumnClose');
    const checkboxes = document.querySelectorAll('.tuition-column-list input[type="checkbox"]');

    if (!modal || !button) {
        return;
    }

    const storageKey = 'tuitionSalesVisibleColumns';

    const setColumnVisible = (columnIndex, visible) => {
        table.querySelectorAll('tr').forEach((row) => {
            const cell = row.children[columnIndex];

            if (cell) {
                cell.style.display = visible ? '' : 'none';
            }
        });
    };

    const saveSettings = () => {
        const settings = {};

        checkboxes.forEach((input) => {
            settings[input.dataset.column] = input.checked;
        });

        localStorage.setItem(storageKey, JSON.stringify(settings));
    };

    const loadSettings = () => {
        const saved = localStorage.getItem(storageKey);

        if (!saved) {
            return;
        }

        try {
            const settings = JSON.parse(saved);

            checkboxes.forEach((input) => {
                if (Object.prototype.hasOwnProperty.call(settings, input.dataset.column)) {
                    input.checked = settings[input.dataset.column];
                    setColumnVisible(Number(input.dataset.column), input.checked);
                }
            });
        } catch (error) {
            localStorage.removeItem(storageKey);
        }
    };

    button.addEventListener('click', () => {
        modal.classList.add('is-open');
    });

    close?.addEventListener('click', () => {
        modal.classList.remove('is-open');
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.classList.remove('is-open');
        }
    });

    checkboxes.forEach((input) => {
        input.addEventListener('change', () => {
            setColumnVisible(Number(input.dataset.column), input.checked);
            saveSettings();
        });
    });

    loadSettings();
}

function initTuitionCheckAll() {
    const checkAll = document.getElementById('tuitionCheckAll');
    const rowChecks = document.querySelectorAll('.tuition-row-check');

    if (!checkAll) {
        return;
    }

    checkAll.addEventListener('change', () => {
        rowChecks.forEach((checkbox) => {
            checkbox.checked = checkAll.checked;
        });
    });
}

function initTuitionInlineEdit(table) {
    const page = document.querySelector('.tuition-sales-page');
    const updateBase = page?.dataset.updateBase || '';

    table.querySelectorAll('tbody tr').forEach((row) => {
        const editButton = row.querySelector('.tuition-edit-button');
        const saveButton = row.querySelector('.tuition-save-button');
        const cancelButton = row.querySelector('.tuition-cancel-button');

        if (!editButton || !saveButton || !cancelButton) {
            return;
        }

        editButton.addEventListener('click', () => enterEditMode(row));
        cancelButton.addEventListener('click', () => cancelEditMode(row));
        saveButton.addEventListener('click', () => saveEditMode(row, updateBase));
    });
}

function enterEditMode(row) {
    row.classList.add('is-editing');

    row.querySelectorAll('[data-edit-field]').forEach((cell) => {
        const field = cell.dataset.editField;
        const value = cell.dataset.value || '';
        const display = cell.querySelector('.display-value');

        if (display) {
            display.style.display = 'none';
        }

        if (cell.querySelector('.tuition-edit-input')) {
            return;
        }

        if (field === 'payment_method_id') {
            const select = document.createElement('select');
            select.className = 'tuition-edit-input';

            (window.tuitionPaymentMethods || []).forEach((method) => {
                const option = document.createElement('option');
                option.value = method.id;
                option.textContent = method.name;

                if (String(method.id) === String(value)) {
                    option.selected = true;
                }

                select.appendChild(option);
            });

            cell.appendChild(select);
            return;
        }

        if (field === 'payment_status') {
            const select = document.createElement('select');
            select.className = 'tuition-edit-input';

            [
                ['paid', '入金済'],
                ['unpaid', '未入金'],
                ['cancelled', '取消'],
            ].forEach(([statusValue, label]) => {
                const option = document.createElement('option');
                option.value = statusValue;
                option.textContent = label;

                if (String(statusValue) === String(value)) {
                    option.selected = true;
                }

                select.appendChild(option);
            });

            cell.appendChild(select);
            return;
        }

        if (field === 'memo' || field === 'discount_note') {
            const input = document.createElement('input');
            input.className = 'tuition-edit-input tuition-edit-note-input';
            input.type = 'text';
            input.value = value;

            cell.appendChild(input);
            return;
        }

        const input = document.createElement('input');
        input.className = 'tuition-edit-input';

        if (field === 'scheduled_date' || field === 'transaction_date') {
            input.type = 'date';
            input.value = value;
        } else {
            input.type = 'number';
            input.min = '0';
            input.value = value;
        }

        cell.appendChild(input);
    });

    toggleEditButtons(row, true);
}

function cancelEditMode(row) {
    row.classList.remove('is-editing');

    row.querySelectorAll('[data-edit-field]').forEach((cell) => {
        const display = cell.querySelector('.display-value');
        const input = cell.querySelector('.tuition-edit-input');

        if (input) {
            input.remove();
        }

        if (display) {
            display.style.display = '';
        }
    });

    toggleEditButtons(row, false);
}

function saveEditMode(row, updateBase) {
    const rowId = row.dataset.rowId;

    if (!rowId || !updateBase) {
        alert('更新先URLを取得できません。');
        return;
    }

    const payload = {};

    row.querySelectorAll('[data-edit-field]').forEach((cell) => {
        const field = cell.dataset.editField;
        const input = cell.querySelector('.tuition-edit-input');

        if (input) {
            payload[field] = input.value;
        }
    });

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch(`${updateBase}/${rowId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
    })
        .then(async (response) => {
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                let message = data.message || '更新に失敗しました。';

                if (data.errors) {
                    message += '\n\n' + Object.entries(data.errors)
                        .map(([key, values]) => `${key}: ${values.join(' / ')}`)
                        .join('\n');
                }

                throw new Error(message);
            }

            return response.json();
        })
        .then(() => {
            window.location.reload();
        })
        .catch((error) => {
            alert(error.message || '更新に失敗しました。');
        });
}

function toggleEditButtons(row, editing) {
    const editButton = row.querySelector('.tuition-edit-button');
    const saveButton = row.querySelector('.tuition-save-button');
    const cancelButton = row.querySelector('.tuition-cancel-button');
    const detailButton = row.querySelector('.tuition-detail-button');

    if (editButton) {
        editButton.style.display = editing ? 'none' : '';
    }

    if (saveButton) {
        saveButton.style.display = editing ? '' : 'none';
    }

    if (cancelButton) {
        cancelButton.style.display = editing ? '' : 'none';
    }

    if (detailButton) {
        detailButton.style.display = editing ? 'none' : '';
    }
}

function escapeTuitionHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function initTuitionStudentLookup() {
    const input = document.getElementById('tuitionStudentLookupInput');
    const hidden = document.getElementById('tuitionStudentId');
    const results = document.getElementById('tuitionStudentLookupResults');
    const amountInput = document.querySelector('input[name="before_discount_amount"]');

    if (!input || !hidden || !results) {
        return;
    }

    let timer = null;

    input.addEventListener('input', () => {
        const keyword = input.value.trim();

        hidden.value = '';
        results.innerHTML = '';

        if (keyword.length < 1) {
            results.classList.remove('is-open');
            return;
        }

        clearTimeout(timer);

        timer = setTimeout(() => {
            fetch(`/admin/operations/classroom-accounting/tuition-enrollment-sales/students/search?q=${encodeURIComponent(keyword)}`, {
                headers: {
                    'Accept': 'application/json',
                },
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
                            <strong>${escapeTuitionHtml(student.label)}</strong>
                            <span>${escapeTuitionHtml(student.school_name || '-')} / ${escapeTuitionHtml(student.course_name || '-')} / ${escapeTuitionHtml(student.attendance_type || '-')}</span>
                        `;

                        item.addEventListener('click', () => {
                            input.value = student.label;
                            hidden.value = student.id;

                            if (amountInput && student.monthly_fee) {
                                amountInput.value = student.monthly_fee;
                            }

                            results.innerHTML = '';
                            results.classList.remove('is-open');
                        });

                        results.appendChild(item);
                    });

                    results.classList.add('is-open');
                });
        }, 250);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.tuition-student-lookup')) {
            results.classList.remove('is-open');
        }
    });
}

