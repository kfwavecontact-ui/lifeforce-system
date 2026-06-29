document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('.account-ledger-table');

    /*
     * 会計台帳テーブルがない画面では、このJS全体を動かさない。
     */
    if (!table) return;

    const buttons = document.querySelectorAll('.account-filter-button');
    let activePanel = null;

    const columnMap = {
        transaction_type: 1,
        account_category: 2,
        school: 4,
        student: 5,
        payment_method: 7,
        status: 12,
        discount_type: 13,
    };

    const closePanel = () => {
        if (activePanel) {
            activePanel.remove();
            activePanel = null;
        }
    };

    const rows = () => Array.from(table.querySelectorAll('tbody tr'));

    const getCellValue = (row, columnIndex) => {
        return row.children[columnIndex]?.innerText.trim() ?? '';
    };

    const getValueCounts = (columnIndex) => {
        const counts = new Map();

        rows().forEach(row => {
            const value = getCellValue(row, columnIndex);
            if (!value) return;

            counts.set(value, (counts.get(value) ?? 0) + 1);
        });

        return [...counts.entries()].sort((a, b) => a[0].localeCompare(b[0], 'ja'));
    };

    const getActiveFilters = () => {
        const activeFilters = {};

        buttons.forEach(button => {
            const values = button.dataset.selected
                ? button.dataset.selected.split('|').filter(Boolean)
                : [];

            if (values.length > 0) {
                activeFilters[button.dataset.filter] = values;
            }
        });

        return activeFilters;
    };

    const updateFilterButtonLabel = (button, selectedCount) => {
        const countBadge = button.querySelector('.account-filter-count');

        if (!countBadge) return;

        if (selectedCount > 0) {
            countBadge.textContent = selectedCount;
            countBadge.style.display = 'inline-flex';
        } else {
            countBadge.textContent = '';
            countBadge.style.display = 'none';
        }
    };

    const applyFilter = () => {
        const activeFilters = getActiveFilters();

        rows().forEach(row => {
            let visible = true;

            Object.entries(activeFilters).forEach(([filter, values]) => {
                const index = columnMap[filter];
                const cellValue = getCellValue(row, index);

                if (!values.includes(cellValue)) {
                    visible = false;
                }
            });

            row.style.display = visible ? '' : 'none';
        });
    };

    /*
     * Excel風フィルタ
     */
    buttons.forEach(button => {
        button.innerHTML = `
            <i class="fas fa-filter"></i>
            <span class="account-filter-count" style="display:none;"></span>
        `;

        button.addEventListener('click', event => {
            event.stopPropagation();
            closePanel();

            const filter = button.dataset.filter;
            const columnIndex = columnMap[filter];
            const valueCounts = getValueCounts(columnIndex);
            const allValues = valueCounts.map(([value]) => value);

            const selected = button.dataset.selected
                ? button.dataset.selected.split('|').filter(Boolean)
                : [];

            const panel = document.createElement('div');
            panel.className = 'account-filter-panel';

            panel.innerHTML = `
                <div class="account-filter-panel-title">フィルタ</div>

                <input
                    type="text"
                    class="account-filter-search"
                    placeholder="検索..."
                >

                <div class="account-filter-panel-actions">
                    <button type="button" data-action="all">すべて選択</button>
                    <button type="button" data-action="clear">クリア</button>
                </div>

                <div class="account-filter-options">
                    ${valueCounts.map(([value, count]) => `
                        <label>
                            <input type="checkbox" value="${value}" ${selected.length === 0 || selected.includes(value) ? 'checked' : ''}>
                            <span class="account-filter-option-name">${value}</span>
                            <span class="account-filter-option-count">${count}</span>
                        </label>
                    `).join('')}
                </div>

                <div class="account-filter-panel-footer">
                    <button type="button" data-action="apply">適用</button>
                </div>
            `;

            document.body.appendChild(panel);

            const rect = button.getBoundingClientRect();
            panel.style.top = `${rect.bottom + window.scrollY + 6}px`;
            panel.style.left = `${rect.left + window.scrollX - 210}px`;

            activePanel = panel;

            const filterSearchInput = panel.querySelector('.account-filter-search');

            if (filterSearchInput) {
                filterSearchInput.addEventListener('input', () => {
                    const keyword = filterSearchInput.value.trim().toLowerCase();

                    panel.querySelectorAll('.account-filter-options label').forEach(label => {
                        const text = label.innerText.trim().toLowerCase();
                        label.style.display = text.includes(keyword) ? '' : 'none';
                    });
                });
            }

            panel.addEventListener('click', e => e.stopPropagation());

            panel.querySelector('[data-action="all"]').addEventListener('click', () => {
                panel.querySelectorAll('input[type="checkbox"]').forEach(input => {
                    input.checked = true;
                });
            });

            panel.querySelector('[data-action="clear"]').addEventListener('click', () => {
                panel.querySelectorAll('input[type="checkbox"]').forEach(input => {
                    input.checked = false;
                });
            });

            panel.querySelector('[data-action="apply"]').addEventListener('click', () => {
                const checkedValues = Array.from(panel.querySelectorAll('input[type="checkbox"]:checked'))
                    .map(input => input.value);

                const isAllSelected = checkedValues.length === allValues.length;

                button.dataset.selected = isAllSelected ? '' : checkedValues.join('|');
                button.classList.toggle('is-filtered', !isAllSelected);

                updateFilterButtonLabel(button, isAllSelected ? 0 : checkedValues.length);

                applyFilter();
                closePanel();
            });
        });
    });

    /*
     * 詳細モーダル
     */
    const detailModal = document.getElementById('accountDetailModal');
    const detailModalBody = document.getElementById('accountDetailBody');
    const detailModalClose = document.getElementById('accountDetailClose');
    const detailButtons = document.querySelectorAll('.account-detail-button');

    const closeDetailModal = () => {
        if (!detailModal) return;
        detailModal.classList.remove('is-open');
    };

    const detailRow = (label, value) => {
        return `
            <div class="account-detail-row">
                <span>${label}</span>
                <strong>${value || '-'}</strong>
            </div>
        `;
    };

    detailButtons.forEach(button => {
        button.addEventListener('click', () => {
            if (!detailModal || !detailModalBody) return;

            detailModalBody.innerHTML = `
                <div class="account-detail-section">
                    <h4>基本情報</h4>
                    <div class="account-detail-grid">
                        ${detailRow('ID', button.dataset.id)}
                        ${detailRow('取引名', button.dataset.name)}
                        ${detailRow('会計カテゴリ', button.dataset.category)}
                        ${detailRow('収支区分', button.dataset.type)}
                        ${detailRow('教室', button.dataset.school)}
                        ${detailRow('生徒', button.dataset.student)}
                        ${detailRow('取引予定日', button.dataset.scheduledDate)}
                        ${detailRow('取引日', button.dataset.transactionDate)}
                        ${detailRow('入出金方法', button.dataset.paymentMethod)}
                        ${detailRow('状態', button.dataset.status)}
                    </div>
                </div>

                <div class="account-detail-section">
                    <h4>金額情報</h4>
                    <div class="account-detail-grid">
                        ${detailRow('割引前金額', `¥${button.dataset.beforeDiscount}`)}
                        ${detailRow('割引種別', button.dataset.discountType)}
                        ${detailRow('割引額', `¥${button.dataset.discountAmount}`)}
                        ${detailRow('取引額（税込）', `¥${button.dataset.amount}`)}
                    </div>
                </div>

                <div class="account-detail-section">
                    <h4>元データ</h4>
                    <div class="account-detail-grid">
                        ${detailRow('元データテーブル', button.dataset.sourceTable)}
                        ${detailRow('元データID', button.dataset.sourceId)}
                    </div>
                </div>

                <div class="account-detail-section">
                    <h4>備考</h4>
                    <div class="account-detail-grid">
                        ${detailRow('割引メモ', button.dataset.discountNote)}
                    </div>
                </div>
            `;

            detailModal.classList.add('is-open');
        });
    });

    if (detailModalClose) {
        detailModalClose.addEventListener('click', closeDetailModal);
    }

    if (detailModal) {
        detailModal.addEventListener('click', event => {
            if (event.target === detailModal) {
                closeDetailModal();
            }
        });
    }

    /*
     * 表示項目設定
     */
    const columnModal = document.getElementById('accountColumnModal');
    const columnButton = document.getElementById('accountColumnSettingButton');
    const columnClose = document.getElementById('accountColumnClose');
    const columnCheckboxes = document.querySelectorAll('.account-column-list input[type="checkbox"]');
    const columnStorageKey = 'accountLedgerVisibleColumns';

    const setColumnVisible = (columnIndex, visible) => {
        table.querySelectorAll('tr').forEach(row => {
            const cell = row.children[columnIndex];

            if (cell) {
                cell.style.display = visible ? '' : 'none';
            }
        });
    };

    const saveColumnSettings = () => {
        const settings = {};

        columnCheckboxes.forEach(input => {
            settings[input.dataset.column] = input.checked;
        });

        localStorage.setItem(columnStorageKey, JSON.stringify(settings));
    };

    const loadColumnSettings = () => {
        const saved = localStorage.getItem(columnStorageKey);

        if (!saved) return;

        let settings = {};

        try {
            settings = JSON.parse(saved);
        } catch (error) {
            localStorage.removeItem(columnStorageKey);
            return;
        }

        columnCheckboxes.forEach(input => {
            if (Object.prototype.hasOwnProperty.call(settings, input.dataset.column)) {
                input.checked = settings[input.dataset.column];
                setColumnVisible(Number(input.dataset.column), input.checked);
            }
        });
    };

    if (columnButton && columnModal) {
        columnButton.addEventListener('click', () => {
            columnModal.classList.add('is-open');
        });
    }

    if (columnClose && columnModal) {
        columnClose.addEventListener('click', () => {
            columnModal.classList.remove('is-open');
        });
    }

    if (columnModal) {
        columnModal.addEventListener('click', event => {
            if (event.target === columnModal) {
                columnModal.classList.remove('is-open');
            }
        });
    }

    columnCheckboxes.forEach(input => {
        input.addEventListener('change', () => {
            setColumnVisible(Number(input.dataset.column), input.checked);
            saveColumnSettings();
        });
    });

    loadColumnSettings();


    /*
     * 共通クローズ処理
     */
    document.addEventListener('click', closePanel);

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;

        closePanel();
        closeDetailModal();

        if (columnModal) {
            columnModal.classList.remove('is-open');
        }
    });
});