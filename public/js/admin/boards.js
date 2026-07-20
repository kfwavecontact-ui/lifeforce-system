/* 運営 ＞ 連絡 ＞ 教室からのご連絡：モーダル、操作メニュー、公開対象切替を管理します。 */
document.addEventListener('DOMContentLoaded', () => {
    const formModal = document.getElementById('boardFormModal');
    const detailModal = document.getElementById('boardDetailModal');
    const previewModal = document.getElementById('boardPreviewModal');
    const audienceStatusModal = document.getElementById('boardAudienceStatusModal');
    const form = document.getElementById('boardForm');
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const statusLabel = (status) => ({draft:'下書き',published:'公開',closed:'公開終了'}[status] || status);
    const openModal = (modal) => { modal?.classList.add('open'); modal?.setAttribute('aria-hidden', 'false'); document.body.style.overflow='hidden'; };
    const closeModal = (modal) => { modal?.classList.remove('open'); modal?.setAttribute('aria-hidden', 'true'); if(!document.querySelector('.board-modal.open')) document.body.style.overflow=''; };


    const attachmentInput = document.getElementById('boardAttachments');
    const selectedAttachmentArea = document.getElementById('boardSelectedAttachments');
    const currentAttachmentArea = document.getElementById('boardCurrentAttachments');
    let selectedAttachmentFiles = [];

    function attachmentKey(file) {
        return `${file.name}::${file.size}::${file.lastModified}`;
    }

    function syncAttachmentInput() {
        if (!attachmentInput) return;
        const transfer = new DataTransfer();
        selectedAttachmentFiles.forEach((file) => transfer.items.add(file));
        attachmentInput.files = transfer.files;
    }

    function currentRemainingAttachmentCount() {
        if (!currentAttachmentArea) return 0;
        return [...currentAttachmentArea.querySelectorAll('.board-current-attachment-item')]
            .filter((item) => !item.querySelector('input[type="checkbox"]')?.checked)
            .length;
    }

    function renderSelectedAttachments() {
        if (!selectedAttachmentArea) return;
        selectedAttachmentArea.innerHTML = selectedAttachmentFiles.length
            ? `<div class="board-selected-attachments-title">今回追加する添付ファイル（${selectedAttachmentFiles.length}件）</div>${selectedAttachmentFiles.map((file, index) => `<div class="board-selected-attachment-item"><span class="board-selected-attachment-name">${escapeHtml(file.name)}</span><span class="board-selected-attachment-size">${(file.size / 1024 / 1024).toFixed(2)}MB</span><button type="button" data-board-remove-selected-attachment="${index}">削除</button></div>`).join('')}`
            : '';
    }

    function resetSelectedAttachments() {
        selectedAttachmentFiles = [];
        if (attachmentInput) attachmentInput.value = '';
        renderSelectedAttachments();
    }

    attachmentInput?.addEventListener('change', () => {
        const newlySelected = [...attachmentInput.files];
        const existingKeys = new Set(selectedAttachmentFiles.map(attachmentKey));
        const uniqueNewFiles = newlySelected.filter((file) => !existingKeys.has(attachmentKey(file)));
        const availableSlots = Math.max(0, 10 - currentRemainingAttachmentCount() - selectedAttachmentFiles.length);

        if (uniqueNewFiles.length > availableSlots) {
            window.alert(`添付ファイルは、現在の添付を含めて合計10件までです。追加できるのはあと${availableSlots}件です。`);
        }

        selectedAttachmentFiles.push(...uniqueNewFiles.slice(0, availableSlots));
        syncAttachmentInput();
        renderSelectedAttachments();
    });

    selectedAttachmentArea?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-board-remove-selected-attachment]');
        if (!button) return;
        selectedAttachmentFiles.splice(Number(button.dataset.boardRemoveSelectedAttachment), 1);
        syncAttachmentInput();
        renderSelectedAttachments();
    });


    currentAttachmentArea?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-board-toggle-current-attachment]');
        if (!button) return;

        const item = button.closest('.board-current-attachment-item');
        const checkbox = item?.querySelector('input[name="remove_attachment_ids[]"]');
        if (!item || !checkbox) return;

        checkbox.checked = !checkbox.checked;
        item.classList.toggle('remove-marked', checkbox.checked);
        button.textContent = checkbox.checked ? '削除取消' : '削除';
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    });

    currentAttachmentArea?.addEventListener('change', (event) => {
        if (!event.target.matches('input[name="remove_attachment_ids[]"]')) return;
        const total = currentRemainingAttachmentCount() + selectedAttachmentFiles.length;
        if (total > 10) {
            event.target.checked = true;
            window.alert('添付ファイルは合計10件までです。新しいファイルを追加する場合は、既存ファイルを削除対象にしてください。');
        }
    });

    document.querySelectorAll('[data-board-close]').forEach((button) => button.addEventListener('click', () => closeModal(button.closest('.board-modal'))));
    document.addEventListener('keydown', (event) => { if(event.key === 'Escape') document.querySelectorAll('.board-modal.open').forEach(closeModal); });

    let activeOperationMenu = null;
    let activeOperationWrap = null;

    function closeOperationMenu() {
        if (!activeOperationMenu || !activeOperationWrap) return;
        activeOperationMenu.classList.remove('board-operation-menu-floating');
        activeOperationMenu.removeAttribute('style');
        activeOperationWrap.appendChild(activeOperationMenu);
        activeOperationWrap.classList.remove('open');
        activeOperationWrap.querySelector('.board-operation-button')?.setAttribute('aria-expanded', 'false');
        activeOperationMenu = null;
        activeOperationWrap = null;
    }

    function placeOperationMenu(button, menu) {
        const buttonRect = button.getBoundingClientRect();
        const menuWidth = Math.max(menu.offsetWidth || 170, 170);
        const menuHeight = menu.offsetHeight || 220;
        const viewportGap = 8;

        let left = buttonRect.right - menuWidth;
        left = Math.max(viewportGap, Math.min(left, window.innerWidth - menuWidth - viewportGap));

        let top = buttonRect.bottom + 6;
        if (top + menuHeight > window.innerHeight - viewportGap) {
            top = Math.max(viewportGap, buttonRect.top - menuHeight - 6);
        }

        menu.style.left = `${left}px`;
        menu.style.top = `${top}px`;
        menu.style.minWidth = `${menuWidth}px`;
    }

    document.querySelectorAll('.board-operation-button').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const wrap = button.closest('.board-operation-wrap');
            const menu = wrap?.querySelector('.board-operation-menu');
            if (!wrap || !menu) return;

            if (activeOperationWrap === wrap) {
                closeOperationMenu();
                return;
            }

            closeOperationMenu();
            activeOperationWrap = wrap;
            activeOperationMenu = menu;
            wrap.classList.add('open');
            button.setAttribute('aria-expanded', 'true');
            document.body.appendChild(menu);
            menu.classList.add('board-operation-menu-floating');
            placeOperationMenu(button, menu);
        });
    });

    document.addEventListener('click', closeOperationMenu);
    window.addEventListener('resize', closeOperationMenu);
    document.querySelector('.board-table-scroll')?.addEventListener('scroll', closeOperationMenu);
    document.querySelectorAll('.board-operation-menu').forEach((menu) => menu.addEventListener('click', (event) => event.stopPropagation()));

    const shareRadios = [...document.querySelectorAll('input[name="share_type"]')];
    const targetRadios = [...document.querySelectorAll('input[name="target_type"]')];
    const targetSelects = [...document.querySelectorAll('[data-target-select]')];
    function updateTargetSelect(type, selectedIds = []) {
        targetSelects.forEach((select) => {
            const active = select.dataset.targetSelect === type;
            select.classList.toggle('active', active);
            select.disabled = !active;
            [...select.options].forEach((option) => option.selected = active && selectedIds.map(String).includes(option.value));
        });
    }
    targetRadios.forEach((radio) => radio.addEventListener('change', () => updateTargetSelect(radio.value)));

    function updateShareType(type) {
        const individual = type === 'individual';
        document.getElementById('boardIndividualTargetOptions')?.closest('.board-form-full')?.classList.toggle('board-target-disabled', !individual);
        targetRadios.forEach((radio) => { radio.disabled = !individual; });
        if (individual) {
            const selected = targetRadios.find((radio) => radio.checked) || targetRadios[0];
            if (selected) { selected.checked = true; updateTargetSelect(selected.value); }
        } else {
            targetSelects.forEach((select) => { select.disabled = true; select.classList.remove('active'); });
        }
    }
    shareRadios.forEach((radio) => radio.addEventListener('change', () => updateShareType(radio.value)));

    function resetForm() {
        form.reset();
        form.action = window.boardRoutes.store;
        document.getElementById('boardFormMethod').value = 'POST';
        document.getElementById('boardFormTitle').textContent = '新規ご連絡登録';
        document.getElementById('boardCurrentAttachments').innerHTML = '';
        resetSelectedAttachments();
        document.getElementById('boardIsListed').checked = true;
        document.getElementById('boardIsNotified').checked = false;
        document.getElementById('boardNotifyAt').value = '';
        document.getElementById('boardNotifyAt').disabled = true;
        document.getElementById('boardRequiresConfirmation').checked = false;
        shareRadios.find((radio) => radio.value === 'all').checked = true;
        if (targetRadios[0]) targetRadios[0].checked = true;
        updateShareType('all');
    }

    document.querySelector('[data-board-open="create"]')?.addEventListener('click', () => { resetForm(); openModal(formModal); });

    function fillEdit(data) {
        resetForm();
        document.getElementById('boardFormTitle').textContent = `教室からのご連絡編集（ID: ${data.id}）`;
        form.action = window.boardRoutes.update.replace('__ID__', data.id);
        document.getElementById('boardFormMethod').value = 'PUT';
        document.getElementById('boardCategory').value = data.category;
        document.getElementById('boardStatus').value = data.status;
        document.getElementById('boardTitle').value = data.title;
        document.getElementById('boardBody').value = data.body;
        document.getElementById('boardPublishFrom').value = data.publish_from || '';
        document.getElementById('boardPublishTo').value = data.publish_to || '';
        document.getElementById('boardImportant').checked = !!data.is_important;
        document.getElementById('boardPinned').checked = !!data.is_pinned;
        document.getElementById('boardIsListed').checked = !!data.is_listed;
        document.getElementById('boardIsNotified').checked = !!data.is_notified;
        document.getElementById('boardNotifyAt').value = data.notify_at || '';
        document.getElementById('boardNotifyAt').disabled = !data.is_notified;
        document.getElementById('boardRequiresConfirmation').checked = !!data.requires_confirmation;
        const shareRadio = shareRadios.find((item) => item.value === (data.share_type || 'all'));
        if (shareRadio) shareRadio.checked = true;
        const radio = targetRadios.find((item) => item.value === data.target_type);
        if (radio) radio.checked = true;
        updateShareType(data.share_type || 'all');
        if ((data.share_type || 'all') === 'individual') updateTargetSelect(data.target_type, data.target_ids || []);
        const attachmentArea = document.getElementById('boardCurrentAttachments');
        const attachments = Array.isArray(data.attachments) ? data.attachments : [];
        attachmentArea.innerHTML = attachments.length
            ? `<div class="board-current-attachments-title">現在の添付ファイル（${attachments.length}件）</div>${attachments.map((item) => `<div class="board-current-attachment-item"><span class="board-current-attachment-name"><a href="${escapeHtml(item.url)}" target="_blank" rel="noopener">${escapeHtml(item.name)}</a></span><span class="board-current-attachment-status">登録済み</span><button type="button" data-board-toggle-current-attachment>削除</button><input type="checkbox" name="remove_attachment_ids[]" value="${escapeHtml(item.id)}" hidden></div>`).join('')}`
            : '';
        openModal(formModal);
    }

    function renderAttachments(attachments) {
        const items = Array.isArray(attachments) ? attachments : [];
        if (!items.length) return 'なし';
        return `<div class="board-detail-attachments">${items.map((item) => `<a href="${escapeHtml(item.url)}" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i>${escapeHtml(item.name)}</a>`).join('')}</div>`;
    }

    function showDetail(data) {
        const period = `${data.publish_from ? data.publish_from.replace('T',' ') : '未設定'} ～ ${data.publish_to ? data.publish_to.replace('T',' ') : '期限なし'}`;
        document.getElementById('boardDetailContent').innerHTML = `<dl class="board-detail-grid">
            <dt>ID</dt><dd>${escapeHtml(data.id)}</dd><dt>タイトル</dt><dd>${escapeHtml(data.title)}</dd>
            <dt>カテゴリ</dt><dd>${escapeHtml(data.category)}</dd><dt>共有区分</dt><dd>${data.share_type === 'individual' ? '個別共有' : '全体共有'}</dd>
            <dt>表示方法</dt><dd>${data.is_listed ? '掲示板に掲載' : ''}${data.is_listed && data.is_notified ? ' ／ ' : ''}${data.is_notified ? '通知する' : ''}</dd><dt>共有対象</dt><dd>${escapeHtml(data.target_label)}（${escapeHtml(data.target_count)}名）</dd>
            <dt>公開期間</dt><dd>${escapeHtml(period)}</dd><dt>通知日時</dt><dd>${data.is_notified ? escapeHtml(data.notify_at ? data.notify_at.replace('T',' ') : '即時') : '通知なし'}${data.notified_at ? '（通知済）' : ''}</dd>
            <dt>状態</dt><dd>${escapeHtml(statusLabel(data.status))}</dd><dt>重要／ピン</dt><dd>${data.is_important ? '重要' : '-'} ／ ${data.is_pinned ? 'ピン留め' : '-'}</dd>
            <dt>閲覧</dt><dd>${escapeHtml(data.reads_count)}件（既読率 ${escapeHtml(data.read_rate)}%）</dd>
            <dt>確認状況</dt><dd>${data.requires_confirmation ? `${escapeHtml(data.confirmations_count)}/${escapeHtml(data.target_count)}名（${escapeHtml(data.confirmation_rate)}%）` : '確認不要'}</dd>
            <dt>本文</dt><dd class="board-detail-body">${escapeHtml(data.body)}</dd>
            <dt>添付</dt><dd>${renderAttachments(data.attachments)}</dd>
            <dt>作成日時</dt><dd>${escapeHtml(data.created_at)}</dd><dt>更新日時</dt><dd>${escapeHtml(data.updated_at)}</dd></dl>`;
        openModal(detailModal);
    }

    function showPreview(data) {
        const publishDate = data.publish_from ? data.publish_from.slice(0,10).replaceAll('-','/') : '';
        const body = escapeHtml(data.body).replace(/\n/g, '<br>');
        const attachmentItems = Array.isArray(data.attachments) ? data.attachments : [];
        const attachment = attachmentItems.length ? `<div class="board-preview-attachment"><div class="board-preview-attachment-title"><i class="fas fa-paperclip"></i> 添付ファイル（${attachmentItems.length}件）</div>${attachmentItems.map((item) => `<a href="${escapeHtml(item.url)}" target="_blank" rel="noopener">${escapeHtml(item.name)}</a>`).join('')}</div>` : '';
        const confirmation = data.requires_confirmation ? `<div class="board-preview-confirmation"><button type="button" data-board-preview-confirm><i class="fas fa-check"></i> 確認しました</button><span>確認が必要なご連絡です。</span></div>` : '';
        document.getElementById('boardPreviewContent').innerHTML = `<div class="board-preview-section-title">${escapeHtml(data.preview_heading)}</div><article class="board-preview-card">
            <div class="board-preview-meta"><span class="board-share-badge board-share-${escapeHtml(data.share_type || 'all')}">${data.share_type === 'individual' ? '個別共有' : '全体共有'}</span>${data.is_notified ? '<span class="board-delivery-badge board-delivery-notified">通知</span>' : ''}${data.is_pinned ? '<i class="fas fa-thumbtack board-preview-pinned"></i>' : ''}${data.is_important ? '<span class="board-preview-important">重要</span>' : ''}<span>${escapeHtml(data.category)}</span><span>${escapeHtml(publishDate)}</span></div>
            <h3 class="board-preview-title">${escapeHtml(data.title)}</h3><div class="board-preview-body">${body}</div>${attachment}${confirmation}
            <div class="board-preview-period">公開期間：${escapeHtml(data.publish_from ? data.publish_from.replace('T',' ') : '未設定')} ～ ${escapeHtml(data.publish_to ? data.publish_to.replace('T',' ') : '期限なし')}${data.is_notified ? `<br>通知日時：${escapeHtml(data.notify_at ? data.notify_at.replace('T',' ') : '即時')}` : ''}</div></article>`;
        document.querySelector('[data-board-preview-confirm]')?.addEventListener('click', (event) => {
            event.currentTarget.classList.add('confirmed');
            event.currentTarget.innerHTML = '<i class="fas fa-check-circle"></i> 確認済み';
            event.currentTarget.disabled = true;
        });
        openModal(previewModal);
    }


    let audienceStatusData = null;
    let activeAudienceTab = 'read';

    function renderAudienceStatusList(type) {
        const content = document.getElementById('boardAudienceStatusContent');
        if (!content || !audienceStatusData) return;

        activeAudienceTab = type;
        document.querySelectorAll('[data-audience-tab]').forEach((button) => {
            button.classList.toggle('active', button.dataset.audienceTab === type);
        });

        const items = Array.isArray(audienceStatusData[type]) ? audienceStatusData[type] : [];
        const dateKey = type === 'confirmed' ? 'confirmed_at' : (type === 'read' ? 'read_at' : null);
        const dateLabel = type === 'confirmed' ? '確認日時' : '既読日時';

        if (!items.length) {
            content.innerHTML = '<div class="board-audience-empty">該当する生徒はいません。</div>';
            return;
        }

        content.innerHTML = `<div class="board-audience-table-wrap"><table class="board-audience-table"><thead><tr><th>生徒コード</th><th>生徒名</th>${dateKey ? `<th>${dateLabel}</th>` : ''}</tr></thead><tbody>${items.map((item) => `<tr><td>${escapeHtml(item.student_code)}</td><td>${escapeHtml(item.name)}</td>${dateKey ? `<td>${escapeHtml(item[dateKey] || '-')}</td>` : ''}</tr>`).join('')}</tbody></table></div>`;
    }

    async function showAudienceStatus(boardId) {
        const summary = document.getElementById('boardAudienceStatusSummary');
        const content = document.getElementById('boardAudienceStatusContent');
        if (!summary || !content) return;

        summary.innerHTML = '<div class="board-audience-loading"><i class="fas fa-spinner fa-spin"></i> 読み込み中...</div>';
        content.innerHTML = '';
        openModal(audienceStatusModal);

        try {
            const url = window.boardRoutes.audienceStatus.replace('__ID__', boardId);
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('状況を取得できませんでした。');

            audienceStatusData = await response.json();
            const counts = audienceStatusData.counts || {};
            summary.innerHTML = `<div class="board-audience-title">${escapeHtml(audienceStatusData.post?.title || '')}</div><div class="board-audience-metrics"><span>対象 <strong>${escapeHtml(counts.total || 0)}</strong>名</span><span>既読 <strong>${escapeHtml(counts.read || 0)}</strong>名</span><span>未読 <strong>${escapeHtml(counts.unread || 0)}</strong>名</span><span>確認 <strong>${escapeHtml(counts.confirmed || 0)}</strong>名</span><span>未確認 <strong>${escapeHtml(counts.unconfirmed || 0)}</strong>名</span></div>${audienceStatusData.post?.requires_confirmation ? '' : '<div class="board-audience-note">このご連絡は「確認しました」ボタンを表示しない設定です。</div>'}`;

            ['read', 'unread', 'confirmed', 'unconfirmed'].forEach((key) => {
                const countNode = document.querySelector(`[data-audience-count="${key}"]`);
                if (countNode) countNode.textContent = counts[key] ?? 0;
            });
            renderAudienceStatusList('read');
        } catch (error) {
            summary.innerHTML = `<div class="board-audience-error">${escapeHtml(error.message)}</div>`;
        }
    }

    document.querySelectorAll('[data-audience-tab]').forEach((button) => {
        button.addEventListener('click', () => renderAudienceStatusList(button.dataset.audienceTab));
    });

    const notifyCheckbox = document.getElementById('boardIsNotified');
    const notifyAtInput = document.getElementById('boardNotifyAt');
    function syncNotificationFields() {
        if (!notifyAtInput || !notifyCheckbox) return;
        notifyAtInput.disabled = !notifyCheckbox.checked;
        if (!notifyCheckbox.checked) notifyAtInput.value = '';
    }
    notifyCheckbox?.addEventListener('change', syncNotificationFields);
    syncNotificationFields();

    form?.addEventListener('submit', (event) => {
        if (!document.getElementById('boardIsListed').checked && !document.getElementById('boardIsNotified').checked) {
            event.preventDefault();
            window.alert('「掲示板に掲載」または「通知する」のどちらか1つ以上を選択してください。');
        }
    });

    document.querySelectorAll('[data-board-action]').forEach((button) => button.addEventListener('click', () => {
        button.closest('.board-operation-wrap')?.classList.remove('open');

        if (button.dataset.boardAction === 'audience-status') {
            showAudienceStatus(button.dataset.boardId);
            return;
        }

        const data = JSON.parse(button.dataset.board);
        if(button.dataset.boardAction === 'edit') fillEdit(data);
        if(button.dataset.boardAction === 'detail') showDetail(data);
        if(button.dataset.boardAction === 'preview') showPreview(data);
    }));

    if (document.querySelector('.board-alert-error')) { resetForm(); openModal(formModal); }

    let activeFilterMenu = null;
    let activeFilterTrigger = null;
    let activeFilterHost = null;

    function closeColumnFilterMenu() {
        if (!activeFilterMenu || !activeFilterHost) return;
        activeFilterMenu.classList.remove('board-column-filter-menu-floating');
        activeFilterMenu.removeAttribute('style');
        activeFilterHost.appendChild(activeFilterMenu);
        activeFilterTrigger?.setAttribute('aria-expanded', 'false');
        activeFilterMenu = null;
        activeFilterTrigger = null;
        activeFilterHost = null;
    }

    function placeColumnFilterMenu(trigger, menu) {
        const rect = trigger.getBoundingClientRect();
        const gap = 8;
        const width = Math.max(menu.offsetWidth || 240, 240);
        const height = menu.offsetHeight || 260;
        let left = Math.min(rect.left, window.innerWidth - width - gap);
        left = Math.max(gap, left);
        let top = rect.bottom + 6;
        if (top + height > window.innerHeight - gap) top = Math.max(gap, rect.top - height - 6);
        menu.style.left = `${left}px`;
        menu.style.top = `${top}px`;
        menu.style.width = `${width}px`;
    }

    document.querySelectorAll('[data-board-filter-trigger]').forEach((trigger) => {
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const host = trigger.closest('th');
            const menu = host?.querySelector('[data-board-filter-menu]');
            if (!host || !menu) return;
            if (activeFilterMenu === menu) { closeColumnFilterMenu(); return; }
            closeColumnFilterMenu();
            activeFilterMenu = menu;
            activeFilterTrigger = trigger;
            activeFilterHost = host;
            document.body.appendChild(menu);
            menu.classList.add('board-column-filter-menu-floating');
            trigger.setAttribute('aria-expanded', 'true');
            placeColumnFilterMenu(trigger, menu);
        });
    });

    document.querySelectorAll('[data-board-filter-menu]').forEach((menu) => {
        menu.addEventListener('click', (event) => event.stopPropagation());
        menu.querySelector('[data-board-filter-clear]')?.addEventListener('click', () => {
            menu.querySelectorAll('input').forEach((input) => {
                if (input.type === 'checkbox' || input.type === 'radio') input.checked = false;
                else input.value = '';
            });
            document.getElementById('boardListFilterForm')?.requestSubmit();
        });
    });

    document.addEventListener('click', closeColumnFilterMenu);
    window.addEventListener('resize', closeColumnFilterMenu);
    document.querySelector('.board-table-scroll')?.addEventListener('scroll', closeColumnFilterMenu);

});
