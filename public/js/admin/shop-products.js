/**
 * ショップ商品一覧・登録編集モーダル。
 *
 * 役割:
 * - 登録／編集モーダルの開閉
 * - タブ切替、必須入力状態、エラー件数表示
 * - 商品画像の選択・プレビュー・削除指定
 * - 粗利益、粗利率、公開状態の即時計算
 * - 未保存変更の検知、二重送信防止
 */
(() => {
    'use strict';

    const modal = document.getElementById('shopProductModal');
    const form = document.getElementById('shopProductForm');
    if (!modal || !form) return;

    const page = document.querySelector('.shop-products-page');
    const title = document.getElementById('shopProductModalTitle');
    const subtitle = document.getElementById('shopProductModalSubtitle');
    const method = document.getElementById('shopProductMethod');
    const submit = document.getElementById('shopProductSubmitButton');
    const preview = document.getElementById('shopProductImagePreview');
    const imageCaption = document.getElementById('shopProductImageCaption');
    const fileInput = document.getElementById('shopProductImage');
    const fileName = document.getElementById('shopProductFileName');
    const removeRow = document.getElementById('shopProductRemoveImageRow');
    const unsaved = document.getElementById('shopProductUnsavedWarning');
    const dirtyCount = document.getElementById('shopProductDirtyCount');
    const descriptionCount = document.getElementById('shopProductDescriptionCount');
    const publicationPreview = document.getElementById('shopProductPublicationPreview');
    const profit = document.getElementById('shopProductProfit');
    const profitRate = document.getElementById('shopProductProfitRate');
    const editingId = document.getElementById('shopProductEditingId');
    const createAction = form.action;

    let dirtyFields = new Set();
    let submitted = false;
    let initialSnapshot = '';
    let objectUrl = null;

    const fields = name => form.elements.namedItem(name);
    const fieldValue = field => field?.type === 'checkbox' ? Boolean(field.checked) : (field?.value ?? '');
    const setField = (name, value) => {
        const field = fields(name);
        if (!field) return;
        if (field.type === 'checkbox') field.checked = Boolean(value);
        else field.value = value ?? '';
    };
    const snapshot = () => JSON.stringify([...new FormData(form).entries()].filter(([key]) => key !== 'image'));
    const setPreview = (url, caption = '商品画像プレビュー') => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        preview.innerHTML = url
            ? `<img src="${url}" alt="商品画像プレビュー">`
            : '<i class="fas fa-image"></i><span>画像未選択</span>';
        imageCaption.textContent = caption;
    };
    const switchTab = name => {
        document.querySelectorAll('[data-product-tab]').forEach(button => {
            const active = button.dataset.productTab === name;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('[data-product-panel]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.productPanel === name));
    };
    const formatMoney = amount => `${amount < 0 ? '-' : ''}¥${Math.abs(amount).toLocaleString('ja-JP')}`;
    const calculateProfit = () => {
        const price = Number(fields('price')?.value || 0);
        const purchase = Number(fields('purchase_price')?.value || 0);
        const amount = price - purchase;
        const rate = price > 0 ? (amount / price) * 100 : 0;
        profit.textContent = formatMoney(amount);
        profitRate.textContent = `${rate.toFixed(1)}%`;
        profit.classList.toggle('is-negative', amount < 0);
    };
    const updateDescriptionCount = () => {
        if (descriptionCount) descriptionCount.textContent = String(fields('description')?.value.length || 0);
    };
    const updatePublicationPreview = () => {
        if (!publicationPreview) return;
        const start = fields('published_at')?.value;
        const end = fields('sales_end_at')?.value;
        const active = fields('is_active')?.checked;
        const online = fields('is_online')?.checked;
        const now = new Date();
        let label = '公開期間未設定';
        let state = 'neutral';
        if (!active) {
            label = '無効（販売対象外）';
            state = 'danger';
        } else if (!online) {
            label = '店頭販売のみ';
            state = 'neutral';
        } else if (start && new Date(start) > now) {
            label = '公開前';
            state = 'warning';
        } else if (end && new Date(end) < now) {
            label = '公開終了';
            state = 'danger';
        } else {
            label = 'オンライン公開中';
            state = 'success';
        }
        publicationPreview.className = `publication-preview ${state}`;
        publicationPreview.textContent = `${label}｜開始 ${start || '指定なし'}｜終了 ${end || '指定なし'}`;
    };
    const syncStockFields = () => {
        const enabled = fields('is_stock_managed')?.checked;
        ['stock_quantity', 'alert_quantity'].forEach(name => {
            const field = fields(name);
            if (!field) return;
            field.disabled = !enabled;
            field.closest('label')?.classList.toggle('is-disabled', !enabled);
        });
    };
    const getPanelState = panel => {
        const requiredFields = [...panel.querySelectorAll('[required]')].filter(field => !field.disabled);
        const invalid = requiredFields.filter(field => !String(fieldValue(field)).trim());
        return { required: requiredFields.length, invalid: invalid.length };
    };
    const updateTabStates = () => {
        document.querySelectorAll('[data-product-panel]').forEach(panel => {
            const state = getPanelState(panel);
            const badge = document.querySelector(`[data-tab-state="${panel.dataset.productPanel}"]`);
            if (!badge) return;
            badge.className = 'tab-state';
            badge.textContent = '';
            if (state.invalid > 0) {
                badge.textContent = String(state.invalid);
                badge.classList.add('has-errors');
            } else if (state.required > 0) {
                badge.textContent = '✓';
                badge.classList.add('is-complete');
            }
        });
    };
    const updateDirtyState = () => {
        const isDirty = snapshot() !== initialSnapshot || Boolean(fileInput.files?.length);
        unsaved?.classList.toggle('is-visible', isDirty);
        if (dirtyCount) dirtyCount.textContent = String(Math.max(dirtyFields.size, isDirty ? 1 : 0));
        return isDirty;
    };
    const resetDirty = () => {
        dirtyFields.clear();
        submitted = false;
        initialSnapshot = snapshot();
        unsaved?.classList.remove('is-visible');
        if (dirtyCount) dirtyCount.textContent = '0';
    };
    const open = () => {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        switchTab('basic');
        updateTabStates();
        window.setTimeout(() => fields('product_code')?.focus(), 30);
    };
    const close = (force = false) => {
        if (!force && updateDirtyState() && !window.confirm('未保存の変更があります。入力内容を破棄して閉じますか？')) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        resetDirty();
    };
    const clearErrors = () => form.querySelectorAll('.has-error').forEach(element => element.classList.remove('has-error'));
    const validate = () => {
        clearErrors();
        let firstInvalid = null;
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.disabled && !String(field.value).trim()) {
                field.closest('label')?.classList.add('has-error');
                firstInvalid ||= field;
            }
        });
        const start = fields('published_at')?.value;
        const end = fields('sales_end_at')?.value;
        if (start && end && end < start) {
            fields('sales_end_at').closest('label')?.classList.add('has-error');
            firstInvalid ||= fields('sales_end_at');
        }
        updateTabStates();
        if (firstInvalid) {
            const panel = firstInvalid.closest('[data-product-panel]');
            if (panel) switchTab(panel.dataset.productPanel);
            firstInvalid.focus();
            return false;
        }
        return true;
    };
    const openCreate = () => {
        form.reset();
        clearErrors();
        form.action = createAction;
        method.value = 'POST';
        editingId.value = '';
        title.textContent = '商品を追加';
        subtitle.textContent = '新しい商品の基本情報・価格・在庫・公開設定を登録します。';
        submit.textContent = '商品を登録';
        setField('display_order', 0);
        setField('tax_rate', '10');
        setField('alert_quantity', 5);
        setField('stock_quantity', 0);
        setField('point_reward', 0);
        setField('point_price', 0);
        setField('price', 0);
        setField('purchase_price', 0);
        setField('is_stock_managed', true);
        setField('is_online', false);
        setField('is_active', true);
        removeRow.style.display = 'none';
        fileName.textContent = '選択されていません';
        setPreview(null);
        calculateProfit();
        syncStockFields();
        updateDescriptionCount();
        updatePublicationPreview();
        resetDirty();
        open();
    };
    const openEdit = product => {
        form.reset();
        clearErrors();
        form.action = `${page.dataset.updateBase}/${product.id}`;
        method.value = 'PUT';
        editingId.value = String(product.id || '');
        title.textContent = '商品を編集';
        subtitle.textContent = `${product.product_code} ｜ ${product.name}`;
        submit.textContent = '変更を保存';
        Object.entries(product).forEach(([key, value]) => setField(key, value));
        setField('tax_rate', String(Number(product.tax_rate || 0)));
        removeRow.style.display = product.image_url ? 'flex' : 'none';
        fileName.textContent = '選択されていません';
        setPreview(product.image_url || null, product.image_url ? '現在の画像' : '商品画像プレビュー');
        calculateProfit();
        syncStockFields();
        updateDescriptionCount();
        updatePublicationPreview();
        resetDirty();
        open();
    };

    document.querySelectorAll('[data-product-tab]').forEach(button => button.addEventListener('click', () => switchTab(button.dataset.productTab)));
    document.querySelectorAll('[data-open-product-modal]').forEach(button => {
        button.addEventListener('click', () => {
            if (button.dataset.openProductModal === 'create') return openCreate();
            try {
                openEdit(JSON.parse(decodeURIComponent(escape(atob(button.dataset.product)))));
            } catch (error) {
                console.error('商品情報の読み込みに失敗しました。', error);
                window.alert('商品情報を読み込めませんでした。画面を再読み込みしてください。');
            }
        });
    });
    document.querySelectorAll('[data-close-product-modal]').forEach(button => button.addEventListener('click', () => close()));
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) close();
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter' && modal.classList.contains('is-open')) form.requestSubmit();
    });
    form.addEventListener('input', event => {
        if (event.target.name) dirtyFields.add(event.target.name);
        event.target.closest('label')?.classList.remove('has-error');
        if (['price', 'purchase_price'].includes(event.target.name)) calculateProfit();
        if (event.target.name === 'description') updateDescriptionCount();
        if (event.target.name === 'is_stock_managed') syncStockFields();
        if (['published_at', 'sales_end_at', 'is_online', 'is_active'].includes(event.target.name)) updatePublicationPreview();
        updateTabStates();
        updateDirtyState();
    });
    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            window.alert('商品画像は5MB以下にしてください。');
            fileInput.value = '';
            fileName.textContent = '選択されていません';
            return;
        }
        objectUrl = URL.createObjectURL(file);
        preview.innerHTML = `<img src="${objectUrl}" alt="変更後の商品画像プレビュー">`;
        imageCaption.textContent = '変更後の画像';
        fileName.textContent = file.name;
        dirtyFields.add('image');
        fields('remove_image').checked = false;
        switchTab('image');
        updateDirtyState();
    });
    fields('remove_image')?.addEventListener('change', event => {
        if (event.target.checked) {
            setPreview(null, '削除後の状態');
            fileInput.value = '';
            fileName.textContent = '選択されていません';
        }
        dirtyFields.add('remove_image');
        updateDirtyState();
    });
    form.addEventListener('submit', event => {
        if (!validate()) {
            event.preventDefault();
            return;
        }
        submitted = true;
        submit.disabled = true;
        submit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中…';
    });
    window.addEventListener('beforeunload', event => {
        // 商品モーダルを開いて編集中の場合だけ離脱警告を表示する。
        // ページ表示直後やモーダルを閉じた後は警告しない。
        if (modal.classList.contains('is-open') && updateDirtyState() && !submitted) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    // 初期表示時のフォーム状態を基準値として保持し、未操作での誤警告を防止する。
    resetDirty();

    const openFromValidation = encoded => {
        try {
            const product = JSON.parse(decodeURIComponent(escape(atob(encoded))));
            if (product.id) openEdit(product); else openCreate();
            Object.entries(product).forEach(([key, value]) => setField(key, value));
            calculateProfit(); syncStockFields(); updateDescriptionCount(); updatePublicationPreview(); updateTabStates();
        } catch (error) {
            console.error('入力値の復元に失敗しました。', error);
            openCreate();
        }
    };

    window.ShopProducts = { openCreate, openEdit, openFromValidation, close: () => close(true) };
})();
