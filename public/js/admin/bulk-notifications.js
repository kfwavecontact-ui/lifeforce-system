(() => {
    const q = (selector, root = document) => root.querySelector(selector);
    const qa = (selector, root = document) => [...root.querySelectorAll(selector)];
    const formModal = q('#bulkFormModal');
    const infoModal = q('#bulkInfoModal');
    const form = q('#bulkForm');
    let selectedFiles = [];

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const formatBytes = (bytes) => {
        const size = Number(bytes || 0);
        if (size < 1024) return `${size}B`;
        if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)}KB`;
        return `${(size / 1024 / 1024).toFixed(2)}MB`;
    };

    const yesNo = (value) => value ? 'あり' : 'なし';
    const open = (modal) => modal?.classList.add('open');
    const close = (modal) => modal?.classList.remove('open');

    qa('[data-bulk-close]').forEach((button) => {
        button.addEventListener('click', () => close(button.closest('.bulk-modal')));
    });

    function syncTargetSelect(type, ids = []) {
        const selectedIds = ids.map(String);
        qa('[data-target]').forEach((select) => {
            const isActive = select.dataset.target === type;
            select.classList.toggle('active', isActive);
            select.disabled = !isActive;
            qa('option', select).forEach((option) => {
                option.selected = selectedIds.includes(option.value);
            });
        });
    }

    qa('input[name=target_type]').forEach((radio) => {
        radio.addEventListener('change', () => syncTargetSelect(radio.value, []));
    });
    syncTargetSelect('all');

    function syncFileInput() {
        const transfer = new DataTransfer();
        selectedFiles.forEach((file) => transfer.items.add(file));
        q('#bulkAttachments').files = transfer.files;
    }

    function renderSelectedFiles() {
        const container = q('#bulkSelectedAttachments');
        if (!container) return;

        if (!selectedFiles.length) {
            container.innerHTML = '';
            syncFileInput();
            return;
        }

        container.innerHTML = `
            <section class="attachment-section">
                <h3 class="attachment-section-title">今回追加する添付ファイル（${selectedFiles.length}件）</h3>
                <div class="attachment-card-list">
                    ${selectedFiles.map((file, index) => `
                        <div class="attachment-card">
                            <span class="attachment-card-name">${escapeHtml(file.name)}</span>
                            <span class="attachment-card-meta">${formatBytes(file.size)}</span>
                            <button type="button" data-remove-file="${index}">削除</button>
                        </div>
                    `).join('')}
                </div>
            </section>`;

        qa('[data-remove-file]', container).forEach((button) => {
            button.addEventListener('click', () => {
                selectedFiles.splice(Number(button.dataset.removeFile), 1);
                renderSelectedFiles();
            });
        });
        syncFileInput();
    }

    q('#bulkAttachments')?.addEventListener('change', (event) => {
        [...event.target.files].forEach((file) => {
            const exists = selectedFiles.some((current) =>
                current.name === file.name &&
                current.size === file.size &&
                current.lastModified === file.lastModified
            );
            if (!exists) selectedFiles.push(file);
        });
        renderSelectedFiles();
    });

    function renderCurrentAttachments(attachments = []) {
        const container = q('#bulkCurrentAttachments');
        if (!container) return;
        if (!attachments.length) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = `
            <section class="attachment-section">
                <h3 class="attachment-section-title">現在の添付ファイル（${attachments.length}件）</h3>
                <div class="attachment-card-list">
                    ${attachments.map((attachment) => `
                        <div class="attachment-card" data-current-attachment="${attachment.id}">
                            <span class="attachment-card-name"><a href="${escapeHtml(attachment.url)}" target="_blank" rel="noopener">${escapeHtml(attachment.name)}</a></span>
                            <span class="attachment-card-status">登録済み</span>
                            <input type="checkbox" hidden name="delete_attachment_ids[]" value="${attachment.id}">
                            <button type="button" data-toggle-delete>削除</button>
                        </div>
                    `).join('')}
                </div>
            </section>`;

        qa('[data-toggle-delete]', container).forEach((button) => {
            button.addEventListener('click', () => {
                const card = button.closest('[data-current-attachment]');
                const checkbox = q('input[type=checkbox]', card);
                checkbox.checked = !checkbox.checked;
                card.classList.toggle('pending-delete', checkbox.checked);
                button.textContent = checkbox.checked ? '削除取消' : '削除';
                q('.attachment-card-status', card).textContent = checkbox.checked ? '削除予定' : '登録済み';
            });
        });
    }

    function resetForm() {
        form.reset();
        form.action = window.bulkRoutes.store;
        q('#bulkMethod').value = 'POST';
        q('#bulkFormTitle').textContent = '新規一斉通知';
        selectedFiles = [];
        renderSelectedFiles();
        renderCurrentAttachments([]);
        syncTargetSelect('all');
        const allTarget = q('input[name=target_type][value="all"]');
        if (allTarget) allTarget.checked = true;
    }

    q('[data-bulk-open]')?.addEventListener('click', () => {
        resetForm();
        open(formModal);
    });

    function fillForm(payload) {
        resetForm();
        form.action = window.bulkRoutes.update.replace('__ID__', payload.id);
        q('#bulkMethod').value = 'PUT';
        q('#bulkFormTitle').textContent = `一斉通知編集（ID: ${payload.id}）`;
        q('#bulkMaster').value = payload.master_id || '';
        q('#bulkTitle').value = payload.title || '';
        q('#bulkBody').value = payload.body || '';
        q('#bulkStatus').value = payload.status === 'scheduled' ? 'scheduled' : 'draft';
        q('#bulkScheduledAt').value = payload.scheduled_at || '';
        q('#bulkExpiresAt').value = payload.expires_at || '';
        q('#bulkImportant').checked = Boolean(payload.important);
        if (q('#bulkModalFlag')) q('#bulkModalFlag').checked = Boolean(payload.modal);
        if (q('#bulkConfirmation')) q('#bulkConfirmation').checked = Boolean(payload.confirmation);
        q('#bulkBoard').value = payload.board_post_id || '';
        q('#bulkLinkUrl').value = payload.link_url || '';

        const targetType = payload.target_type || 'all';
        const targetRadio = q(`input[name=target_type][value="${targetType}"]`);
        if (targetRadio) targetRadio.checked = true;
        syncTargetSelect(targetType, payload.target_ids || []);
        qa('input[name="recipient_types[]"]').forEach((checkbox) => {
            checkbox.checked = (payload.recipient_types || []).includes(checkbox.value);
        });
        renderCurrentAttachments(payload.attachments || []);
        open(formModal);
    }

    qa('[data-bulk-edit]').forEach((button) => {
        button.addEventListener('click', () => fillForm(JSON.parse(button.dataset.bulkEdit)));
    });

    function attachmentLinks(attachments = []) {
        if (!attachments.length) return '<span>-</span>';
        return `<div class="bulk-detail-attachments">${attachments.map((attachment) => `
            <a href="${escapeHtml(attachment.url)}" target="_blank" rel="noopener">
                <i class="fas fa-paperclip"></i>${escapeHtml(attachment.name)}
            </a>`).join('')}</div>`;
    }

    function renderDetail(payload) {
        return `
            <table class="bulk-detail-table">
                <tbody>
                    <tr><th>ID</th><td>${escapeHtml(payload.id)}</td></tr>
                    <tr><th>通知種類</th><td>${escapeHtml(payload.master_name || '-')}</td></tr>
                    <tr><th>タイトル</th><td>${escapeHtml(payload.title)}</td></tr>
                    <tr><th>状態</th><td>${escapeHtml(payload.status_label || '-')}</td></tr>
                    <tr><th>送信対象</th><td>${escapeHtml(payload.target_label || '-')}</td></tr>
                    <tr><th>受信者</th><td>${escapeHtml(payload.recipient_label || '-')}</td></tr>
                    <tr><th>送信予定日時</th><td>${escapeHtml(payload.scheduled_label || '-')}</td></tr>
                    <tr><th>送信日時</th><td>${escapeHtml(payload.sent_label || '-')}</td></tr>
                    <tr><th>表示終了日時</th><td>${escapeHtml(payload.expires_label || '-')}</td></tr>
                    <tr><th>重要通知</th><td>${yesNo(payload.important)}</td></tr>
                    <tr><th>既読</th><td>${escapeHtml(payload.read_count || 0)}/${escapeHtml(payload.recipients_count || 0)}</td></tr>
                    <tr><th>リンク先掲示板</th><td>${payload.board_post_id ? `#${escapeHtml(payload.board_post_id)}` : '-'}</td></tr>
                    <tr><th>任意URL</th><td>${payload.link_url ? `<a href="${escapeHtml(payload.link_url)}" target="_blank" rel="noopener">${escapeHtml(payload.link_url)}</a>` : '-'}</td></tr>
                    <tr><th>本文</th><td><div class="bulk-detail-body">${escapeHtml(payload.body)}</div></td></tr>
                    <tr><th>添付</th><td>${attachmentLinks(payload.attachments)}</td></tr>
                    <tr><th>作成日時</th><td>${escapeHtml(payload.created_label || '-')}</td></tr>
                    <tr><th>更新日時</th><td>${escapeHtml(payload.updated_label || '-')}</td></tr>
                </tbody>
            </table>`;
    }

    function boardExcerpt(body, maxLength = 80) {
        const normalized = String(body || '').replace(/\s+/g, ' ').trim();
        return normalized.length > maxLength ? `${normalized.slice(0, maxLength)}…` : normalized;
    }

    function renderRelatedBoardCard(board) {
        if (!board) return '';
        return `
            <div class="portal-preview-divider"></div>
            <section class="portal-related-board">
                <h3 class="portal-related-board-heading">関連する教室のお知らせ</h3>
                <article class="portal-related-board-card">
                    <div class="portal-related-board-meta">
                        ${board.is_pinned ? '<i class="fas fa-thumbtack" aria-hidden="true"></i>' : ''}
                        <span class="portal-related-board-category">${escapeHtml(board.category || 'お知らせ')}</span>
                        ${board.is_important ? '<span class="badge important">重要</span>' : ''}
                    </div>
                    <h4 class="portal-related-board-title">${escapeHtml(board.title || '')}</h4>
                    <p class="portal-related-board-excerpt">${escapeHtml(boardExcerpt(board.body))}</p>
                    <div class="portal-related-board-actions">
                        <button type="button" class="portal-preview-action secondary" data-related-board-detail>掲示板を見る <i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                    </div>
                </article>
            </section>`;
    }

    function renderBoardDetail(board) {
        if (!board) return '<p>関連する掲示板が見つかりません。</p>';
        return `
            <table class="bulk-detail-table">
                <tbody>
                    <tr><th>ID</th><td>${escapeHtml(board.id)}</td></tr>
                    <tr><th>タイトル</th><td>${escapeHtml(board.title || '-')}</td></tr>
                    <tr><th>カテゴリ</th><td>${escapeHtml(board.category || '-')}</td></tr>
                    <tr><th>重要</th><td>${board.is_important ? '重要' : '-'}</td></tr>
                    <tr><th>ピン留め</th><td>${board.is_pinned ? 'あり' : 'なし'}</td></tr>
                    <tr><th>本文</th><td><div class="bulk-detail-body">${escapeHtml(board.body || '')}</div></td></tr>
                    <tr><th>公開期間</th><td>${escapeHtml(board.publish_from || '未設定')} ～ ${escapeHtml(board.publish_to || '期限なし')}</td></tr>
                </tbody>
            </table>`;
    }

    function renderPreview(payload) {
        const attachments = payload.attachments || [];
        return `
            <div class="portal-preview">
                <div class="portal-preview-kicker">あなたへのお知らせ</div>
                <article class="portal-preview-card">
                    <div class="portal-preview-category">${escapeHtml(payload.master_name || '一斉通知')}</div>
                    ${payload.important ? '<div class="portal-preview-important"><span class="badge important">重要</span></div>' : ''}
                    <h2 class="portal-preview-title">${escapeHtml(payload.title)}</h2>
                    <div class="portal-preview-body">${escapeHtml(payload.body)}</div>
                    ${attachments.length ? `
                        <div class="portal-preview-divider"></div>
                        <div class="portal-preview-attachments">
                            <h3><i class="fas fa-paperclip"></i> 添付ファイル</h3>
                            ${attachments.map((attachment) => `<a href="${escapeHtml(attachment.url)}" target="_blank" rel="noopener">${escapeHtml(attachment.name)}</a>`).join('')}
                        </div>` : ''}
                    ${renderRelatedBoardCard(payload.board)}
                    ${payload.link_url ? `
                        <div class="portal-preview-actions">
                            <a class="portal-preview-action secondary" href="${escapeHtml(payload.link_url)}" target="_blank" rel="noopener">リンクを開く</a>
                        </div>` : ''}
                    <div class="portal-preview-meta">送信予定：${escapeHtml(payload.scheduled_label || '-')}　／　表示終了：${escapeHtml(payload.expires_label || '-')}</div>
                </article>
            </div>`;
    }

    function showInfo(payload, preview = false) {
        q('#bulkInfoTitle').textContent = preview ? '一斉通知 表示イメージ' : '一斉通知詳細';
        q('#bulkInfoContent').innerHTML = preview ? renderPreview(payload) : renderDetail(payload);
        infoModal.dataset.currentPayload = JSON.stringify(payload);
        open(infoModal);
    }

    q('#bulkInfoContent')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-related-board-detail]');
        if (!button) return;
        const payload = JSON.parse(infoModal.dataset.currentPayload || '{}');
        q('#bulkInfoTitle').textContent = '掲示板詳細';
        q('#bulkInfoContent').innerHTML = renderBoardDetail(payload.board);
    });

    qa('[data-bulk-detail]').forEach((button) => {
        button.addEventListener('click', () => showInfo(JSON.parse(button.dataset.bulkDetail)));
    });
    qa('[data-bulk-preview]').forEach((button) => {
        button.addEventListener('click', () => showInfo(JSON.parse(button.dataset.bulkPreview), true));
    });

    qa('[data-bulk-menu]').forEach((button) => {
        const menu = button.closest('.bulk-operation-wrap')?.querySelector('.bulk-menu');
        if (!menu) return;
        menu.addEventListener('click', (event) => event.stopPropagation());
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            qa('.bulk-menu.open').forEach((openedMenu) => openedMenu.classList.remove('open'));
            if (menu.parentElement !== document.body) document.body.appendChild(menu);
            const rect = button.getBoundingClientRect();
            const menuWidth = 150;
            menu.style.left = `${Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8))}px`;
            menu.classList.add('open');
            const menuHeight = menu.offsetHeight || 230;
            const preferredTop = rect.bottom + 5;
            menu.style.top = `${preferredTop + menuHeight > window.innerHeight ? Math.max(8, rect.top - menuHeight - 5) : preferredTop}px`;
        });
    });
    document.addEventListener('click', () => qa('.bulk-menu.open').forEach((menu) => menu.classList.remove('open')));

    let activeFilterMenu = null;
    let activeFilterTrigger = null;
    let activeFilterHost = null;

    function closeFilter() {
        if (!activeFilterMenu || !activeFilterHost) return;
        activeFilterMenu.classList.remove('bulk-column-filter-menu-floating');
        activeFilterMenu.removeAttribute('style');
        activeFilterHost.appendChild(activeFilterMenu);
        activeFilterTrigger?.setAttribute('aria-expanded', 'false');
        activeFilterMenu = activeFilterTrigger = activeFilterHost = null;
    }

    function placeFilter(trigger, menu) {
        const rect = trigger.getBoundingClientRect();
        const gap = 8;
        const width = Math.max(menu.offsetWidth || 240, 240);
        const height = menu.offsetHeight || 260;
        let left = Math.max(gap, Math.min(rect.left, window.innerWidth - width - gap));
        let top = rect.bottom + 6;
        if (top + height > window.innerHeight - gap) top = Math.max(gap, rect.top - height - 6);
        Object.assign(menu.style, { left: `${left}px`, top: `${top}px`, width: `${width}px` });
    }

    qa('[data-bulk-filter-trigger]').forEach((trigger) => {
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const host = trigger.closest('th');
            const menu = host?.querySelector('[data-bulk-filter-menu]');
            if (!host || !menu) return;
            if (activeFilterMenu === menu) {
                closeFilter();
                return;
            }
            closeFilter();
            activeFilterMenu = menu;
            activeFilterTrigger = trigger;
            activeFilterHost = host;
            document.body.appendChild(menu);
            menu.classList.add('bulk-column-filter-menu-floating');
            trigger.setAttribute('aria-expanded', 'true');
            placeFilter(trigger, menu);
        });
    });

    qa('[data-bulk-filter-menu]').forEach((menu) => {
        menu.addEventListener('click', (event) => event.stopPropagation());
        menu.querySelector('[data-bulk-filter-clear]')?.addEventListener('click', () => {
            menu.querySelectorAll('input').forEach((input) => {
                if (input.type === 'checkbox' || input.type === 'radio') input.checked = false;
                else input.value = '';
            });
            q('#bulkListFilterForm')?.requestSubmit();
        });
    });

    document.addEventListener('click', closeFilter);
    window.addEventListener('resize', closeFilter);
    q('.bulk-table-scroll')?.addEventListener('scroll', closeFilter);
})();
