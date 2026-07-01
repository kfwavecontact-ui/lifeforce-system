document.addEventListener('DOMContentLoaded', () => {
    const yen = value => '¥' + Number(value || 0).toLocaleString();
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[s]));
    const page = document.querySelector('.refund-page');
    const targetSearchUrl = page?.dataset.targetSearchUrl || '/admin/operations/classroom-accounting/refunds/targets/search';
    const sourceType = document.getElementById('refundSourceType');
    const sourceId = document.getElementById('refundSourceId');
    const targetInput = document.getElementById('refundTargetLookupInput');
    const targetButton = document.getElementById('refundTargetSearchButton');
    const targetClearButton = document.getElementById('refundTargetClearButton');
    const targetResults = document.getElementById('refundTargetResults');
    const lookupModal = document.getElementById('refundLookupModal');
    const lookupClose = document.getElementById('refundLookupClose');
    const lookupCount = document.getElementById('refundLookupCount');
    const lookupBody = document.getElementById('refundLookupTableBody');
    const studentField = document.getElementById('refundStudentField');
    const studentInput = document.getElementById('refundStudentLookupInput');
    const studentId = document.getElementById('refundStudentId');
    const studentResults = document.getElementById('refundStudentResults');
    const amountInput = document.getElementById('refundAmount');
    const amountHelp = document.getElementById('refundAmountHelp');
    const maxText = document.getElementById('refundMaxAmountText');
    const amountText = document.getElementById('refundAmountText');
    const submitButton = document.getElementById('refundSubmitButton');
    const statusInput = document.getElementById('refundStatus');
    const reasonInput = document.getElementById('refundReason');
    const reasonHelp = document.getElementById('refundReasonHelp');
    let currentTarget = null;

    function isOther() { return sourceType?.value === 'other'; }
    function maxAmount() { return isOther() ? 9999999 : Number(currentTarget?.max_amount || 0); }

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

    const sessionToast = document.getElementById('refundToastMessage')?.dataset.message;
    if (sessionToast) showToast(sessionToast);

    function syncAmount() {
        const max = maxAmount();
        const value = Number(amountInput?.value || 0);
        if (maxText) maxText.textContent = max && max < 9999999 ? yen(max) : '任意';
        if (amountText) amountText.textContent = yen(value);

        let valid = value > 0;
        if (!isOther() && (!sourceId?.value || value > max)) valid = false;
        if (isOther() && !studentId?.value) valid = false;
        if (statusInput?.value !== 'cancelled' && !String(reasonInput?.value || '').trim()) valid = false;

        if (amountHelp) {
            const over = value > max && !isOther();
            amountHelp.textContent = over ? '返金可能額を超えています。' : '返金可能額以内で入力してください。';
            amountHelp.classList.toggle('refund-amount-error', over);
        }
        if (reasonHelp) {
            reasonHelp.classList.toggle('refund-amount-error', statusInput?.value !== 'cancelled' && !String(reasonInput?.value || '').trim());
        }
        if (submitButton) submitButton.disabled = !valid;
    }

    function clearTarget() {
        currentTarget = null;
        if (sourceId) sourceId.value = '';
        if (targetInput) targetInput.value = '';
        if (targetResults) targetResults.innerHTML = '';
        if (amountInput && !isOther()) amountInput.value = '';
        syncAmount();
    }

    function resetTarget() {
        clearTarget();
        if (targetInput) {
            targetInput.disabled = isOther();
            targetInput.placeholder = isOther() ? 'その他は返金対象の検索不要' : 'レコードID・生徒ID・生徒名で検索';
        }
        if (targetButton) targetButton.disabled = isOther();
        if (targetClearButton) targetClearButton.disabled = isOther();
        if (studentField) studentField.style.display = isOther() ? '' : 'none';
        if (!isOther() && studentId) studentId.value = '';
        if (!isOther() && studentInput) studentInput.value = '';
        if (studentResults) studentResults.innerHTML = '';
        syncAmount();
    }

    function selectTarget(row) {
        currentTarget = row;
        sourceId.value = row.id || '';
        studentId.value = row.student_id || '';
        targetInput.value = row.label || `${row.code || ''}｜返金可能 ${yen(row.max_amount)}`;
        if (amountInput && !amountInput.value) amountInput.value = row.max_amount || '';
        lookupModal?.classList.remove('is-open');
        syncAmount();
    }

    function renderLookupRows(rows) {
        if (!lookupBody || !lookupModal) return;
        lookupCount.textContent = rows.length ? `${rows.length}件見つかりました。クリックして返金対象を選択してください。` : '該当する返金対象はありません。';
        lookupBody.innerHTML = rows.length ? rows.map(row => `
            <tr class="refund-lookup-select-row" data-target='${esc(JSON.stringify(row))}'>
                <td title="${esc(row.code)}">${esc(row.code)}</td>
                <td>${esc(row.student_label || '-')}</td>
                <td>${yen(row.original_amount)}</td>
                <td>${esc(row.transaction_date || '-')}</td>
                <td><strong>${yen(row.max_amount)}</strong></td>
                <td><button type="button" class="tuition-search-button refund-lookup-select-button">選択</button></td>
            </tr>
        `).join('') : '<tr><td colspan="6" class="refund-lookup-empty">該当する返金対象はありません。</td></tr>';
        lookupModal.classList.add('is-open');
    }

    async function searchTargets() {
        if (!sourceType || isOther()) return;
        const q = targetInput?.value.trim() || '';
        targetButton.disabled = true;
        targetButton.textContent = '検索中';
        const url = `${targetSearchUrl}?type=${encodeURIComponent(sourceType.value)}&q=${encodeURIComponent(q)}`;
        try {
            const res = await fetch(url, {headers:{'Accept':'application/json'}});
            const rows = await res.json();
            renderLookupRows(rows);
        } finally {
            targetButton.disabled = false;
            targetButton.textContent = '検索';
        }
    }

    targetButton?.addEventListener('click', searchTargets);
    targetClearButton?.addEventListener('click', clearTarget);
    targetInput?.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); searchTargets(); } });
    lookupBody?.addEventListener('click', event => {
        const row = event.target.closest('.refund-lookup-select-row');
        if (!row) return;
        selectTarget(JSON.parse(row.dataset.target || '{}'));
    });
    lookupClose?.addEventListener('click', () => lookupModal?.classList.remove('is-open'));
    lookupModal?.addEventListener('click', event => { if (event.target === lookupModal) lookupModal.classList.remove('is-open'); });

    sourceType?.addEventListener('change', resetTarget);
    amountInput?.addEventListener('input', syncAmount);
    statusInput?.addEventListener('change', syncAmount);
    reasonInput?.addEventListener('input', syncAmount);
    resetTarget();

    let studentTimer = null;
    studentInput?.addEventListener('input', () => {
        clearTimeout(studentTimer);
        const q = studentInput.value.trim();
        studentId.value = '';
        syncAmount();
        if (q.length < 1) { studentResults.innerHTML = ''; return; }
        studentTimer = setTimeout(async () => {
            const res = await fetch(`/admin/operations/classroom-accounting/refunds/students/search?q=${encodeURIComponent(q)}`);
            const rows = await res.json();
            studentResults.innerHTML = rows.map(row => `<button type="button" data-id="${row.id}" data-label="${esc(row.label)}">${esc(row.label)}</button>`).join('');
        }, 250);
    });
    studentResults?.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        studentId.value = button.dataset.id;
        studentInput.value = button.dataset.label;
        studentResults.innerHTML = '';
        syncAmount();
    });

    const detailModal = document.getElementById('tuitionDetailModal');
    const detailBody = document.getElementById('tuitionDetailBody');
    document.querySelectorAll('.tuition-detail-button').forEach(button => {
        button.addEventListener('click', () => {
            const data = button.dataset;
            const rows = [
                ['ID', data.id], ['返金番号', data.refundCode], ['教室', data.school], ['生徒', data.student], ['生徒コード', data.studentCode],
                ['返金元区分', data.sourceType], ['返金対象', data.target], ['返金予定日', data.scheduledDate], ['返金方法', data.refundMethod],
                ['返金日', data.refundedAt], ['返金額', `¥${data.amount}`], ['状態', data.status], ['返金理由', data.reason], ['メモ', data.memo],
                ['作成者', data.createdBy], ['更新者', data.updatedBy],
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
            document.querySelectorAll(`#refundTable tr > *:nth-child(${index})`).forEach(cell => cell.style.display = check.checked ? '' : 'none');
        });
    });

    document.getElementById('tuitionCheckAll')?.addEventListener('change', event => {
        document.querySelectorAll('.tuition-row-check').forEach(check => check.checked = event.target.checked);
    });

    document.querySelectorAll('.tuition-edit-button').forEach(button => button.addEventListener('click', () => startEdit(button.closest('tr'))));
    document.querySelectorAll('.tuition-cancel-button').forEach(button => button.addEventListener('click', () => cancelEdit(button.closest('tr'))));
    document.querySelectorAll('.tuition-save-button').forEach(button => button.addEventListener('click', () => saveEdit(button.closest('tr'))));

    function createEditInput(field, value) {
        if (field === 'status') return `<select class="tuition-edit-input" data-field="status">${(window.refundStatuses || []).map(s => `<option value="${s.value}" ${s.value === value ? 'selected' : ''}>${esc(s.label)}</option>`).join('')}</select>`;
        if (field === 'refund_method_id') return `<select class="tuition-edit-input" data-field="refund_method_id"><option value="">未設定</option>${(window.refundPaymentMethods || []).map(m => `<option value="${m.id}" ${String(m.id) === String(value) ? 'selected' : ''}>${esc(m.name)}</option>`).join('')}</select>`;
        if (field === 'scheduled_date' || field === 'refunded_at') return `<input type="date" class="tuition-edit-input" data-field="${field}" value="${esc(value || '')}">`;
        if (field === 'refund_amount') return `<input type="number" min="1" class="tuition-edit-input" data-field="${field}" value="${esc(value || 0)}">`;
        return `<input type="text" class="tuition-edit-input" data-field="${field}" value="${esc(value || '')}">`;
    }

    function startEdit(row) {
        if (!row) return;
        document.querySelectorAll('#refundTable tbody tr.is-editing').forEach(editingRow => { if (editingRow !== row) cancelEdit(editingRow); });
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
        const res = await fetch(`${document.querySelector('.refund-page').dataset.updateBase}/${row.dataset.rowId}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':payload._token,'Accept':'application/json'},
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            alert(data.message || '更新に失敗しました。');
            return;
        }
        sessionStorage.setItem('refundUpdatedRowId', row.dataset.rowId);
        window.location.reload();
    }

    const updatedRowId = sessionStorage.getItem('refundUpdatedRowId');
    if (updatedRowId) {
        sessionStorage.removeItem('refundUpdatedRowId');
        const row = document.querySelector(`#refundTable tbody tr[data-row-id="${updatedRowId}"]`);
        if (row) {
            row.classList.add('is-saved-highlight');
            setTimeout(() => row.classList.remove('is-saved-highlight'), 1200);
        }
    }

    function initColumnFilters() {
        const active = {};
        const map = {school:'filterSchool',student:'filterStudent',source:'filterSource',method:'filterMethod',status:'filterStatus'};
        document.querySelectorAll('.refund-column-filter-button').forEach(button => {
            button.addEventListener('click', event => {
                event.stopPropagation();
                document.querySelector('.refund-column-filter-menu')?.remove();
                const key = button.dataset.filterCol;
                const dataKey = map[key];
                const values = [...new Set([...document.querySelectorAll('#refundTable tbody tr')].map(row => row.querySelector(`[data-${dataKey.replace(/[A-Z]/g, m => '-' + m.toLowerCase())}]`)?.dataset[dataKey]).filter(Boolean))].sort();
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
        document.querySelectorAll('#refundTable tbody tr').forEach(row => {
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
        const amountData = window.refundAmountChartData || [];
        const countData = window.refundCountChartData || [];
        const sourceData = window.refundSourceChartData || [];
        const yenTick = value => '¥' + Number(value || 0).toLocaleString();
        const amountCanvas = document.getElementById('refundAmountChart');
        if (amountCanvas) new Chart(amountCanvas, {type:'line',data:{labels:amountData.map(r=>r.month),datasets:[{label:'返金額',data:amountData.map(r=>r.amount),borderColor:'#3b82f6',backgroundColor:'transparent',pointBackgroundColor:'#3b82f6',pointBorderColor:'#3b82f6',tension:.25,fill:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{labels:{usePointStyle:true,pointStyle:'line'}}},scales:{y:{ticks:{callback:yenTick}}}}});
        const countCanvas = document.getElementById('refundCountChart');
        if (countCanvas) new Chart(countCanvas, {type:'bar',data:{labels:countData.map(r=>r.month),datasets:[{label:'件数',data:countData.map(r=>r.count)}]},options:{responsive:true,maintainAspectRatio:false,plugins:{tooltip:{callbacks:{afterBody:ctx=>{const i=ctx[0].dataIndex;return amountData[i] ? `返金額 ${yen(amountData[i].amount)}` : '';}}}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
        const sourceCanvas = document.getElementById('refundSourceChart');
        const totalSourceAmount = sourceData.reduce((sum, row) => sum + Number(row.amount || 0), 0);
        const refundDoughnutAmountLabelPlugin = {
            id: 'refundDoughnutAmountLabel',
            afterDatasetsDraw(chart) {
                const {ctx} = chart;
                const meta = chart.getDatasetMeta(0);
                const data = chart.data.datasets[0].data || [];
                ctx.save();
                ctx.font = 'bold 12px sans-serif';
                ctx.fillStyle = '#0f172a';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                meta.data.forEach((arc, index) => {
                    const value = Number(data[index] || 0);
                    if (!value) return;
                    const percent = totalSourceAmount > 0 ? Math.round(value / totalSourceAmount * 100) : 0;
                    const pos = arc.tooltipPosition();
                    ctx.fillText(`${yen(value)}（${percent}%）`, pos.x, pos.y);
                });
                ctx.restore();
            }
        };
        if (sourceCanvas) new Chart(sourceCanvas, {type:'doughnut',data:{labels:sourceData.map(r=>r.label),datasets:[{data:sourceData.map(r=>r.amount)}]},options:{responsive:true,maintainAspectRatio:false,plugins:{tooltip:{callbacks:{label:(ctx)=>{const value=Number(ctx.raw||0);const percent=totalSourceAmount>0?Math.round(value/totalSourceAmount*100):0;return `${ctx.label}: ${yen(value)}（${percent}%）`;}}}}},plugins:[refundDoughnutAmountLabelPlugin]});
    }
    renderCharts();

    document.getElementById('eventChartToggle')?.addEventListener('click', event => {
        const body = document.getElementById('eventChartBody');
        const hidden = body.style.display === 'none';
        body.style.display = hidden ? '' : 'none';
        event.target.textContent = hidden ? '−' : '+';
    });
});
