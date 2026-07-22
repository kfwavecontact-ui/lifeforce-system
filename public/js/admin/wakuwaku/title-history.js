document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.title-history-page');
    if (!page) return;

    const listUrl = page.dataset.listUrl;
    const detailBase = page.dataset.detailUrlBase;
    const grantUrl = page.dataset.grantUrl;
    const studentLookupUrl = page.dataset.studentLookupUrl;
    const titleLookupUrl = page.dataset.titleLookupUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const body = document.getElementById('historyTableBody');
    let currentSort = 'acquired_desc';
    let debounceTimer = null;

    const filters = {
        keyword: document.getElementById('historyKeyword'), school_id: document.getElementById('historySchool'),
        title_id: document.getElementById('historyTitle'), category_id: document.getElementById('historyCategory'),
        series_id: document.getElementById('historySeries'), status: document.getElementById('historyStatus'),
        grant_method: document.getElementById('historyMethod'), date_from: document.getElementById('historyDateFrom'),
        date_to: document.getElementById('historyDateTo'), sort: document.getElementById('historySort')
    };

    const params = (exportCsv = false) => {
        const search = new URLSearchParams();
        Object.entries(filters).forEach(([key, el]) => search.set(key, el?.value ?? ''));
        search.set('sort', currentSort);
        if (exportCsv) search.set('export', '1');
        return search;
    };
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const showToast = (message, error = false) => {
        const toast = document.getElementById('historyToast');
        toast.textContent = message; toast.classList.toggle('is-error', error); toast.classList.add('is-show');
        setTimeout(() => toast.classList.remove('is-show'), 3200);
    };
    const requestJson = async (url, options = {}) => {
        const response = await fetch(url, {headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf,...(options.headers||{})}, ...options});
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(firstError || data.message || '処理に失敗しました。');
        }
        return data;
    };


    const formatLocalDateTime = date => {
        const pad = value => String(value).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };
    const relativeTime = value => {
        if (!value) return '';
        const date = new Date(String(value).replace(/\//g, '-'));
        if (Number.isNaN(date.getTime())) return '';
        const diff = Date.now() - date.getTime();
        const day = Math.floor(diff / 86400000);
        if (day <= 0) return '今日';
        if (day === 1) return '昨日';
        if (day < 31) return `${day}日前`;
        return '';
    };

    const createLookup = ({inputId, hiddenId, selectedId, resultsId, url, emptyMessage}) => {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const selected = document.getElementById(selectedId);
        const results = document.getElementById(resultsId);
        let timer = null;
        let controller = null;
        let activeIndex = -1;

        const close = () => { results.hidden = true; results.innerHTML = ''; activeIndex = -1; };
        const clear = () => {
            hidden.value = '';
            input.value = '';
            input.disabled = false;
            selected.hidden = true;
            selected.innerHTML = '';
            close();
        };
        const choose = item => {
            hidden.value = item.id;
            input.value = '';
            input.disabled = true;
            selected.innerHTML = `<div><strong>${esc(item.primary)}</strong><small>${esc(item.secondary || '')}</small></div><button type="button" aria-label="選択解除">×</button>`;
            selected.hidden = false;
            selected.querySelector('button').addEventListener('click', clear);
            close();
        };
        const search = async () => {
            const keyword = input.value.trim();
            if (!keyword) { close(); return; }
            controller?.abort();
            controller = new AbortController();
            results.hidden = false;
            results.innerHTML = '<div class="history-lookup-message">検索中...</div>';
            try {
                const response = await fetch(`${url}?q=${encodeURIComponent(keyword)}`, {headers:{'Accept':'application/json'}, signal:controller.signal});
                if (!response.ok) throw new Error('検索に失敗しました。');
                const data = await response.json();
                const items = data.items || [];
                activeIndex = -1;
                results.innerHTML = items.length
                    ? items.map(item => `<button type="button" class="history-lookup-option" data-id="${item.id}" data-primary="${esc(item.primary)}" data-secondary="${esc(item.secondary || '')}"><strong>${esc(item.primary)}</strong><small>${esc(item.secondary || '')}</small></button>`).join('')
                    : `<div class="history-lookup-message">${esc(emptyMessage)}</div>`;
            } catch (error) {
                if (error.name !== 'AbortError') results.innerHTML = `<div class="history-lookup-message is-error">${esc(error.message)}</div>`;
            }
        };

        input.addEventListener('input', () => {
            hidden.value = '';
            clearTimeout(timer);
            timer = setTimeout(search, 300);
        });
        input.addEventListener('focus', () => { if (input.value.trim()) search(); });
        results.addEventListener('click', event => {
            const option = event.target.closest('.history-lookup-option');
            if (!option) return;
            choose({id: option.dataset.id, primary: option.dataset.primary, secondary: option.dataset.secondary});
        });
        input.addEventListener('keydown', event => {
            const options = [...results.querySelectorAll('.history-lookup-option')];
            if (event.key === 'Escape') { close(); return; }
            if (!options.length) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = event.key === 'ArrowDown'
                    ? (activeIndex + 1) % options.length
                    : (activeIndex <= 0 ? options.length - 1 : activeIndex - 1);
                options.forEach((option, index) => option.classList.toggle('is-active', index === activeIndex));
                options[activeIndex].scrollIntoView({block:'nearest'});
            }
            if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                options[activeIndex].click();
            }
        });
        document.addEventListener('click', event => {
            if (!event.target.closest(`[data-lookup="${input.closest('.history-lookup').dataset.lookup}"]`)) close();
        });

        return {clear, hasValue: () => hidden.value !== ''};
    };

    const load = async () => {
        body.innerHTML = '<tr><td colspan="12" class="history-loading">読み込み中...</td></tr>';
        try {
            const response = await fetch(`${listUrl}?${params()}`, {headers:{'Accept':'application/json'}});
            if (!response.ok) throw new Error('一覧取得に失敗しました。');
            const data = await response.json();
            renderRows(data.rows || []); renderSummary(data.summary || {});
            document.getElementById('historyResultCount').textContent = `${data.result_count ?? 0}件`;
        } catch (error) { body.innerHTML = `<tr><td colspan="12" class="history-empty">${esc(error.message)}</td></tr>`; }
    };
    const renderSummary = summary => {
        document.getElementById('historyTotalGrants').textContent = summary.total_grants ?? 0;
        document.getElementById('historyActiveHoldings').textContent = summary.active_holdings ?? 0;
        document.getElementById('historyMonthlyGrants').textContent = summary.monthly_grants ?? 0;
        document.getElementById('historyMonthlyRemovals').textContent = summary.monthly_removals ?? 0;
        document.getElementById('historyMonthlyRegrants').textContent = summary.monthly_regrants ?? 0;
    };
    const renderRows = rows => {
        if (!rows.length) { body.innerHTML = '<tr><td colspan="12" class="history-empty">該当する獲得履歴がありません。</td></tr>'; return; }
        body.innerHTML = rows.map(row => `
            <tr class="history-data-row" data-row-history-id="${row.id}">
                <td>${row.id}</td><td><div class="history-date-cell"><strong>${esc(row.acquired_at || '-')}</strong><small>${esc(relativeTime(row.acquired_at))}</small></div></td>
                <td><div class="history-student"><strong>${esc(row.student_name)}</strong><small>${esc(row.student_code || '')}</small></div></td>
                <td>${esc(row.school_name)}</td>
                <td><div class="history-title-cell">${row.title_image_path ? `<img class="history-title-image" src="${esc(row.title_image_path)}" alt="" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'history-title-image',innerHTML:'<i class=\'fas fa-crown\'></i>'}))">` : '<span class="history-title-image"><i class="fas fa-crown"></i></span>'}<div class="history-title"><strong>${esc(row.title_name)}</strong><small>${esc(row.title_code || '')}</small></div></div></td>
                <td>${esc(row.category_name)}</td><td>${esc(row.series_name)}</td>
                <td><span class="history-pill ${esc(row.grant_method)}">${esc(row.grant_method_label)}</span></td>
                <td><span class="history-operator"><i class="fas ${row.granted_by_name === 'システム' ? 'fa-robot' : 'fa-user-shield'}"></i>${esc(row.granted_by_name)}</span></td><td><span class="history-pill ${esc(row.status)}"><i class="fas ${row.status === 'active' ? 'fa-circle-check' : 'fa-circle-minus'}"></i>${esc(row.status_label)}</span></td>
                <td class="history-reason" title="${esc(row.removal_reason_detail || row.grant_reason || '')}">${esc(row.removal_reason_detail || row.grant_reason || '-')}</td>
                <td class="history-operation-column"><div class="history-row-actions"><button class="history-detail-button" data-history-id="${row.id}">詳細</button>${row.status === 'active' ? `<button class="history-remove-button" data-remove-id="${row.id}" data-student="${esc(row.student_name)}" data-title="${esc(row.title_name)}">取り外す</button>` : `<button class="history-regrant-button" data-regrant-id="${row.id}" data-student="${esc(row.student_name)}" data-title="${esc(row.title_name)}">再付与</button>`}</div></td>
            </tr>`).join('');
    };

    Object.entries(filters).forEach(([key, el]) => {
        if (!el) return;
        el.addEventListener(key === 'keyword' ? 'input' : 'change', () => {
            if (key === 'sort') currentSort = el.value;
            clearTimeout(debounceTimer); debounceTimer = setTimeout(load, key === 'keyword' ? 450 : 0);
        });
    });
    document.querySelectorAll('.history-table th button[data-sort]').forEach(button => button.addEventListener('click', () => {
        const base = button.dataset.sort, asc = `${base}_asc`, desc = `${base}_desc`;
        currentSort = currentSort === asc ? desc : asc; filters.sort.value = currentSort;
        document.querySelectorAll('.history-table th button span').forEach(el => el.textContent = '↕');
        button.querySelector('span').textContent = currentSort.endsWith('_asc') ? '↑' : '↓'; load();
    }));
    document.getElementById('historyResetButton').addEventListener('click', () => {
        Object.entries(filters).forEach(([key, el]) => { if (el) el.value = key === 'sort' ? 'acquired_desc' : (el.tagName === 'SELECT' ? 'all' : ''); });
        currentSort = 'acquired_desc'; load();
    });
    document.getElementById('historyCsvButton').addEventListener('click', () => setModal(csvModal, true));

    const detailModal = document.getElementById('historyDetailModal');
    const manualModal = document.getElementById('manualGrantModal');
    const operationModal = document.getElementById('titleOperationModal');
    const csvModal = document.getElementById('historyCsvModal');
    const setModal = (modal, open) => { modal.classList.toggle('is-open', open); modal.setAttribute('aria-hidden', open ? 'false' : 'true'); };
    const studentLookup = createLookup({
        inputId: 'manualStudentLookup', hiddenId: 'manualStudentId', selectedId: 'manualStudentSelected', resultsId: 'manualStudentResults',
        url: studentLookupUrl, emptyMessage: '該当する生徒が見つかりません。'
    });
    const titleLookup = createLookup({
        inputId: 'manualTitleLookup', hiddenId: 'manualTitleId', selectedId: 'manualTitleSelected', resultsId: 'manualTitleResults',
        url: titleLookupUrl, emptyMessage: '手動付与できる称号が見つかりません。'
    });

    document.getElementById('manualGrantButton').addEventListener('click', () => {
        document.getElementById('manualGrantForm').reset();
        studentLookup.clear();
        titleLookup.clear();
        document.querySelector('#manualGrantForm [name="is_displayed"]').checked = true;
        document.getElementById('manualGrantError').textContent='';
        setModal(manualModal, true);
        setTimeout(() => document.getElementById('manualStudentLookup').focus(), 80);
    });
    document.getElementById('setCurrentAcquiredAt').addEventListener('click', () => {
        document.getElementById('manualAcquiredAt').value = formatLocalDateTime(new Date());
    });
    document.querySelectorAll('[data-close-history-modal]').forEach(el => el.addEventListener('click', () => setModal(detailModal, false)));
    document.querySelectorAll('[data-close-manual-modal]').forEach(el => el.addEventListener('click', () => setModal(manualModal, false)));
    document.querySelectorAll('[data-close-operation-modal]').forEach(el => el.addEventListener('click', () => setModal(operationModal, false)));
    document.querySelectorAll('[data-close-csv-modal]').forEach(el => el.addEventListener('click', () => setModal(csvModal, false)));
    document.getElementById('csvSelectAll').addEventListener('click', () => document.querySelectorAll('#historyCsvColumns input').forEach(el => el.checked = true));
    document.getElementById('csvClearAll').addEventListener('click', () => document.querySelectorAll('#historyCsvColumns input').forEach(el => el.checked = false));
    document.getElementById('historyCsvExportConfirm').addEventListener('click', () => {
        const columns = [...document.querySelectorAll('#historyCsvColumns input:checked')].map(el => el.value);
        if (!columns.length) { showToast('CSVへ出力する項目を1つ以上選択してください。', true); return; }
        const query = params(true); query.set('csv_columns', columns.join(','));
        window.location.href = `${listUrl}?${query}`; setModal(csvModal, false);
    });

    body.addEventListener('click', async event => {
        const row = event.target.closest('[data-row-history-id]');
        const detail = event.target.closest('[data-history-id]');
        if (!detail && row && !event.target.closest('button,a,input,select,textarea')) {
            try { openDetail(await requestJson(`${detailBase}/${row.dataset.rowHistoryId}`, {method:'GET', headers:{'Content-Type':'application/json'}})); }
            catch(e){ showToast(e.message, true); }
            return;
        }
        if (detail) {
            try { openDetail(await requestJson(`${detailBase}/${detail.dataset.historyId}`, {method:'GET', headers:{'Content-Type':'application/json'}})); }
            catch(e){ showToast(e.message, true); }
            return;
        }
        const remove = event.target.closest('[data-remove-id]');
        if (remove) return openOperation('remove', remove.dataset.removeId, remove.dataset.student, remove.dataset.title);
        const regrant = event.target.closest('[data-regrant-id]');
        if (regrant) return openOperation('regrant', regrant.dataset.regrantId, regrant.dataset.student, regrant.dataset.title);
    });

    const openDetail = data => {
        const grant = data.grant;
        document.getElementById('historyDetailSubtitle').textContent = `${grant.student_name} / ${grant.title_name}`;
        document.getElementById('historyDetailHero').innerHTML = `${grant.title_image_path ? `<img src="${esc(grant.title_image_path)}" alt="">` : '<span><i class="fas fa-crown"></i></span>'}<div><strong>${esc(grant.title_name)}</strong><small>${esc(grant.title_code || '')}</small><p>${esc(grant.student_name)}（${esc(grant.student_code || '-') }）</p></div>`;
        const fields = [['生徒',grant.student_name],['生徒コード',grant.student_code],['教室',grant.school_name],['カテゴリ',grant.category_name],['シリーズ',grant.series_name],['獲得日時',grant.acquired_at],['付与方法',grant.grant_method_label],['付与者',grant.granted_by_name],['状態',grant.status_label],['生徒画面表示',grant.is_displayed ? '表示' : '非表示'],['付与通知',grant.grant_notification_sent ? '送信済み' : '未送信'],['取り外し日時',grant.removed_at || '-'],['取り外し担当',grant.removed_by_name || '-'],['取り外し通知',grant.removal_notification_sent ? '送信済み' : '未送信'],['再付与元ID',grant.regrant_source_id || '-'],['付与理由',grant.grant_reason || '-'],['取り外し理由',grant.removal_reason_detail || '-']];
        document.getElementById('historyDetailGrid').innerHTML = fields.map(([label,value]) => `<article><small>${esc(label)}</small><strong>${esc(value || '-')}</strong></article>`).join('');
        document.getElementById('historyTimeline').innerHTML = (data.events || []).length ? data.events.map(item => `<article><strong>${esc(item.event_type_label)}</strong><p>${esc(item.reason_detail || '理由の記録なし')}</p><small>${esc(item.event_at)} / ${esc(item.operator_name)}</small></article>`).join('') : '<p class="history-empty">操作履歴がありません。</p>';
        setModal(detailModal, true);
    };
    const openOperation = (type, id, student, title) => {
        const form = document.getElementById('titleOperationForm'); form.reset();
        form.elements.grant_id.value = id; form.elements.operation_type.value = type;
        document.getElementById('titleOperationTitle').textContent = type === 'remove' ? '称号を取り外す' : '称号を再付与する';
        document.getElementById('titleOperationSubtitle').textContent = `${student} / ${title}`;
        document.getElementById('removeFields').style.display = type === 'remove' ? 'grid' : 'none';
        document.getElementById('regrantFields').style.display = type === 'regrant' ? 'grid' : 'none';
        document.getElementById('titleOperationSubmit').textContent = type === 'remove' ? '取り外す' : '再付与する';
        document.getElementById('titleOperationWarning').classList.toggle('is-danger', type === 'remove');
        document.getElementById('titleOperationError').textContent=''; setModal(operationModal, true);
    };

    document.getElementById('manualGrantForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const error = document.getElementById('manualGrantError');
        error.textContent = '';
        if (!studentLookup.hasValue()) { error.textContent = '対象生徒を検索結果から選択してください。'; document.getElementById('manualStudentLookup').focus(); return; }
        if (!titleLookup.hasValue()) { error.textContent = '称号を検索結果から選択してください。'; document.getElementById('manualTitleLookup').focus(); return; }
        const submit = form.querySelector('[type="submit"]'); submit.disabled = true;
        const fd = new FormData(form); const payload = Object.fromEntries(fd.entries()); payload.notify = form.elements.notify.checked; payload.is_displayed = form.elements.is_displayed.checked;
        try { const data = await requestJson(grantUrl, {method:'POST', body:JSON.stringify(payload)}); setModal(manualModal,false); showToast(data.message); await load(); }
        catch(e){ error.textContent=e.message; } finally { submit.disabled=false; }
    });
    document.getElementById('titleOperationForm').addEventListener('submit', async event => {
        event.preventDefault(); const form=event.currentTarget; const type=form.elements.operation_type.value, id=form.elements.grant_id.value, submit=form.querySelector('[type="submit"]'); submit.disabled=true;
        if (!form.elements.confirmed.checked) { document.getElementById('titleOperationError').textContent='対象と操作内容を確認してください。'; submit.disabled=false; return; }
        if (type === 'remove' && form.elements.reason_code.value === 'other' && !form.elements.reason_detail.value.trim()) { document.getElementById('titleOperationError').textContent='「その他」を選択した場合は理由の詳細を入力してください。'; submit.disabled=false; return; }
        const payload = type === 'remove' ? {reason_code:form.elements.reason_code.value,reason_detail:form.elements.reason_detail.value,removed_at:form.elements.removed_at.value,notify:form.elements.notify.checked} : {reason:form.elements.reason.value,acquired_at:form.elements.acquired_at.value,notify:form.elements.notify.checked,is_displayed:form.elements.is_displayed.checked};
        try { const data=await requestJson(`${detailBase}/${id}/${type}`, {method:'POST',body:JSON.stringify(payload)}); setModal(operationModal,false); showToast(data.message); await load(); }
        catch(e){ document.getElementById('titleOperationError').textContent=e.message; } finally { submit.disabled=false; }
    });
    load();
});
