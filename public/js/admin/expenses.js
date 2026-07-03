(function(){
    const yen = (value) => '¥' + Number(value || 0).toLocaleString();
    const escapeHtml = (value) => String(value ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    const page = document.querySelector('.expense-page');
    if(!page) return;

    const optionData = JSON.parse(document.getElementById('expenseInlineOptions')?.textContent || '{}');
    const labelMap = (items) => Object.fromEntries((items || []).map(item => [String(item.value), item.label]));
    const schoolLabels = labelMap(optionData.schools);
    const categoryLabels = labelMap(optionData.categories);
    const paymentMethodLabels = labelMap(optionData.paymentMethods);
    const statusLabels = labelMap(optionData.statuses);

    function bindCreateTotal(){
        const form = document.getElementById('expenseCreateForm');
        if(!form) return;
        const amount = form.querySelector('[name="amount"]');
        const tax = form.querySelector('[name="tax_amount"]');
        const status = form.querySelector('[name="payment_status"]');
        const totalOut = document.getElementById('expenseCreateTotalAmount');
        const amountOut = document.getElementById('expenseCreateAmountText');
        const taxOut = document.getElementById('expenseCreateTaxText');
        const statusOut = document.getElementById('expenseCreateStatusText');
        const update = () => {
            const a = Number(amount?.value || 0);
            const t = Number(tax?.value || 0);
            if(totalOut) totalOut.textContent = yen(a + t);
            if(amountOut) amountOut.textContent = yen(a);
            if(taxOut) taxOut.textContent = yen(t);
            if(statusOut) statusOut.textContent = statusLabels[String(status?.value || 'unpaid')] || '未払い';
        };
        amount?.addEventListener('input', update);
        tax?.addEventListener('input', update);
        status?.addEventListener('change', update);
        update();
    }
    bindCreateTotal();

    const detailModal = document.getElementById('expenseDetailModal');
    const detailList = document.getElementById('expenseDetailList');
    function openModal(modal){ modal?.classList.add('is-open'); modal?.setAttribute('aria-hidden','false'); }
    function closeModal(modal){ modal?.classList.remove('is-open'); modal?.setAttribute('aria-hidden','true'); }
    document.querySelectorAll('[data-expense-modal-close]').forEach(el => el.addEventListener('click', () => closeModal(detailModal)));

    document.querySelectorAll('.expense-detail-button').forEach(button => {
        button.addEventListener('click', () => {
            const d = button.dataset;
            const rows = [
                ['ID', d.id], ['経費番号', d.expenseCode], ['教室', d.schoolName], ['経費分類', d.expenseCategoryLabel],
                ['経費名', d.expenseTitle], ['支払先', d.vendorName || '-'], ['支払予定日', d.scheduledDateText], ['支払日', d.paidAtText],
                ['支払方法', d.paymentMethodName], ['税抜金額', yen(d.amount)], ['消費税', yen(d.taxAmount)], ['税込金額', yen(d.totalAmount)],
                ['支払状況', d.paymentStatusLabel], ['経費メモ', d.memo || '-'], ['作成者', d.creatorName], ['作成日時', d.createdAt],
                ['更新者', d.updaterName], ['更新日時', d.updatedAt]
            ];
            detailList.innerHTML = rows.map(([k,v]) => `<dt>${escapeHtml(k)}</dt><dd>${escapeHtml(v)}</dd>`).join('');
            openModal(detailModal);
        });
    });

    const columnModal = document.getElementById('tuitionColumnModal');
    const columnButton = document.getElementById('expenseColumnButton') || document.getElementById('tuitionColumnButton') || document.getElementById('tuitionColumnSettingButton');
    const columnClose = document.getElementById('tuitionColumnClose');

    function openColumnModal(){
        if(!columnModal) return;
        columnModal.classList.add('is-open');
        columnModal.setAttribute('aria-hidden', 'false');
    }

    function closeColumnModal(){
        if(!columnModal) return;
        columnModal.classList.remove('is-open');
        columnModal.setAttribute('aria-hidden', 'true');
    }

    function toggleExpenseColumn(columnIndex, visible){
        const nth = Number(columnIndex) + 1;
        if(!Number.isFinite(nth) || nth <= 0) return;
        document.querySelectorAll(`#expenseTable tr > *:nth-child(${nth})`).forEach(cell => {
            cell.style.display = visible ? '' : 'none';
        });
    }

    columnButton?.addEventListener('click', openColumnModal);
    columnClose?.addEventListener('click', closeColumnModal);
    columnModal?.addEventListener('click', event => {
        if(event.target === columnModal) closeColumnModal();
    });
    columnModal?.querySelectorAll('[data-column]').forEach(check => {
        toggleExpenseColumn(check.dataset.column, check.checked);
        check.addEventListener('change', () => toggleExpenseColumn(check.dataset.column, check.checked));
    });

    const checkAll = document.getElementById('expenseCheckAll');
    checkAll?.addEventListener('change', () => {
        document.querySelectorAll('.expense-row-check').forEach(check => check.checked = checkAll.checked);
    });

    function createSelect(name, value, options){
        const select = document.createElement('select');
        select.name = name;
        select.dataset.field = name;
        select.className = 'expense-inline-select';
        (options || []).forEach(item => {
            const option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label;
            if(String(item.value) === String(value ?? '')) option.selected = true;
            select.appendChild(option);
        });
        return select;
    }
    function createInput(name, value, type='text'){
        const input = document.createElement('input');
        input.name = name;
        input.dataset.field = name;
        input.type = type;
        input.value = value ?? '';
        input.className = 'expense-inline-input';
        if(['expense_title','vendor_name'].includes(name)) input.classList.add('expense-inline-wide');
        if(name === 'memo') input.classList.add('expense-inline-memo');
        if(type === 'number'){ input.min = '0'; input.step = '1'; }
        return input;
    }
    function inputForField(field, value){
        if(field === 'school_id') return createSelect(field, value, optionData.schools);
        if(field === 'expense_category') return createSelect(field, value, optionData.categories);
        if(field === 'payment_method_id') return createSelect(field, value, optionData.paymentMethods);
        if(field === 'payment_status') return createSelect(field, value, optionData.statuses);
        if(['scheduled_date','paid_at'].includes(field)) return createInput(field, value, 'date');
        if(['amount','tax_amount'].includes(field)) return createInput(field, value, 'number');
        return createInput(field, value, 'text');
    }
    function setButtons(row, editing){
        const detail = row.querySelector('.expense-detail-button');
        const edit = row.querySelector('.expense-edit-button');
        const save = row.querySelector('.expense-save-button');
        const cancel = row.querySelector('.expense-cancel-button');
        if(detail) detail.style.display = editing ? 'none' : '';
        if(edit) edit.style.display = editing ? 'none' : '';
        if(save) save.style.display = editing ? '' : 'none';
        if(cancel) cancel.style.display = editing ? '' : 'none';
    }
    function enterEdit(row){
        if(!row) return;
        document.querySelectorAll('#expenseTable tbody tr.expense-editing').forEach(editingRow => { if(editingRow !== row) cancelEdit(editingRow); });
        if(row.classList.contains('expense-editing')) return;
        row.classList.add('expense-editing');
        row.querySelectorAll('[data-edit-field]').forEach(cell => {
            const field = cell.dataset.editField;
            const value = cell.dataset.value || '';
            cell.dataset.originalHtml = cell.innerHTML;
            cell.innerHTML = '';
            cell.appendChild(inputForField(field, value));
        });
        setButtons(row, true);
    }
    function cancelEdit(row){
        row.querySelectorAll('[data-edit-field]').forEach(cell => {
            cell.innerHTML = cell.dataset.originalHtml || cell.innerHTML;
            delete cell.dataset.originalHtml;
        });
        row.classList.remove('expense-editing');
        setButtons(row, false);
    }
    async function saveRow(row){
        const id = row.dataset.rowId;
        if(!id) return;
        const form = new FormData();
        form.append('_method', 'PUT');
        form.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value || '');
        row.querySelectorAll('[data-edit-field]').forEach(cell => {
            const input = cell.querySelector('input,select');
            if(input) form.append(cell.dataset.editField, input.value);
        });
        try{
            const res = await fetch(`${page.dataset.updateBase}/${id}`, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, body:form});
            const json = await res.json().catch(() => ({}));
            if(!res.ok){ alert(json.message || '更新に失敗しました。'); return; }
            location.reload();
        }catch(e){
            alert('通信に失敗しました。');
        }
    }
    document.querySelectorAll('.expense-edit-button').forEach(button => button.addEventListener('click', () => enterEdit(button.closest('tr'))));
    document.querySelectorAll('.expense-cancel-button').forEach(button => button.addEventListener('click', () => cancelEdit(button.closest('tr'))));
    document.querySelectorAll('.expense-save-button').forEach(button => button.addEventListener('click', () => saveRow(button.closest('tr'))));

    function initExpenseColumnFilters(){
        const active = {};
        const map = {school:'filterSchool', category:'filterCategory', method:'filterMethod', status:'filterStatus'};
        document.querySelectorAll('.refund-column-filter-button').forEach(button => {
            button.addEventListener('click', event => {
                event.stopPropagation();
                document.querySelector('.refund-column-filter-menu')?.remove();
                const key = button.dataset.filterCol;
                const dataKey = map[key];
                if(!dataKey) return;
                const dataName = dataKey.replace(/[A-Z]/g, m => '-' + m.toLowerCase());
                const values = [...new Set([...document.querySelectorAll('#expenseTable tbody tr')].map(row => row.querySelector(`[data-${dataName}]`)?.dataset[dataKey]).filter(Boolean))].sort();
                active[key] = active[key] || new Set(values);
                const rect = button.getBoundingClientRect();
                const menu = document.createElement('div');
                menu.className = 'refund-column-filter-menu';
                menu.style.left = `${Math.min(rect.left, window.innerWidth - 280)}px`;
                menu.style.top = `${rect.bottom + window.scrollY + 6}px`;
                menu.innerHTML = `<input type="text" placeholder="検索..."><div class="refund-column-filter-actions"><button type="button" data-action="all">すべて選択</button><button type="button" data-action="clear">クリア</button></div><div class="refund-column-filter-values">${values.map(v => `<label><input type="checkbox" value="${escapeHtml(v)}" ${active[key].has(v) ? 'checked' : ''}>${escapeHtml(v)}</label>`).join('')}</div><button type="button" class="refund-column-filter-apply">適用</button>`;
                document.body.appendChild(menu);
                const search = menu.querySelector('input[type=text]');
                search.addEventListener('input', () => menu.querySelectorAll('.refund-column-filter-values label').forEach(label => label.style.display = label.textContent.includes(search.value) ? '' : 'none'));
                menu.querySelector('[data-action=all]').addEventListener('click', () => menu.querySelectorAll('.refund-column-filter-values input').forEach(input => input.checked = true));
                menu.querySelector('[data-action=clear]').addEventListener('click', () => menu.querySelectorAll('.refund-column-filter-values input').forEach(input => input.checked = false));
                menu.querySelector('.refund-column-filter-apply').addEventListener('click', () => {
                    active[key] = new Set([...menu.querySelectorAll('.refund-column-filter-values input:checked')].map(input => input.value));
                    applyExpenseColumnFilters(active, map);
                    menu.remove();
                });
            });
        });
        document.addEventListener('click', event => {
            if(!event.target.closest('.refund-column-filter-menu') && !event.target.closest('.refund-column-filter-button')) document.querySelector('.refund-column-filter-menu')?.remove();
        });
    }

    function applyExpenseColumnFilters(active, map){
        document.querySelectorAll('#expenseTable tbody tr').forEach(row => {
            let show = true;
            Object.entries(active).forEach(([key, set]) => {
                const dataKey = map[key];
                const dataName = dataKey.replace(/[A-Z]/g, m => '-' + m.toLowerCase());
                const cell = row.querySelector(`[data-${dataName}]`);
                const value = cell?.dataset[dataKey] || '';
                if(set.size && !set.has(value)) show = false;
            });
            row.style.display = show ? '' : 'none';
        });
    }
    initExpenseColumnFilters();

    function drawChart(canvasId, emptyId, type, label, integerTicks){
        if(typeof Chart === 'undefined') return;
        const canvas = document.getElementById(canvasId);
        if(!canvas) return;
        const data = JSON.parse(canvas.dataset.chart || '{"labels":[],"values":[]}');
        const empty = document.getElementById(emptyId);
        if(!data.labels?.length){ empty?.classList.add('is-visible'); return; }

        const isAmountLine = canvasId === 'expenseAmountChart';
        const dataset = {
            label,
            data:data.values,
            tension:.25,
            fill:false
        };
        if(isAmountLine){
            dataset.borderColor = '#3b82f6';
            dataset.backgroundColor = '#3b82f6';
            dataset.pointBackgroundColor = '#3b82f6';
            dataset.pointBorderColor = '#3b82f6';
            dataset.borderWidth = 2;
        }

        const legendLabels = isAmountLine
            ? {usePointStyle:true, pointStyle:'line', boxWidth:90, boxHeight:4, padding:18}
            : {usePointStyle:false};

        new Chart(canvas, {
            type,
            data:{labels:data.labels, datasets:[dataset]},
            options:{
                responsive:true,
                maintainAspectRatio:false,
                scales:{
                    y:{
                        beginAtZero:true,
                        ticks:{
                            precision: integerTicks ? 0 : undefined,
                            callback:(v)=> integerTicks ? Number(v).toLocaleString() : yen(v)
                        }
                    }
                },
                plugins:{legend:{display:true, labels:legendLabels}}
            }
        });
    }
    drawChart('expenseAmountChart', 'expenseAmountEmpty', 'line', '経費額', false);
    drawChart('expenseCountChart', 'expenseCountEmpty', 'bar', '件数', true);

    const chartToggle = document.getElementById('expenseChartToggle');
    const chartBody = document.getElementById('expenseChartBody');
    chartToggle?.addEventListener('click', () => {
        if(!chartBody) return;
        const hidden = chartBody.style.display === 'none';
        chartBody.style.display = hidden ? '' : 'none';
        chartToggle.textContent = hidden ? '−' : '+';
    });
})();
