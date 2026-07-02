document.addEventListener('DOMContentLoaded', () => {
    const yen = value => '¥' + Number(value || 0).toLocaleString();
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[s]));
    const page = document.querySelector('.point-cost-page');
    const studentSearchUrl = page?.dataset.studentSearchUrl || '/admin/operations/classroom-accounting/point-product-costs/students/search';
    const studentInput = document.getElementById('pointCostStudentLookupInput');
    const studentId = document.getElementById('pointCostStudentId');
    const studentResults = document.getElementById('pointCostStudentResults');
    const rewardItem = document.getElementById('pointCostRewardItem');
    const rewardItemInput = document.getElementById('pointCostRewardItemLookupInput');
    const rewardItemResults = document.getElementById('pointCostRewardItemResults');
    const statusInput = document.getElementById('pointCostStatus');
    const deliveredAt = document.getElementById('pointCostDeliveredAt');
    const categoryText = document.getElementById('pointCostCategoryText');
    const pointsText = document.getElementById('pointCostPointsText');
    const amountText = document.getElementById('pointCostAmountText');
    const stockText = document.getElementById('pointCostStockText');
    const currentPointsText = document.getElementById('pointCostCurrentPointsText');
    const totalEarnedText = document.getElementById('pointCostTotalEarnedText');
    const submitButton = document.getElementById('pointCostSubmitButton');
    let selectedStudent = null;

    function showToast(message) {
        if (!message) return;
        const toast = document.createElement('div');
        toast.className = 'refund-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => toast.remove(), 250);
        }, 3000);
    }

    const sessionToast = document.getElementById('pointCostToastMessage')?.dataset.message;
    if (sessionToast) showToast(sessionToast);

    function selectedRewardItem() {
        return (window.pointCostRewardItems || []).find(item => String(item.id) === String(rewardItem?.value || '')) || null;
    }

    function syncCreateForm() {
        const selected = selectedRewardItem();
        const points = Number(selected?.required_points || 0);
        const cost = Number(selected?.cost_price || 0);
        const stock = Number(selected?.stock_quantity || 0);
        if (categoryText) categoryText.textContent = selected?.category || '未選択';
        if (pointsText) pointsText.textContent = points.toLocaleString() + 'pt';
        if (amountText) amountText.textContent = yen(cost);
        if (stockText) stockText.textContent = stock.toLocaleString() + '個';
        if (currentPointsText) currentPointsText.textContent = selectedStudent ? Number(selectedStudent.current_points || 0).toLocaleString() + 'pt' : '未選択';
        if (totalEarnedText) totalEarnedText.textContent = selectedStudent ? Number(selectedStudent.total_earned_points || 0).toLocaleString() + 'pt' : '未選択';
        if (deliveredAt) deliveredAt.disabled = statusInput?.value !== 'delivered';
        if (statusInput?.value !== 'delivered' && deliveredAt) deliveredAt.value = '';
        if (submitButton) submitButton.disabled = !studentId?.value || !rewardItem?.value;
    }

    let studentTimer = null;
    studentInput?.addEventListener('input', () => {
        clearTimeout(studentTimer);
        const q = studentInput.value.trim();
        studentId.value = '';
        selectedStudent = null;
        syncCreateForm();
        if (q.length < 1) { studentResults.innerHTML = ''; return; }
        studentTimer = setTimeout(async () => {
            const res = await fetch(`${studentSearchUrl}?q=${encodeURIComponent(q)}`, {headers:{'Accept':'application/json'}});
            const rows = await res.json();
            studentResults.innerHTML = rows.map(row => `<button type="button" data-id="${row.id}" data-label="${esc(row.label)}" data-current-points="${Number(row.current_points || 0)}" data-total-earned-points="${Number(row.total_earned_points || 0)}" data-total-used-points="${Number(row.total_used_points || 0)}">${esc(row.label)} <span>${esc(row.school_name || '')} / 現在${Number(row.current_points || 0).toLocaleString()}pt</span></button>`).join('');
        }, 250);
    });

    studentResults?.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        studentId.value = button.dataset.id;
        studentInput.value = button.dataset.label;
        selectedStudent = {
            current_points: button.dataset.currentPoints || 0,
            total_earned_points: button.dataset.totalEarnedPoints || 0,
            total_used_points: button.dataset.totalUsedPoints || 0,
        };
        studentResults.innerHTML = '';
        syncCreateForm();
    });

    function renderRewardItemResults(keyword) {
        const q = String(keyword || '').trim().toLowerCase();
        const rows = (window.pointCostRewardItems || [])
            .filter(item => !q || String(item.name || '').toLowerCase().includes(q) || String(item.category || '').toLowerCase().includes(q))
            .slice(0, 20);
        if (!rewardItemResults) return;
        rewardItemResults.innerHTML = rows.map(item => `<button type="button" data-id="${item.id}" data-label="${esc(item.name)}"><strong>${esc(item.name)}</strong><span>${esc(item.category)} / ${Number(item.required_points || 0).toLocaleString()}pt / ${yen(item.cost_price)} / 在庫${Number(item.stock_quantity || 0).toLocaleString()}個</span></button>`).join('');
        rewardItemResults.style.display = rows.length ? 'block' : 'none';
    }

    rewardItemInput?.addEventListener('input', () => {
        rewardItem.value = '';
        syncCreateForm();
        renderRewardItemResults(rewardItemInput.value);
    });
    rewardItemInput?.addEventListener('focus', () => renderRewardItemResults(rewardItemInput.value));
    rewardItemResults?.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        rewardItem.value = button.dataset.id;
        rewardItemInput.value = button.dataset.label;
        rewardItemResults.innerHTML = '';
        rewardItemResults.style.display = 'none';
        syncCreateForm();
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.point-cost-item-field')) {
            if (rewardItemResults) rewardItemResults.style.display = 'none';
        }
    });

    statusInput?.addEventListener('change', syncCreateForm);
    syncCreateForm();

    const detailModal = document.getElementById('tuitionDetailModal');
    const detailBody = document.getElementById('tuitionDetailBody');
    document.querySelectorAll('.tuition-detail-button').forEach(button => {
        button.addEventListener('click', () => {
            const data = button.dataset;
            const rows = [
                ['ID', data.id], ['教室', data.school], ['生徒', data.student], ['生徒コード', data.studentCode],
                ['カテゴリ', data.category], ['商品名', data.item], ['使用ポイント', `${data.points}pt`], ['商品原価', `¥${data.cost}`],
                ['状態', data.status], ['申請日', data.requestedAt], ['承認日', data.approvedAt], ['受け渡し日', data.deliveredAt],
                ['却下日', data.rejectedAt], ['会計反映', data.accountSync], ['メモ', data.note],
                ['作成日時', data.createdAt], ['更新日時', data.updatedAt], ['会計取引ID', data.accountTransactionId],
                ['会計作成日時', data.accountCreatedAt], ['会計更新日時', data.accountUpdatedAt],
                ['source_table', data.sourceTable], ['source_id', data.sourceId],
            ];
            detailBody.innerHTML = `<dl class="tuition-detail-grid event-detail-grid refund-detail-grid">${rows.map(([label, value]) => `<dt>${esc(label)}</dt><dd>${esc(value || '-')}</dd>`).join('')}</dl>`;
            detailModal.classList.add('is-open');
        });
    });
    document.getElementById('tuitionDetailClose')?.addEventListener('click', () => detailModal.classList.remove('is-open'));
    detailModal?.addEventListener('click', event => { if (event.target === detailModal) detailModal.classList.remove('is-open'); });

    const columnModal = document.getElementById('tuitionColumnModal');
    document.getElementById('tuitionColumnButton')?.addEventListener('click', () => columnModal.classList.add('is-open'));
    document.getElementById('tuitionColumnClose')?.addEventListener('click', () => columnModal.classList.remove('is-open'));
    columnModal?.querySelectorAll('[data-column]').forEach(check => {
        check.addEventListener('change', () => {
            const index = Number(check.dataset.column) + 1;
            document.querySelectorAll(`#pointCostTable tr > *:nth-child(${index})`).forEach(cell => cell.style.display = check.checked ? '' : 'none');
        });
    });

    document.getElementById('tuitionCheckAll')?.addEventListener('change', event => {
        document.querySelectorAll('.tuition-row-check').forEach(check => check.checked = event.target.checked);
    });

    document.querySelectorAll('.tuition-edit-button').forEach(button => button.addEventListener('click', () => startEdit(button.closest('tr'))));
    document.querySelectorAll('.tuition-cancel-button').forEach(button => button.addEventListener('click', () => cancelEdit(button.closest('tr'))));
    document.querySelectorAll('.tuition-save-button').forEach(button => button.addEventListener('click', () => saveEdit(button.closest('tr'))));

    function createEditInput(field, value) {
        if (field === 'status') return `<select class="tuition-edit-input" data-field="status">${(window.pointCostStatuses || []).map(s => `<option value="${s.value}" ${s.value === value ? 'selected' : ''}>${esc(s.label)}</option>`).join('')}</select>`;
        if (field === 'requested_at' || field === 'delivered_at') return `<input type="date" class="tuition-edit-input" data-field="${field}" value="${esc(value || '')}">`;
        return `<input type="text" class="tuition-edit-input" data-field="${field}" value="${esc(value || '')}">`;
    }

    function startEdit(row) {
        if (!row) return;
        document.querySelectorAll('#pointCostTable tbody tr.is-editing').forEach(editingRow => { if (editingRow !== row) cancelEdit(editingRow); });
        row.classList.add('is-editing');
        row.querySelectorAll('[data-edit-field]').forEach(cell => {
            const field = cell.dataset.editField;
            const value = cell.dataset.value || '';
            cell.dataset.originalHtml = cell.innerHTML;
            cell.innerHTML = createEditInput(field, value);
        });
        row.querySelector('.tuition-edit-button').style.display = 'none';
        row.querySelector('.tuition-detail-button').style.display = 'none';
        row.querySelector('.tuition-save-button').style.display = '';
        row.querySelector('.tuition-cancel-button').style.display = '';
    }

    function cancelEdit(row) {
        if (!row) return;
        row.querySelectorAll('[data-edit-field]').forEach(cell => { if (cell.dataset.originalHtml) cell.innerHTML = cell.dataset.originalHtml; });
        row.classList.remove('is-editing');
        row.querySelector('.tuition-edit-button')?.style.removeProperty('display');
        row.querySelector('.tuition-detail-button')?.style.removeProperty('display');
        const saveButton = row.querySelector('.tuition-save-button');
        const cancelButton = row.querySelector('.tuition-cancel-button');
        if (saveButton) saveButton.style.display = 'none';
        if (cancelButton) cancelButton.style.display = 'none';
    }

    async function saveEdit(row) {
        if (!row) return;
        const payload = {};
        row.querySelectorAll('.tuition-edit-input').forEach(input => payload[input.dataset.field] = input.value);
        payload._token = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value || '';
        payload._method = 'PUT';
        const res = await fetch(`${document.querySelector('.point-cost-page').dataset.updateBase}/${row.dataset.rowId}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':payload._token,'Accept':'application/json'},
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            alert(data.message || '更新に失敗しました。');
            return;
        }
        sessionStorage.setItem('pointCostUpdatedRowId', row.dataset.rowId);
        window.location.reload();
    }

    const updatedRowId = sessionStorage.getItem('pointCostUpdatedRowId');
    if (updatedRowId) {
        sessionStorage.removeItem('pointCostUpdatedRowId');
        const row = document.querySelector(`#pointCostTable tbody tr[data-row-id="${updatedRowId}"]`);
        if (row) {
            row.classList.add('is-saved-highlight');
            setTimeout(() => row.classList.remove('is-saved-highlight'), 1200);
        }
    }

    function initColumnFilters() {
        const active = {};
        const map = {school:'filterSchool',student:'filterStudent',category:'filterCategory',status:'filterStatus',account:'filterAccount'};
        document.querySelectorAll('.refund-column-filter-button').forEach(button => {
            button.addEventListener('click', event => {
                event.stopPropagation();
                document.querySelector('.refund-column-filter-menu')?.remove();
                const key = button.dataset.filterCol;
                const dataKey = map[key];
                const values = [...new Set([...document.querySelectorAll('#pointCostTable tbody tr')].map(row => row.querySelector(`[data-${dataKey.replace(/[A-Z]/g, m => '-' + m.toLowerCase())}]`)?.dataset[dataKey]).filter(Boolean))].sort();
                active[key] = active[key] || new Set(values);
                const rect = button.getBoundingClientRect();
                const menu = document.createElement('div');
                menu.className = 'refund-column-filter-menu';
                menu.style.left = `${Math.min(rect.left, window.innerWidth - 280)}px`;
                menu.style.top = `${rect.bottom + window.scrollY + 6}px`;
                menu.innerHTML = `<input type="text" placeholder="検索..."><div class="refund-column-filter-actions"><button type="button" data-action="all">すべて選択</button><button type="button" data-action="clear">クリア</button></div><div class="refund-column-filter-values">${values.map(v => `<label><input type="checkbox" value="${esc(v)}" ${active[key].has(v) ? 'checked' : ''}>${esc(v)}</label>`).join('')}</div><button type="button" class="refund-column-filter-apply">適用</button>`;
                document.body.appendChild(menu);
                const search = menu.querySelector('input[type=text]');
                search.addEventListener('input', () => menu.querySelectorAll('.refund-column-filter-values label').forEach(label => label.style.display = label.textContent.includes(search.value) ? '' : 'none'));
                menu.querySelector('[data-action=all]').addEventListener('click', () => menu.querySelectorAll('.refund-column-filter-values input').forEach(i => i.checked = true));
                menu.querySelector('[data-action=clear]').addEventListener('click', () => menu.querySelectorAll('.refund-column-filter-values input').forEach(i => i.checked = false));
                menu.querySelector('.refund-column-filter-apply').addEventListener('click', () => {
                    active[key] = new Set([...menu.querySelectorAll('.refund-column-filter-values input:checked')].map(i => i.value));
                    applyTableFilters(active, map);
                    menu.remove();
                });
            });
        });
        document.addEventListener('click', e => { if (!e.target.closest('.refund-column-filter-menu') && !e.target.closest('.refund-column-filter-button')) document.querySelector('.refund-column-filter-menu')?.remove(); });
    }

    function applyTableFilters(active, map) {
        document.querySelectorAll('#pointCostTable tbody tr').forEach(row => {
            let show = true;
            Object.entries(active).forEach(([key, set]) => {
                const dataName = map[key].replace(/[A-Z]/g, m => '-' + m.toLowerCase());
                const cell = row.querySelector(`[data-${dataName}]`);
                const value = cell?.dataset[map[key]] || '';
                if (set.size && !set.has(value)) show = false;
            });
            row.style.display = show ? '' : 'none';
        });
    }
    initColumnFilters();

    function renderCharts() {
        if (typeof Chart === 'undefined') return;
        const amountData = window.pointCostAmountChartData || [];
        const countData = window.pointCostCountChartData || [];
        const categoryData = window.pointCostCategoryChartData || [];
        const yenTick = value => '¥' + Number(value || 0).toLocaleString();
        const amountCanvas = document.getElementById('pointCostAmountChart');
        document.getElementById('pointCostAmountEmpty')?.classList.toggle('is-visible', amountData.every(r => Number(r.amount || 0) === 0));
        if (amountCanvas) new Chart(amountCanvas, {type:'line',data:{labels:amountData.map(r=>r.month),datasets:[{label:'費用額',data:amountData.map(r=>r.amount),borderColor:'#3b82f6',backgroundColor:'transparent',pointBackgroundColor:'#3b82f6',pointBorderColor:'#3b82f6',tension:.25,fill:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{usePointStyle:true,pointStyle:'line'}}},scales:{y:{ticks:{callback:yenTick}}}}});
        const countCanvas = document.getElementById('pointCostCountChart');
        document.getElementById('pointCostCountEmpty')?.classList.toggle('is-visible', countData.every(r => Number(r.count || 0) === 0));
        if (countCanvas) new Chart(countCanvas, {type:'bar',data:{labels:countData.map(r=>r.month),datasets:[{label:'件数',data:countData.map(r=>r.count)}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
        const categoryCanvas = document.getElementById('pointCostCategoryChart');
        document.getElementById('pointCostCategoryEmpty')?.classList.toggle('is-visible', categoryData.length === 0 || categoryData.every(r => Number(r.amount || 0) === 0));
        const totalCategoryAmount = categoryData.reduce((sum, row) => sum + Number(row.amount || 0), 0);
        const legend = document.getElementById('pointCostCategoryLegend');
        if (legend) {
            legend.innerHTML = categoryData.length ? categoryData.map(row => {
                const value = Number(row.amount || 0);
                const percent = totalCategoryAmount > 0 ? Math.round(value / totalCategoryAmount * 100) : 0;
                return `<div class="point-cost-category-legend-row"><span>${esc(row.label)}</span><strong>${yen(value)}</strong><em>${percent}%</em></div>`;
            }).join('') : '<p>内訳データがありません。</p>';
        }
        if (categoryCanvas) new Chart(categoryCanvas, {type:'doughnut',data:{labels:categoryData.map(r=>r.label),datasets:[{data:categoryData.map(r=>r.amount)}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:(ctx)=>{const value=Number(ctx.raw||0);const percent=totalCategoryAmount>0?Math.round(value/totalCategoryAmount*100):0;return `${ctx.label}: ${yen(value)}（${percent}%）`;}}}}}});
    }
    renderCharts();

    document.getElementById('eventChartToggle')?.addEventListener('click', event => {
        const body = document.getElementById('eventChartBody');
        const hidden = body.style.display === 'none';
        body.style.display = hidden ? '' : 'none';
        event.target.textContent = hidden ? '−' : '+';
    });
});
