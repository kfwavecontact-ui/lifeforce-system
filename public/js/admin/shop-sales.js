function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatYen(value) {
    return '¥' + Number(value || 0).toLocaleString();
}

function getRowUnitPrice(row) {
    return Number(row.dataset.unitPrice || 0);
}

function updateShopSaleTotals() {
    let subtotal = 0;
    let discountTotal = 0;
    let grandTotal = 0;

    document.querySelectorAll('.shop-sale-items-row').forEach((row) => {
        const unitPrice = getRowUnitPrice(row);
        const quantityInput = row.querySelector('.shop-quantity-input');
        const discountInput = row.querySelector('.shop-discount-input');
        const unitPriceDisplay = row.querySelector('.shop-unit-price');
        const rowTotalDisplay = row.querySelector('.shop-row-total');

        const quantity = Math.max(1, Number(quantityInput?.value || 1));
        const discount = Math.max(0, Number(discountInput?.value || 0));
        const rowSubtotal = unitPrice * quantity;
        const rowDiscount = Math.min(discount, rowSubtotal);
        const rowTotal = Math.max(0, rowSubtotal - rowDiscount);

        subtotal += rowSubtotal;
        discountTotal += rowDiscount;
        grandTotal += rowTotal;

        if (unitPriceDisplay) {
            unitPriceDisplay.textContent = formatYen(unitPrice);
        }

        if (rowTotalDisplay) {
            rowTotalDisplay.textContent = formatYen(rowTotal);
        }

        if (discountInput && Number(discountInput.value || 0) > rowSubtotal) {
            discountInput.value = rowSubtotal;
        }
    });

    const subtotalDisplay = document.getElementById('shopSubtotal');
    const discountDisplay = document.getElementById('shopDiscountTotal');
    const grandTotalDisplay = document.getElementById('shopGrandTotal');

    if (subtotalDisplay) {
        subtotalDisplay.textContent = formatYen(subtotal);
    }

    if (discountDisplay) {
        discountDisplay.textContent = formatYen(discountTotal);
    }

    if (grandTotalDisplay) {
        grandTotalDisplay.textContent = formatYen(grandTotal);
    }
}

function setupStudentLookup() {
    const input = document.getElementById('tuitionStudentLookupInput');
    const hidden = document.getElementById('tuitionStudentId');
    const results = document.getElementById('tuitionStudentLookupResults');

    if (!input || !hidden || !results) {
        return;
    }

    let timer = null;

    input.addEventListener('input', function () {
        const keyword = input.value.trim();

        hidden.value = '';
        results.innerHTML = '';
        results.classList.remove('is-open');

        clearTimeout(timer);

        if (keyword.length < 1) {
            return;
        }

        timer = setTimeout(() => {
            fetch(`/admin/operations/classroom-accounting/shop-sales/students/search?q=${encodeURIComponent(keyword)}`, {
                headers: {
                    Accept: 'application/json',
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
                            <strong>${escapeHtml(student.label)}</strong>
                            <span>${escapeHtml(student.school_name || '-')} / ${escapeHtml(student.course_name || '-')} / ${escapeHtml(student.attendance_type || '-')}</span>
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

function setupProductLookupForRow(row) {
    const input = row.querySelector('.shop-product-lookup-input');
    const hidden = row.querySelector('.shop-product-id');
    const results = row.querySelector('.shop-product-lookup-results');

    if (!input || !hidden || !results) {
        return;
    }

    let timer = null;

    input.addEventListener('input', function () {
        const keyword = input.value.trim();

        hidden.value = '';
        row.dataset.unitPrice = '0';
        updateShopSaleTotals();

        results.innerHTML = '';
        results.classList.remove('is-open');

        clearTimeout(timer);

        if (keyword.length < 1) {
            return;
        }

        timer = setTimeout(() => {
            fetch(`/admin/operations/classroom-accounting/shop-sales/product-search?keyword=${encodeURIComponent(keyword)}`, {
                headers: {
                    Accept: 'application/json',
                },
            })
                .then((response) => response.json())
                .then((products) => {
                    results.innerHTML = '';

                    if (!products.length) {
                        results.innerHTML = '<div class="tuition-student-lookup-empty">該当する商品がありません</div>';
                        results.classList.add('is-open');
                        return;
                    }

                    products.forEach((product) => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'tuition-student-lookup-item';

                        item.innerHTML = `
                            <strong>${escapeHtml(product.name)}</strong>
                            <span>${escapeHtml(product.product_code || '-')} / ${formatYen(product.price)} / 在庫 ${product.stock_quantity ?? 0}</span>
                        `;

                        item.addEventListener('click', () => {
                            input.value = `${product.product_code || ''} ${product.name}`.trim();
                            hidden.value = product.id;
                            row.dataset.unitPrice = Number(product.price || 0);
                            results.innerHTML = '';
                            results.classList.remove('is-open');
                            updateShopSaleTotals();
                        });

                        results.appendChild(item);
                    });

                    results.classList.add('is-open');
                });
        }, 250);
    });
}

function setupProductLookups() {
    document.querySelectorAll('.shop-sale-items-row').forEach((row) => {
        setupProductLookupForRow(row);
    });
}

function refreshShopItemIndexes() {
    document.querySelectorAll('.shop-sale-items-row').forEach((row, index) => {
        row.dataset.index = index;

        const productHidden = row.querySelector('.shop-product-id');
        const quantityInput = row.querySelector('.shop-quantity-input');
        const discountInput = row.querySelector('.shop-discount-input');

        if (productHidden) {
            productHidden.name = `items[${index}][shop_product_id]`;
        }

        if (quantityInput) {
            quantityInput.name = `items[${index}][quantity]`;
        }

        if (discountInput) {
            discountInput.name = `items[${index}][discount_amount]`;
        }
    });
}

function createShopItemRow(index) {
    const row = document.createElement('div');
    row.className = 'shop-sale-items-row';
    row.dataset.index = index;
    row.dataset.unitPrice = '0';

    row.innerHTML = `
        <label class="shop-sale-field shop-product-lookup">
            <span>商品</span>
            <input
                type="text"
                class="shop-product-lookup-input"
                placeholder="商品コード・商品名で検索"
                autocomplete="off"
            >
            <input
                type="hidden"
                name="items[${index}][shop_product_id]"
                class="shop-product-id"
                required
            >
            <div class="shop-product-lookup-results"></div>
        </label>

        <div class="shop-sale-field shop-price-display">
            <span>単価</span>
            <div class="shop-display-box shop-unit-price">¥0</div>
        </div>

        <label class="shop-sale-field">
            <span>数量</span>
            <input type="number" name="items[${index}][quantity]" class="shop-quantity-input" min="1" value="1" required>
        </label>

        <label class="shop-sale-field">
            <span>割引</span>
            <input type="number" name="items[${index}][discount_amount]" class="shop-discount-input" min="0" value="0" required>
        </label>

        <div class="shop-sale-field shop-price-display">
            <span>金額</span>
            <div class="shop-display-box shop-row-total">¥0</div>
        </div>

        <button type="button" class="shop-remove-item-button">削除</button>

    `;

    setupProductLookupForRow(row);

    return row;
}

function setupAddItemButton() {
    const button = document.getElementById('shopAddItemButton');
    const body = document.getElementById('shopSaleItemsBody');

    if (!button || !body) {
        return;
    }

    button.addEventListener('click', function () {
        const index = body.querySelectorAll('.shop-sale-items-row').length;
        body.appendChild(createShopItemRow(index));
        refreshShopItemIndexes();
        updateShopSaleTotals();
    });
}

document.addEventListener('input', function (event) {
    if (
        event.target.classList.contains('shop-quantity-input') ||
        event.target.classList.contains('shop-discount-input')
    ) {
        updateShopSaleTotals();
    }
});

document.addEventListener('click', function (event) {
    if (event.target.classList.contains('shop-remove-item-button')) {
        const rows = document.querySelectorAll('.shop-sale-items-row');

        if (rows.length <= 1) {
            return;
        }

        event.target.closest('.shop-sale-items-row')?.remove();
        refreshShopItemIndexes();
        updateShopSaleTotals();
        return;
    }

    if (
        !event.target.closest('.tuition-student-lookup') &&
        !event.target.closest('.shop-product-lookup')
    ) {
        document.querySelectorAll('.tuition-student-lookup-results, .shop-product-lookup-results').forEach(function (result) {
            result.classList.remove('is-open');
        });
    }
});

setupStudentLookup();
setupProductLookups();
setupAddItemButton();
refreshShopItemIndexes();
updateShopSaleTotals();

function setupFloatingTooltip() {
    const tooltip = document.createElement('div');
    tooltip.className = 'lf-floating-tooltip';
    document.body.appendChild(tooltip);

    document.addEventListener('mouseover', function (event) {
        const target = event.target.closest('.lf-tooltip');

        if (!target) {
            return;
        }

        const text = target.dataset.tooltip || '';

        if (!text.trim()) {
            return;
        }

        tooltip.textContent = text;
        tooltip.style.display = 'block';

        const rect = target.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();

        let left = rect.left;
        let top = rect.bottom + 8;

        if (left + tooltipRect.width > window.innerWidth - 12) {
            left = window.innerWidth - tooltipRect.width - 12;
        }

        if (top + tooltipRect.height > window.innerHeight - 12) {
            top = rect.top - tooltipRect.height - 8;
        }

        if (top < 12) {
            top = 12;
        }

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    });

    document.addEventListener('mouseout', function (event) {
        if (!event.target.closest('.lf-tooltip')) {
            return;
        }

        tooltip.style.display = 'none';
        tooltip.textContent = '';
    });
}

setupFloatingTooltip();

function setupColumnSetting() {
    const openButton = document.getElementById('tuitionColumnSettingButton');
    const modal = document.getElementById('tuitionColumnModal');
    const closeButton = document.getElementById('tuitionColumnClose');

    if (!openButton || !modal || !closeButton) {
        return;
    }

    openButton.addEventListener('click', function () {
        modal.classList.add('is-open');
    });

    closeButton.addEventListener('click', function () {
        modal.classList.remove('is-open');
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.classList.remove('is-open');
        }
    });

    modal.querySelectorAll('input[data-column]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const columnIndex = Number(checkbox.dataset.column) + 1;
            const display = checkbox.checked ? '' : 'none';

            document.querySelectorAll(`.tuition-sales-table tr > *:nth-child(${columnIndex})`).forEach(function (cell) {
                cell.style.display = display;
            });
        });
    });
}

function setupDetailModal() {
    const modal = document.getElementById('tuitionDetailModal');
    const body = document.getElementById('tuitionDetailBody');
    const closeButton = document.getElementById('tuitionDetailClose');

    if (!modal || !body || !closeButton) {
        return;
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.tuition-detail-button');

        if (!button) {
            return;
        }

        body.innerHTML = `
            <dl class="tuition-detail-grid">
                <dt>ID</dt><dd>${escapeHtml(button.dataset.id)}</dd>
                <dt>教室</dt><dd>${escapeHtml(button.dataset.school)}</dd>
                <dt>生徒</dt><dd>${escapeHtml(button.dataset.student)}</dd>
                <dt>生徒コード</dt><dd>${escapeHtml(button.dataset.studentCode)}</dd>
                <dt>商品名</dt><dd>${escapeHtml(button.dataset.itemSummary)}</dd>
                <dt>商品明細</dt><dd style="white-space:pre-line;">${escapeHtml(button.dataset.itemDetailSummary)}</dd>
                <dt>商品数</dt><dd>${escapeHtml(button.dataset.totalQuantity)}</dd>
                <dt>取引予定日</dt><dd>${escapeHtml(button.dataset.scheduledDate)}</dd>
                <dt>入出金方法</dt><dd>${escapeHtml(button.dataset.paymentMethod)}</dd>
                <dt>取引日</dt><dd>${escapeHtml(button.dataset.transactionDate)}</dd>
                <dt>割引種別</dt><dd>${escapeHtml(button.dataset.discountType)}</dd>
                <dt>割引前金額</dt><dd>¥${escapeHtml(button.dataset.beforeDiscount)}</dd>
                <dt>割引額</dt><dd>¥${escapeHtml(button.dataset.discountAmount)}</dd>
                <dt>取引額(税込)</dt><dd>¥${escapeHtml(button.dataset.amount)}</dd>
                <dt>入金状態</dt><dd>${escapeHtml(button.dataset.paymentStatus)}</dd>
                <dt>取引メモ</dt><dd>${escapeHtml(button.dataset.transactionNote)}</dd>
                <dt>割引メモ</dt><dd>${escapeHtml(button.dataset.discountNote)}</dd>
                <dt>登録者</dt><dd>${escapeHtml(button.dataset.createdBy)}</dd>
                <dt>更新者</dt><dd>${escapeHtml(button.dataset.updatedBy)}</dd>
                <dt>登録日時</dt><dd>${escapeHtml(button.dataset.createdAt)}</dd>
                <dt>更新日時</dt><dd>${escapeHtml(button.dataset.updatedAt)}</dd>
            </dl>
        `;

        modal.classList.add('is-open');
    });

    closeButton.addEventListener('click', function () {
        modal.classList.remove('is-open');
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.classList.remove('is-open');
        }
    });
}

function setupInlineEdit() {
    document.addEventListener('click', function (event) {
        const editButton = event.target.closest('.tuition-edit-button');

        if (!editButton) {
            return;
        }

        const row = editButton.closest('tr');
        if (!row) return;

        row.querySelectorAll('[data-edit-field]').forEach(function (cell) {
            const field = cell.dataset.editField;
            const value = cell.dataset.value || '';

            if (field === 'payment_method_id') {
                const options = (window.tuitionPaymentMethods || [])
                    .map((method) => `<option value="${method.id}" ${String(method.id) === String(value) ? 'selected' : ''}>${escapeHtml(method.name)}</option>`)
                    .join('');

                cell.innerHTML = `<select class="tuition-edit-input" data-field="${field}"><option value="">未設定</option>${options}</select>`;
                return;
            }

            if (field === 'payment_status') {
                cell.innerHTML = `
                    <select class="tuition-edit-input" data-field="${field}">
                        <option value="unpaid" ${value === 'unpaid' ? 'selected' : ''}>未入金</option>
                        <option value="paid" ${value === 'paid' ? 'selected' : ''}>入金済</option>
                        <option value="cancelled" ${value === 'cancelled' ? 'selected' : ''}>取消</option>
                    </select>
                `;
                return;
            }

            if (field === 'scheduled_date' || field === 'transaction_date') {
                cell.innerHTML = `<input type="date" class="tuition-edit-input" data-field="${field}" value="${escapeHtml(value)}">`;
                return;
            }

            cell.innerHTML = `<input type="text" class="tuition-edit-input" data-field="${field}" value="${escapeHtml(value === '-' ? '' : value)}">`;
        });

        row.querySelector('.tuition-edit-button').style.display = 'none';
        row.querySelector('.tuition-save-button').style.display = '';
        row.querySelector('.tuition-cancel-button').style.display = '';
    });

    document.addEventListener('click', function (event) {
        const cancelButton = event.target.closest('.tuition-cancel-button');

        if (!cancelButton) {
            return;
        }

        window.location.reload();
    });

    document.addEventListener('click', function (event) {
        const saveButton = event.target.closest('.tuition-save-button');

        if (!saveButton) {
            return;
        }

        const row = saveButton.closest('tr');
        const id = row?.dataset.rowId;
        const base = document.querySelector('.shop-sales-page')?.dataset.updateBase;

        if (!row || !id || !base) {
            return;
        }

        const payload = {};

        row.querySelectorAll('.tuition-edit-input').forEach(function (input) {
            payload[input.dataset.field] = input.value;
        });

        fetch(`${base}/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(payload),
        })
            .then(async (response) => {
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || '更新に失敗しました。');
                }

                return response.json();
            })
            .then(() => {
                window.location.reload();
            })
            .catch((error) => {
                alert(error.message);
            });
    });
}

function setupShopSalesCharts() {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js is not loaded.');
        return;
    }

    const chartData = window.shopSalesChartData || {};
    const labels = chartData.labels || [];
    const salesAmounts = chartData.salesAmounts || [];
    const salesQuantities = chartData.salesQuantities || [];

    const amountCanvas = document.getElementById('shopSalesAmountChart');
    const quantityCanvas = document.getElementById('shopSalesQuantityChart');

    if (amountCanvas) {
        new Chart(amountCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: '売上額（税込）',
                    type: 'line',
                    data: salesAmounts,
                    borderColor: '#3b82f6',
                    backgroundColor: 'transparent',
                    pointBackgroundColor: '#3b82f6',
                    pointRadius: 4,
                    borderWidth: 3,
                    fill: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'line'
                        }
                    }
                }
            }
        });
    }

    if (quantityCanvas) {
        new Chart(quantityCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '販売数（点）',
                    data: salesQuantities,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
            },
        });
    }
}



function setupShopChartToggle() {
    const button = document.getElementById('shopChartToggle');
    const body = document.getElementById('shopChartBody');

    if (!button || !body) {
        return;
    }

    button.addEventListener('click', function () {
        body.classList.toggle('is-hidden');
        button.textContent = body.classList.contains('is-hidden') ? '+' : '−';
    });
}


console.log('shop-sales.js loaded');
console.log('chart data', window.shopSalesChartData);
console.log('Chart exists', typeof Chart);

setupStudentLookup();
setupProductLookups();
setupAddItemButton();
refreshShopItemIndexes();
updateShopSaleTotals();

setupColumnSetting();
setupDetailModal();
setupInlineEdit();

if (typeof setupFloatingTooltip === 'function') {
    setupFloatingTooltip();
}

setupShopSalesCharts();
setupShopChartToggle();