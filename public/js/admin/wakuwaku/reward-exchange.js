/**
 * 商品交換所3画面共通UI。
 * 関連画面: 商品一覧 / 申請情報 / 交換履歴
 * 役割: 生徒検索、商品詳細、Ajax交換申請、申請処理、詳細タイムライン、通知表示。
 */
(() => {
    'use strict';

    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    })[char]);
    const formatNumber = (value) => Number(value || 0).toLocaleString('ja-JP');

    const openLayer = (element) => {
        if (!element) return;
        element.hidden = false;
        document.body.classList.add('rex-layer-open');
    };
    const closeLayer = (element) => {
        if (!element) return;
        element.hidden = true;
        if (!qs('.rex-modal:not([hidden]), .rex-drawer:not([hidden])')) {
            document.body.classList.remove('rex-layer-open');
        }
    };

    const showToast = (message, type = 'success') => {
        let region = qs('#rex-toast-region');
        if (!region) {
            region = document.createElement('div');
            region.id = 'rex-toast-region';
            region.className = 'rex-toast-region';
            region.setAttribute('aria-live', 'polite');
            document.body.appendChild(region);
        }
        const toast = document.createElement('div');
        toast.className = `rex-toast is-${type}`;
        toast.innerHTML = `<span>${type === 'success' ? '✓' : '!'}</span><p>${escapeHtml(message)}</p>`;
        region.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        window.setTimeout(() => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => toast.remove(), 220);
        }, 3800);
    };

    const parseJsonResponse = async (response) => {
        const payload = await response.json().catch(() => ({}));
        if (response.ok) return payload;
        const validation = payload.errors ? Object.values(payload.errors).flat() : [];
        const message = validation[0] || payload.message || '処理に失敗しました。';
        const error = new Error(message);
        error.status = response.status;
        error.payload = payload;
        throw error;
    };

    // 生徒検索
    const studentSearch = qs('#rex-student-search');
    const studentResults = qs('#rex-student-results');
    const studentSelect = qs('#rex-student-select');
    let studentTimer;
    if (studentSearch && studentResults && studentSelect) {
        studentSearch.addEventListener('input', () => {
            clearTimeout(studentTimer);
            studentTimer = window.setTimeout(async () => {
                const keyword = studentSearch.value.trim();
                if (!keyword) {
                    studentResults.innerHTML = '';
                    return;
                }
                studentResults.innerHTML = '<span class="rex-lookup-message">検索中...</span>';
                try {
                    const response = await fetch(`${window.rewardExchangeConfig.studentLookup}?q=${encodeURIComponent(keyword)}`, {
                        headers: { Accept: 'application/json' },
                    });
                    const data = await parseJsonResponse(response);
                    studentResults.innerHTML = '';
                    data.forEach((student) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.textContent = `${student.label}（${formatNumber(student.points)}pt）`;
                        button.addEventListener('click', () => {
                            studentSelect.innerHTML = `<option selected value="${student.id}">${escapeHtml(student.label)}</option>`;
                            studentSearch.value = student.label;
                            studentResults.innerHTML = '';
                        });
                        studentResults.appendChild(button);
                    });
                    if (!data.length) {
                        studentResults.innerHTML = '<span class="rex-lookup-message">該当する生徒がいません。</span>';
                    }
                } catch (error) {
                    console.error('[RewardExchange] student lookup failed', error);
                    studentResults.innerHTML = `<span class="rex-lookup-message is-error">${escapeHtml(error.message)}</span>`;
                }
            }, 250);
        });
    }

    // 商品詳細Drawer
    const productDrawer = qs('#rex-product-drawer');
    const productBackdrop = qs('#rex-product-backdrop');
    const productContent = qs('#rex-product-detail-content');
    const closeProduct = () => {
        closeLayer(productDrawer);
        if (productBackdrop) productBackdrop.hidden = true;
    };
    qsa('.rex-product-drawer-close').forEach((button) => button.addEventListener('click', closeProduct));
    if (productBackdrop) productBackdrop.addEventListener('click', closeProduct);

    const showProduct = (card) => {
        if (!card || !productContent) return;
        const product = JSON.parse(card.dataset.product || '{}');
        qs('#rex-product-detail-title').textContent = product.name || '商品詳細';
        const tags = [
            product.recommended ? '<b class="tag-recommended">★ おすすめ</b>' : '',
            product.new ? '<b class="tag-new">NEW</b>' : '',
            product.limited ? '<b class="tag-limited">限定</b>' : '',
        ].join(' ');
        productContent.innerHTML = `
            <div class="rex-detail-hero">${product.image ? `<img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}">` : '<span>🎁</span>'}</div>
            <div class="rex-tags rex-detail-tags">${tags}</div>
            <span class="rex-category-badge">${escapeHtml(product.category)}</span>
            <p class="rex-detail-description">${escapeHtml(product.description || '説明はありません。')}</p>
            <div class="rex-detail-stat-grid">
                <article><span>必要ポイント</span><b>${formatNumber(product.points)}pt</b></article>
                <article><span>交換可能在庫</span><b>${product.available === null ? '制限なし' : formatNumber(product.available)}</b></article>
                <article><span>交換実績</span><b>${formatNumber(product.exchangeCount)}件</b></article>
            </div>
            <dl class="rex-detail-dl">
                <dt>商品コード</dt><dd>${escapeHtml(product.code || '-')}</dd>
                <dt>在庫状態</dt><dd>${escapeHtml(product.stockLabel || '-')}</dd>
                <dt>実在庫</dt><dd>${product.stock === null ? '管理なし' : formatNumber(product.stock)}</dd>
                <dt>予約数</dt><dd>${product.reserved === null ? '-' : formatNumber(product.reserved)}</dd>
            </dl>`;
        openLayer(productDrawer);
        if (productBackdrop) productBackdrop.hidden = false;
    };

    qsa('.rex-product-card').forEach((card) => {
        card.addEventListener('click', (event) => {
            if (event.target.closest('.rex-open-request, .rex-open-detail')) return;
            showProduct(card);
        });
        const detailButton = qs('.rex-open-detail', card);
        if (detailButton) {
            detailButton.addEventListener('click', (event) => {
                event.stopPropagation();
                showProduct(card);
            });
        }
    });

    // 交換申請モーダル
    const requestModal = qs('#rex-request-modal');
    const requestForm = qs('#rex-request-form');
    const quantityInput = qs('#rex-quantity');
    let currentItem = null;

    const renderRequest = () => {
        if (!currentItem || !quantityInput) return;
        const max = currentItem.available === null ? 99 : Math.max(1, Number(currentItem.available));
        let quantity = Math.max(1, Number(quantityInput.value || 1));
        quantity = Math.min(quantity, max);
        quantityInput.value = quantity;
        const total = Number(currentItem.points) * quantity;
        const after = Number(currentItem.balance) - total;
        qs('#rex-total-points').textContent = `${formatNumber(total)}pt`;
        qs('#rex-after-balance').textContent = `${formatNumber(after)}pt`;
        qs('#rex-after-balance').classList.toggle('is-negative', after < 0);
        qs('#rex-quantity-help').textContent = currentItem.available === null
            ? '数量制限なし'
            : `交換可能在庫：${formatNumber(currentItem.available)}個`;
        const errorBox = qs('#rex-request-error');
        const submit = qs('#rex-submit-request');
        if (after < 0) {
            errorBox.textContent = 'ポイントが不足しています。';
            errorBox.hidden = false;
            submit.disabled = true;
        } else {
            errorBox.hidden = true;
            submit.disabled = false;
        }
    };

    qsa('.rex-open-request').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            if (button.disabled) return;
            currentItem = JSON.parse(button.dataset.item || '{}');
            qs('#rex-item-id').value = currentItem.id;
            qs('#rex-item-name').textContent = currentItem.name;
            qs('#rex-modal-category').textContent = currentItem.category;
            qs('#rex-unit-points').textContent = `${formatNumber(currentItem.points)}pt`;
            const image = qs('#rex-modal-image');
            image.innerHTML = currentItem.image ? `<img src="${escapeHtml(currentItem.image)}" alt="">` : '🎁';
            quantityInput.max = currentItem.available === null ? 99 : Math.max(1, currentItem.available);
            quantityInput.value = 1;
            renderRequest();
            openLayer(requestModal);
        });
    });

    if (quantityInput) {
        quantityInput.addEventListener('input', renderRequest);
        qs('#rex-qty-minus')?.addEventListener('click', () => {
            quantityInput.value = Math.max(1, Number(quantityInput.value) - 1);
            renderRequest();
        });
        qs('#rex-qty-plus')?.addEventListener('click', () => {
            quantityInput.value = Number(quantityInput.value) + 1;
            renderRequest();
        });
    }

    if (requestForm) {
        requestForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const errorBox = qs('#rex-request-error');
            const submit = qs('#rex-submit-request');
            const total = Number(currentItem?.points || 0) * Number(quantityInput?.value || 1);
            if (Number(currentItem?.balance || 0) - total < 0) {
                errorBox.textContent = 'ポイントが不足しています。';
                errorBox.hidden = false;
                return;
            }
            submit.disabled = true;
            submit.textContent = '申請中…';
            errorBox.hidden = true;
            try {
                const response = await fetch(requestForm.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(requestForm),
                });
                const payload = await parseJsonResponse(response);
                currentItem.balance = payload.student_points;
                currentItem.available = payload.available_quantity;
                const balance = qs('#rex-selected-balance');
                if (balance) balance.innerHTML = `${formatNumber(payload.student_points)}<em>pt</em>`;

                const card = qsa('.rex-product-card').find((candidate) => {
                    try { return Number(JSON.parse(candidate.dataset.product || '{}').id) === Number(payload.item_id); } catch (_) { return false; }
                });
                if (card) {
                    const requestButton = qs('.rex-open-request', card);
                    if (requestButton) {
                        requestButton.disabled = true;
                        requestButton.textContent = '申請中';
                    }
                    let unavailable = qs('.rex-unavailable', card);
                    if (!unavailable) {
                        unavailable = document.createElement('div');
                        unavailable.className = 'rex-unavailable';
                        qs('footer', card)?.prepend(unavailable);
                    }
                    unavailable.innerHTML = '<span>!</span>同じ商品の未処理申請があります。';
                    const stockValue = qs('.rex-product-meta span b', card);
                    if (stockValue && payload.available_quantity !== null) stockValue.textContent = formatNumber(payload.available_quantity);
                }
                closeLayer(requestModal);
                showToast(`${payload.message}（${payload.request_number}）`);
            } catch (error) {
                console.error('[RewardExchange] request failed', error);
                errorBox.textContent = error.message;
                errorBox.hidden = false;
                showToast(error.message, 'error');
            } finally {
                submit.disabled = false;
                submit.textContent = '申請する';
            }
        });
    }

    // 申請処理モーダル
    const processModal = qs('#rex-process-modal');
    const processForm = qs('#rex-process-form');
    const statusSelect = qs('#rex-process-status');
    let processData = null;
    const impact = {
        approved: 'ポイント・在庫は申請時に確保済みです。追加のポイント減算は行いません。',
        preparing: '担当者を記録し、受渡準備中へ変更します。',
        shipped: '発送日時と追跡番号を記録します。ポイント・在庫は確保済みのままです。',
        delivered: '予約在庫を実在庫から確定減算し、商品原価を会計台帳へ1件だけ連携します。',
        rejected: 'ポイントを全額返還し、予約在庫を解除します。再実行はできません。',
        cancelled: 'ポイントを全額返還し、予約在庫を解除します。再実行はできません。',
    };

    const renderProcess = () => {
        const value = statusSelect?.value || '';
        qs('#rex-tracking-wrap').hidden = value !== 'shipped';
        qs('#rex-reason-wrap').hidden = !['rejected', 'cancelled'].includes(value);
        const impactBox = qs('#rex-process-impact');
        impactBox.textContent = value ? (impact[value] || '申請状態を更新します。') : '実行する処理を選択してください。';
        impactBox.className = `rex-process-impact ${['rejected', 'cancelled'].includes(value) ? 'is-warning' : ''}`;
    };

    qsa('.rex-process-button').forEach((button) => {
        button.addEventListener('click', () => {
            if (button.disabled) return;
            processData = JSON.parse(button.dataset.action || '{}');
            processForm.action = processData.url;
            qs('#rex-process-number').textContent = processData.requestNumber;
            qs('#rex-process-student').textContent = processData.student;
            qs('#rex-process-item').textContent = processData.item;
            qs('#rex-process-amount').textContent = `${processData.quantity}個／${formatNumber(processData.points)}pt`;
            qs('#rex-process-current').textContent = processData.currentStatus;
            statusSelect.innerHTML = '<option value="">処理を選択してください</option>' + Object.entries(processData.actions)
                .map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`)
                .join('');
            qs('#rex-process-tracking').value = processData.trackingNumber || '';
            qs('#rex-process-note').value = processData.note || '';
            qs('#rex-process-reason').value = '';
            qs('#rex-process-error').hidden = true;
            renderProcess();
            openLayer(processModal);
        });
    });
    statusSelect?.addEventListener('change', renderProcess);

    if (processForm) {
        processForm.addEventListener('submit', (event) => {
            const value = statusSelect.value;
            const errorBox = qs('#rex-process-error');
            errorBox.hidden = true;
            if (!value) {
                event.preventDefault();
                errorBox.textContent = '実行する処理を選択してください。';
                errorBox.hidden = false;
                return;
            }
            if (value === 'shipped' && !qs('#rex-process-tracking').value.trim()) {
                event.preventDefault();
                errorBox.textContent = '発送済にする場合は追跡番号を入力してください。';
                errorBox.hidden = false;
                return;
            }
            if (['rejected', 'cancelled'].includes(value) && !qs('#rex-process-reason').value.trim()) {
                event.preventDefault();
                errorBox.textContent = '却下・取消理由を入力してください。';
                errorBox.hidden = false;
                return;
            }
            const label = statusSelect.options[statusSelect.selectedIndex].textContent;
            if (!window.confirm(`${processData?.requestNumber || '申請'}を「${label}」に更新します。\n\n${impact[value] || ''}\n\n実行してよろしいですか？`)) {
                event.preventDefault();
                return;
            }
            const submit = qs('#rex-process-submit');
            submit.disabled = true;
            submit.textContent = '処理中…';
        });
    }

    // 交換詳細Drawer
    const detailDrawer = qs('#rex-detail-drawer');
    const detailBackdrop = qs('#rex-drawer-backdrop');
    const detailContent = qs('#rex-detail-content');
    const closeDetail = () => {
        closeLayer(detailDrawer);
        if (detailBackdrop) detailBackdrop.hidden = true;
    };
    qsa('.rex-drawer-close').forEach((button) => button.addEventListener('click', closeDetail));
    if (detailBackdrop) detailBackdrop.addEventListener('click', closeDetail);

    qsa('.rex-detail').forEach((button) => {
        button.addEventListener('click', async () => {
            openLayer(detailDrawer);
            if (detailBackdrop) detailBackdrop.hidden = false;
            detailContent.innerHTML = '<p class="rex-loading">読み込み中...</p>';
            try {
                const response = await fetch(button.dataset.url, { headers: { Accept: 'application/json' } });
                const payload = await parseJsonResponse(response);
                const exchange = payload.exchange;
                const image = exchange.item?.main_image?.image_path ? `/storage/${exchange.item.main_image.image_path}` : null;
                const restoreBadges = ['rejected', 'cancelled'].includes(exchange.status)
                    ? '<div class="rex-restoration-badges rex-restoration-detail"><span>ポイント返還済</span><span>在庫戻し済</span></div>'
                    : '';
                detailContent.innerHTML = `
                    <div class="rex-detail-hero">${image ? `<img src="${escapeHtml(image)}" alt="">` : '<span>🎁</span>'}</div>
                    ${restoreBadges}
                    <dl class="rex-detail-dl">
                        <dt>申請番号</dt><dd>${escapeHtml(exchange.request_number || '-')}</dd>
                        <dt>生徒</dt><dd>${escapeHtml(`${exchange.student?.last_name || ''} ${exchange.student?.first_name || ''}`.trim())}</dd>
                        <dt>商品</dt><dd>${escapeHtml(exchange.item?.name || '削除済み商品')}</dd>
                        <dt>ポイント</dt><dd>${formatNumber(exchange.request_points)}pt</dd>
                        <dt>返還ポイント</dt><dd>${formatNumber(exchange.returned_points)}pt</dd>
                        <dt>受渡方法</dt><dd>${exchange.delivery_method === 'shipping' ? '🚚 発送' : '🏫 教室受渡'}</dd>
                        <dt>担当者</dt><dd>${escapeHtml(exchange.handler?.name || '未担当')}</dd>
                        <dt>追跡番号</dt><dd>${escapeHtml(exchange.tracking_number || '-')}</dd>
                        <dt>管理メモ</dt><dd>${escapeHtml(exchange.note || '-')}</dd>
                        <dt>ポイント連携</dt><dd>${payload.links?.point ? `<a href="${escapeHtml(payload.links.point)}">Pt #${exchange.point_transaction_id}</a>` : '-'}</dd>
                        <dt>会計連携</dt><dd>${payload.links?.account ? `<a href="${escapeHtml(payload.links.account)}">会計 #${exchange.account_transaction_id}</a>` : '-'}</dd>
                    </dl>
                    <h3>処理タイムライン</h3>
                    <div class="rex-timeline">${payload.events.map((event) => `
                        <article class="event-${escapeHtml(event.type || '')}">
                            <b>${escapeHtml(event.label)}</b>
                            <time>${new Date(event.at).toLocaleString('ja-JP')}</time>
                            <p>${escapeHtml(event.detail || '')}</p>
                            <small>担当：${escapeHtml(event.actor || 'システム')}</small>
                        </article>`).join('')}</div>`;
            } catch (error) {
                console.error('[RewardExchange] detail load failed', error);
                detailContent.innerHTML = `<div class="rex-error-box">${escapeHtml(error.message)}</div>`;
            }
        });
    });

    qsa('.rex-modal-close, .rex-process-close').forEach((button) => {
        button.addEventListener('click', () => closeLayer(button.closest('.rex-modal')));
    });
    qsa('.rex-modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeLayer(modal);
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        qsa('.rex-modal:not([hidden])').forEach(closeLayer);
        closeProduct();
        closeDetail();
    });
})();
