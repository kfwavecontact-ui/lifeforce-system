document.addEventListener('DOMContentLoaded', () => {
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[char]);

    const modal = document.getElementById('assignModal');
    if (modal) {
        const renderItems = (items = []) => {
            const container = document.getElementById('assignItems');
            if (!container) return;
            if (!items.length) {
                container.innerHTML = '<p class="er-empty">構成アイテムがありません。</p>';
                return;
            }
            container.innerHTML = items.map((item, index) => {
                const name = item.item_name || item.master_name || '名称未設定';
                const pageReady = ['created', 'completed', 'published', '作成済み'].includes(item.learning_page_status)
                    || item.publish_status === true || item.publish_status === 1;
                return `<article class="er-assign-item">
                    <input type="hidden" name="items[${item.id}][enabled]" value="0">
                    <label class="er-check er-assign-item-head">
                        <input type="checkbox" name="items[${item.id}][enabled]" value="1" checked>
                        <strong>${escapeHtml(name)}</strong>
                        <span class="er-badge ${pageReady ? 'green' : 'amber'}">${pageReady ? '学習可能' : 'ページ要確認'}</span>
                    </label>
                    <div class="er-assign-item-grid">
                        <label>生徒用名称<input name="items[${item.id}][item_name]" value="${escapeHtml(name)}" maxlength="255"></label>
                        <label>並び順<input type="number" name="items[${item.id}][order_no]" value="${item.order_no ?? index + 1}" min="0"></label>
                        <label>目標値<input type="number" step="0.01" min="0" name="items[${item.id}][target_value]" value="${item.target_value ?? ''}"></label>
                        <label>必要日数<input type="number" min="0" name="items[${item.id}][required_days]" value="${item.required_days ?? ''}"></label>
                        <label>目安時間（分）<input type="number" min="0" name="items[${item.id}][estimated_minutes]" value="${item.estimated_minutes ?? ''}"></label>
                        <label class="er-check"><input type="hidden" name="items[${item.id}][is_required]" value="0"><input type="checkbox" name="items[${item.id}][is_required]" value="1" ${item.is_required ? 'checked' : ''}> 必須</label>
                        <label class="full">メモ<textarea name="items[${item.id}][memo]" rows="2">${escapeHtml(item.memo ?? '')}</textarea></label>
                    </div>
                </article>`;
            }).join('');
        };

        const decodePackageData = (encoded) => {
            if (!encoded) return {};
            try {
                const bytes = Uint8Array.from(atob(encoded), (char) => char.charCodeAt(0));
                return JSON.parse(new TextDecoder('utf-8').decode(bytes));
            } catch (error) {
                console.error('ルーティン割当データの読み込みに失敗しました。', error);
                return {};
            }
        };

        let packageMap = {};
        const packageDataElement = document.getElementById('routinePackageData');
        if (packageDataElement) {
            try {
                packageMap = JSON.parse(packageDataElement.textContent || '{}');
            } catch (error) {
                console.error('ルーティン一覧データの読み込みに失敗しました。', error);
            }
        }

        const openAssignModal = (button) => {
            const packageId = String(button.dataset.packageId || '');
            const packageData = packageMap[packageId] || decodePackageData(button.dataset.package);
            if (!packageData.id) {
                window.alert('割り当てるルーティン情報を読み込めませんでした。画面を再読み込みしてください。');
                return;
            }
            document.getElementById('assignPackageId').value = packageData.id || '';
            document.getElementById('assignPackageName').textContent = packageData.name || '';
            document.getElementById('assignName').value = packageData.name || '';
            document.getElementById('assignDescription').value = packageData.description || '';
            renderItems(packageData.items || []);

            const warnings = [];
            if (!packageData.items || packageData.items.length === 0) warnings.push('構成アイテムがありません。');
            const unfinished = (packageData.items || []).filter((item) => !['created','completed','published','作成済み'].includes(item.learning_page_status) && item.publish_status !== true && item.publish_status !== 1).length;
            if (unfinished) warnings.push(`学習ページを確認する必要があるアイテムが${unfinished}件あります。`);
            document.getElementById('assignWarnings').textContent = warnings.length ? warnings.join(' ') : '保存前に対象生徒・期間・構成アイテムを確認してください。';
            if (typeof modal.showModal === 'function') {
                modal.showModal();
            } else {
                modal.setAttribute('open', 'open');
            }
        };

        document.addEventListener('click', (event) => {
            const button = event.target.closest('.js-open-assign');
            if (!button) return;
            event.preventDefault();
            openAssignModal(button);
        });

        document.querySelectorAll('.js-close-modal').forEach((button) => button.addEventListener('click', () => modal.close()));
        modal.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });
    }

    const drawer = document.getElementById('routineDetailDrawer');
    if (drawer) {
        const title = document.getElementById('routineDrawerTitle');
        const body = document.getElementById('routineDrawerBody');
        const closeDrawer = () => {
            drawer.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('er-drawer-open');
        };
        document.querySelectorAll('.js-open-drawer').forEach((button) => button.addEventListener('click', () => {
            title.textContent = button.dataset.drawerTitle || '詳細';
            body.innerHTML = `<p>${escapeHtml(button.dataset.drawerBody || '詳細情報はありません。')}</p>`;
            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
            document.body.classList.add('er-drawer-open');
        }));
        drawer.querySelectorAll('.js-close-drawer').forEach((button) => button.addEventListener('click', closeDrawer));
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeDrawer(); });
    }
});

/*
 * 生徒カルテ共通ルーティン表のカレンダーモーダル制御。
 * モーダルが横スクロール領域内にあっても確実に表示できるよう、
 * 開く直前に document.body 直下へ移動する。
 */
(function initializeRoutineCalendarModal() {
    const closeModal = (modal) => {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    };

    document.addEventListener('click', function (event) {
        const openButton = event.target.closest('[data-routine-modal-target]');
        if (openButton) {
            const selector = openButton.getAttribute('data-routine-modal-target');
            const modal = selector ? document.querySelector(selector) : null;
            if (!modal) return;

            event.preventDefault();
            event.stopPropagation();

            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            return;
        }

        const closeButton = event.target.closest('[data-routine-modal-close]');
        if (closeButton) {
            event.preventDefault();
            closeModal(closeButton.closest('.routine-modal'));
            return;
        }

        if (event.target.classList && event.target.classList.contains('routine-modal')) {
            closeModal(event.target);
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.routine-modal.is-open').forEach(closeModal);
    });
})();

/*
 * 割当済ルーティン状況の詳細・カレンダー制御。
 * 同じJSON取得結果を共有し、詳細とカレンダーの操作だけを分離する。
 */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-page="history-items"]');
    if (!page) return;

    const escapeHistoryHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[char]);
    const detailCache = new Map();
    const loadDetail = async (url) => {
        if (detailCache.has(url)) return detailCache.get(url);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        detailCache.set(url, data);
        return data;
    };
    const formatValue = (value, empty = '—') => value === null || value === undefined || value === '' ? empty : value;

    const drawer = document.getElementById('routineDetailDrawer');
    const drawerTitle = document.getElementById('routineDrawerTitle');
    const drawerBody = document.getElementById('routineDrawerBody');
    const openDrawer = () => {
        drawer?.classList.add('open');
        drawer?.setAttribute('aria-hidden', 'false');
        document.body.classList.add('er-drawer-open');
    };

    page.addEventListener('click', async (event) => {
        const detailButton = event.target.closest('.js-open-item-detail');
        if (!detailButton) return;
        event.preventDefault();
        drawerTitle.textContent = '詳細を読み込んでいます';
        drawerBody.innerHTML = '<p class="er-empty">しばらくお待ちください。</p>';
        openDrawer();
        try {
            const data = await loadDetail(detailButton.dataset.detailUrl);
            const item = data.item || {};
            const daily = Array.isArray(data.daily) ? data.daily : [];
            const sessions = Array.isArray(data.sessions) ? data.sessions : [];
            drawerTitle.textContent = data.title || 'ルーティンアイテム詳細';
            drawerBody.innerHTML = `
                <section class="er-detail-block">
                    <h3>割当情報</h3>
                    <dl>
                        <dt>生徒</dt><dd>${escapeHistoryHtml(formatValue(data.student_name || item.student_name))}</dd>
                        <dt>所属ルーティン</dt><dd>${escapeHistoryHtml(formatValue(item.routine_name))}</dd>
                        <dt>ルーティンアイテム</dt><dd>${escapeHistoryHtml(formatValue(item.item_name))}</dd>
                        <dt>元アイテム</dt><dd>${escapeHistoryHtml(formatValue(item.master_item_name))}</dd>
                        <dt>元アイテムコード</dt><dd>${escapeHistoryHtml(formatValue(item.content_code))}</dd>
                        <dt>必須区分</dt><dd>${item.is_required ? '必須' : '任意'}</dd>
                        <dt>開始日</dt><dd>${escapeHistoryHtml(formatValue(item.start_date))}</dd>
                        <dt>完了日時</dt><dd>${escapeHistoryHtml(formatValue(item.completed_at))}</dd>
                        <dt>必要日数</dt><dd>${escapeHistoryHtml(item.required_days ? `${item.required_days}日` : '—')}</dd>
                        <dt>目標値</dt><dd>${escapeHistoryHtml(formatValue(item.target_value))}</dd>
                        <dt>メモ</dt><dd>${escapeHistoryHtml(formatValue(item.memo))}</dd>
                    </dl>
                </section>
                <section class="er-detail-block">
                    <h3>日次履歴 <small>${daily.length}件</small></h3>
                    ${daily.length ? `<div class="er-detail-list">${daily.slice().reverse().map((row) => `
                        <article><strong>${escapeHistoryHtml(formatValue(row.target_date))}</strong><span>${escapeHistoryHtml(formatValue(row.status, '未着手'))}</span><small>学習時間 ${escapeHistoryHtml(formatValue(row.study_seconds, 0))}秒／達成率 ${escapeHistoryHtml(formatValue(row.achievement_rate))}${row.achievement_rate === null || row.achievement_rate === undefined ? '' : '%'}</small></article>
                    `).join('')}</div>` : '<p class="er-inline-empty">日次履歴はありません。</p>'}
                </section>
                <section class="er-detail-block">
                    <h3>学習結果 <small>${sessions.length}件</small></h3>
                    ${sessions.length ? `<div class="er-detail-list">${sessions.map((row) => `
                        <article><strong>${escapeHistoryHtml(formatValue(row.started_at))}</strong><span>${row.is_completed ? '完了' : '中断'}</span><small>正答率 ${row.accuracy_rate === null || row.accuracy_rate === undefined ? '—' : `${escapeHistoryHtml(row.accuracy_rate)}%`}／スコア ${escapeHistoryHtml(formatValue(row.score))}</small></article>
                    `).join('')}</div>` : '<p class="er-inline-empty">学習結果はありません。</p>'}
                </section>`;
        } catch (error) {
            console.error('割当済ルーティンアイテム詳細の取得に失敗しました。', error);
            drawerTitle.textContent = '詳細を表示できません';
            drawerBody.innerHTML = '<p class="er-warning">詳細情報の読み込みに失敗しました。画面を再読み込みして、もう一度お試しください。</p>';
        }
    });

    const calendarModal = document.getElementById('routineItemCalendarModal');
    const calendarTitle = document.getElementById('routineItemCalendarTitle');
    const calendarBody = document.getElementById('routineItemCalendarBody');
    const closeCalendar = () => {
        calendarModal?.classList.remove('is-open');
        calendarModal?.setAttribute('aria-hidden', 'true');
    };

    page.addEventListener('click', async (event) => {
        const calendarButton = event.target.closest('.js-open-item-calendar');
        if (!calendarButton) return;
        event.preventDefault();
        calendarTitle.textContent = 'カレンダーを読み込んでいます';
        calendarBody.innerHTML = '<p class="er-empty">しばらくお待ちください。</p>';
        if (calendarModal.parentElement !== document.body) document.body.appendChild(calendarModal);
        calendarModal.classList.add('is-open');
        calendarModal.setAttribute('aria-hidden', 'false');
        try {
            const data = await loadDetail(calendarButton.dataset.detailUrl);
            const calendar = Array.isArray(data.calendar) ? data.calendar : [];
            calendarTitle.textContent = `カレンダー：${data.title || 'ルーティンアイテム'}`;
            calendarBody.innerHTML = calendar.length ? `
                <div class="er-history-calendar-legend"><span><b class="worked">○</b> 取組あり</span><span><b class="missed">×</b> 未実施</span></div>
                <div class="er-detail-calendar">${calendar.map((day) => `
                    <div class="er-detail-calendar-day ${day.worked ? 'worked' : 'missed'}"><span>${escapeHistoryHtml(day.date)}</span><b>${day.worked ? '○' : '×'}</b></div>
                `).join('')}</div>` : '<p class="er-inline-empty">表示できる期間がありません。</p>';
        } catch (error) {
            console.error('カレンダーの取得に失敗しました。', error);
            calendarTitle.textContent = 'カレンダーを表示できません';
            calendarBody.innerHTML = '<p class="er-warning">カレンダーの読み込みに失敗しました。画面を再読み込みして、もう一度お試しください。</p>';
        }
    });

    calendarModal?.querySelectorAll('[data-routine-modal-close]').forEach((button) => button.addEventListener('click', closeCalendar));
    calendarModal?.addEventListener('click', (event) => { if (event.target === calendarModal) closeCalendar(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeCalendar(); });

});
