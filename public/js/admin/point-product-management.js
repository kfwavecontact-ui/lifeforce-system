(() => {
    const page = document.querySelector('.ppm-page');
    if (!page) return;

    const q = (selector, root = document) => root.querySelector(selector);
    const qa = (selector, root = document) => [...root.querySelectorAll(selector)];
    const body = q('#ppmBody');
    const drawer = q('#ppmDrawer');
    const overlay = q('#ppmOverlay');
    const form = q('#ppmForm');
    const pagination = q('#ppmPagination');
    const imageList = q('#ppmImageList');
    const newImageList = q('#ppmNewImageList');
    const availablePreview = q('#ppmAvailablePreview');
    let rows = [];
    let currentPage = 1;
    let draggedRow = null;

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[char]));

    const storageUrl = (path) => {
        if (!path) return '';
        if (/^https?:\/\//i.test(path) || path.startsWith('/')) return path;
        return `/storage/${path}`;
    };

    const dateText = (value) => {
        if (!value) return '—';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        return new Intl.DateTimeFormat('ja-JP', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit',
        }).format(date);
    };

    const toLocalDateTime = (value) => {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        const offset = date.getTimezoneOffset();
        return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 16);
    };

    const filterParams = (pageNumber = 1) => new URLSearchParams({
        page: String(pageNumber),
        keyword: q('#ppmKeyword').value,
        category_id: q('#ppmCategory').value,
        publication_status: q('#ppmStatus').value,
        stock_status: q('#ppmStock').value,
        recommended: q('#ppmRecommended').value,
        limited: q('#ppmLimitedFilter').value,
        active: q('#ppmActive').value,
        sort: q('#ppmSort').value,
    });

    async function request(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                'X-CSRF-TOKEN': page.dataset.csrf,
                ...(options.headers || {}),
            },
            ...options,
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = Object.values(json.errors || {}).flat().join('\n') || json.message || '処理に失敗しました。';
            throw new Error(message);
        }
        return json;
    }

    async function load(pageNumber = 1) {
        currentPage = pageNumber;
        body.innerHTML = '<tr><td colspan="16">読み込み中...</td></tr>';

        try {
            const json = await request(`${page.dataset.listUrl}?${filterParams(pageNumber)}`);
            rows = json.data || [];
            renderRows();
            renderStats(json.stats || {});
            renderPagination(json);
        } catch (error) {
            body.innerHTML = `<tr><td colspan="16" class="ppm-error">${esc(error.message)}</td></tr>`;
        }
    }

    function renderStats(stats) {
        q('#ppmRegistered').textContent = stats.registered ?? 0;
        q('#ppmPublished').textContent = stats.published ?? 0;
        q('#ppmOutStock').textContent = stats.out_of_stock ?? 0;
        q('#ppmLowStock').textContent = stats.low_stock ?? 0;
        q('#ppmLimited').textContent = stats.limited ?? 0;
        q('#ppmEnded').textContent = stats.ended ?? 0;
    }

    function statusBadge(status) {
        const labels = { draft: '下書き', published: '公開', ended: '公開終了' };
        return `<span class="ppm-badge ppm-badge-${esc(status)}">${esc(labels[status] || status)}</span>`;
    }

    function stockClass(available, alert, managed) {
        if (!managed) return 'ppm-stock-unmanaged';
        if (available <= 0) return 'ppm-stock-out';
        if (available <= alert) return 'ppm-stock-low';
        return 'ppm-stock-ok';
    }

    function renderRows() {
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="16">該当するポイント商品はありません。</td></tr>';
            return;
        }

        body.innerHTML = rows.map((item, index) => {
            const stock = item.stock || {
                stock_quantity: item.stock_quantity || 0,
                reserved_quantity: 0,
                alert_quantity: item.stock_alert_quantity || 0,
            };
            const available = item.is_stock_managed
                ? Math.max(0, Number(stock.stock_quantity) - Number(stock.reserved_quantity))
                : null;
            const image = item.main_image?.image_path || item.image_url;
            const tags = [
                item.is_recommended ? '<span class="ppm-mini-tag">おすすめ</span>' : '',
                item.is_new ? '<span class="ppm-mini-tag">NEW</span>' : '',
                item.is_limited ? '<span class="ppm-mini-tag">限定</span>' : '',
            ].filter(Boolean).join('');
            const rowNumber = ((currentPage - 1) * 30) + index + 1;

            return `
                <tr draggable="true" data-id="${item.id}" class="${item.is_active ? '' : 'ppm-row-inactive'}">
                    <td class="ppm-drag-cell"><button type="button" class="ppm-drag" title="ドラッグして並び替え">⋮⋮</button><span>${rowNumber}</span></td>
                    <td>${item.id}</td>
                    <td>${image ? `<button type="button" class="ppm-image-button" data-image="${esc(storageUrl(image))}"><img class="ppm-thumb" src="${esc(storageUrl(image))}" alt="${esc(item.name)}"></button>` : '<span class="ppm-no-image">画像なし</span>'}</td>
                    <td><code>${esc(item.code)}</code></td>
                    <td><strong>${esc(item.name)}</strong><div class="ppm-tag-line">${tags}</div></td>
                    <td><span class="ppm-category-dot" style="--category-color:${esc(item.category?.color_code || '#94a3b8')}"></span>${esc(item.category?.name || '—')}</td>
                    <td class="ppm-number"><strong>${Number(item.required_points || 0).toLocaleString()}Pt</strong></td>
                    <td class="ppm-number">${item.is_stock_managed ? Number(stock.stock_quantity).toLocaleString() : '管理なし'}</td>
                    <td class="ppm-number">${item.is_stock_managed ? Number(stock.reserved_quantity).toLocaleString() : '—'}</td>
                    <td class="ppm-number ${stockClass(available, Number(stock.alert_quantity), item.is_stock_managed)}">${item.is_stock_managed ? Number(available).toLocaleString() : '∞'}</td>
                    <td>${statusBadge(item.publication_status)}</td>
                    <td>${item.is_recommended ? '<span class="ppm-flag on">★</span>' : '<span class="ppm-flag">—</span>'}</td>
                    <td>${item.is_limited ? '<span class="ppm-badge ppm-badge-limited">限定</span>' : '—'}</td>
                    <td>${item.is_active ? '<span class="ppm-badge ppm-badge-active">有効</span>' : '<span class="ppm-badge ppm-badge-inactive">無効</span>'}</td>
                    <td>${esc(dateText(item.updated_at))}</td>
                    <td class="ppm-actions">
                        <button type="button" data-edit="${item.id}">編集</button>
                        <button type="button" data-copy="${item.id}">複製</button>
                        <button type="button" data-publish="${item.id}">${item.publication_status === 'published' ? '下書きへ' : '公開'}</button>
                        <button type="button" class="danger" data-delete="${item.id}">削除</button>
                    </td>
                </tr>`;
        }).join('');

        bindDragAndDrop();
    }

    function renderPagination(meta) {
        if (!meta.last_page || meta.last_page <= 1) {
            pagination.innerHTML = meta.total ? `全${meta.total}件` : '';
            return;
        }

        const buttons = [];
        buttons.push(`<span>全${meta.total}件（${meta.from ?? 0}〜${meta.to ?? 0}件）</span>`);
        buttons.push(`<button type="button" data-page="${Math.max(1, meta.current_page - 1)}" ${meta.current_page <= 1 ? 'disabled' : ''}>前へ</button>`);
        for (let pageNumber = Math.max(1, meta.current_page - 2); pageNumber <= Math.min(meta.last_page, meta.current_page + 2); pageNumber += 1) {
            buttons.push(`<button type="button" data-page="${pageNumber}" class="${pageNumber === meta.current_page ? 'active' : ''}">${pageNumber}</button>`);
        }
        buttons.push(`<button type="button" data-page="${Math.min(meta.last_page, meta.current_page + 1)}" ${meta.current_page >= meta.last_page ? 'disabled' : ''}>次へ</button>`);
        pagination.innerHTML = buttons.join('');
    }

    function openDrawer(item = null) {
        form.reset();
        q('#ppmId').value = item?.id || '';
        q('#ppmDrawerTitle').textContent = item ? 'ポイント商品を編集' : 'ポイント商品を追加';
        imageList.innerHTML = '';
        newImageList.innerHTML = '';

        if (item) {
            Object.entries(item).forEach(([key, value]) => {
                const element = form.elements[key];
                if (!element) return;
                if (element.type === 'checkbox') element.checked = Boolean(value);
                else if (key === 'published_at' || key === 'publication_ended_at') element.value = toLocalDateTime(value);
                else if (value !== null && typeof value !== 'object') element.value = value;
            });

            if (item.stock) {
                form.elements.stock_quantity.value = item.stock.stock_quantity;
                form.elements.reserved_quantity.value = item.stock.reserved_quantity;
                form.elements.alert_quantity.value = item.stock.alert_quantity;
            }
            renderExistingImages(item.images || []);
        } else {
            form.elements.publication_status.value = 'draft';
            form.elements.stock_quantity.value = 0;
            form.elements.reserved_quantity.value = 0;
            form.elements.alert_quantity.value = 0;
            form.elements.is_stock_managed.checked = true;
            form.elements.is_active.checked = true;
        }

        updateAvailablePreview();
        drawer.classList.add('open');
        overlay.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ppm-lock');
    }

    function closeDrawer() {
        drawer.classList.remove('open');
        overlay.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ppm-lock');
    }

    function renderExistingImages(images) {
        imageList.innerHTML = images.map((image) => `
            <article class="ppm-image-card" data-image-id="${image.id}">
                <img src="${esc(storageUrl(image.image_path))}" alt="${esc(image.alt_text || '')}">
                <label><input type="radio" name="main_image_id" value="${image.id}" ${image.is_main ? 'checked' : ''}> メイン画像</label>
                <label><input type="checkbox" name="remove_image_ids[]" value="${image.id}"> 削除予定</label>
            </article>`).join('');
    }

    function renderNewImages(files) {
        newImageList.innerHTML = '';
        [...files].forEach((file) => {
            const reader = new FileReader();
            reader.onload = () => {
                const card = document.createElement('article');
                card.className = 'ppm-image-card';
                card.innerHTML = `<img src="${esc(reader.result)}" alt="追加予定画像"><span>追加予定</span>`;
                newImageList.appendChild(card);
            };
            reader.readAsDataURL(file);
        });
    }

    function updateAvailablePreview() {
        const managed = form.elements.is_stock_managed.checked;
        const stock = Number(form.elements.stock_quantity.value || 0);
        const reserved = Number(form.elements.reserved_quantity.value || 0);
        availablePreview.value = managed ? Math.max(0, stock - reserved).toLocaleString() : '在庫管理なし';
    }

    async function save(event) {
        event.preventDefault();
        const id = q('#ppmId').value;
        const formData = new FormData(form);
        ['is_stock_managed', 'is_recommended', 'is_new', 'is_limited', 'is_active'].forEach((key) => {
            formData.set(key, form.elements[key].checked ? '1' : '0');
        });
        if (id) formData.append('_method', 'PUT');

        try {
            await request(id ? `${page.dataset.baseUrl}/${id}` : page.dataset.storeUrl, {
                method: 'POST',
                body: formData,
            });
            closeDrawer();
            await load(currentPage);
        } catch (error) {
            alert(error.message);
        }
    }

    async function performAction(url, method, confirmation) {
        if (confirmation && !confirm(confirmation)) return;
        try {
            await request(url, { method });
            await load(currentPage);
        } catch (error) {
            alert(error.message);
        }
    }

    function resetFilters() {
        qa('.ppm-filter input, .ppm-filter select').forEach((element) => {
            element.value = element.id === 'ppmSort' ? 'display_order' : '';
        });
        load(1);
    }

    function bindDragAndDrop() {
        qa('#ppmBody tr[data-id]').forEach((row) => {
            row.addEventListener('dragstart', () => {
                draggedRow = row;
                row.classList.add('dragging');
            });
            row.addEventListener('dragend', async () => {
                row.classList.remove('dragging');
                draggedRow = null;
                const items = qa('#ppmBody tr[data-id]').map((currentRow, index) => ({
                    id: Number(currentRow.dataset.id),
                    sort_order: ((currentPage - 1) * 30) + index + 1,
                }));
                try {
                    await request(page.dataset.reorderUrl, {
                        method: 'POST',
                        body: JSON.stringify({ items }),
                    });
                    await load(currentPage);
                } catch (error) {
                    alert(error.message);
                    await load(currentPage);
                }
            });
            row.addEventListener('dragover', (event) => {
                event.preventDefault();
                if (!draggedRow || draggedRow === row) return;
                const rectangle = row.getBoundingClientRect();
                body.insertBefore(draggedRow, event.clientY < rectangle.top + rectangle.height / 2 ? row : row.nextSibling);
            });
        });
    }

    q('#ppmSearch').addEventListener('click', () => load(1));
    q('#ppmReset').addEventListener('click', resetFilters);
    q('#ppmKeyword').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') load(1);
    });
    q('#ppmAdd').addEventListener('click', () => openDrawer());
    q('#ppmClose').addEventListener('click', closeDrawer);
    q('#ppmCancel').addEventListener('click', closeDrawer);
    overlay.addEventListener('click', closeDrawer);
    form.addEventListener('submit', save);
    q('#ppmImages').addEventListener('change', (event) => renderNewImages(event.target.files));
    ['stock_quantity', 'reserved_quantity', 'is_stock_managed'].forEach((name) => {
        form.elements[name].addEventListener('input', updateAvailablePreview);
        form.elements[name].addEventListener('change', updateAvailablePreview);
    });

    qa('[data-stat-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            const type = button.dataset.statFilter;
            qa('.ppm-filter input, .ppm-filter select').forEach((element) => {
                element.value = element.id === 'ppmSort' ? 'display_order' : '';
            });
            if (type === 'published') q('#ppmStatus').value = 'published';
            if (type === 'out' || type === 'low') q('#ppmStock').value = type;
            if (type === 'limited') q('#ppmLimitedFilter').value = '1';
            if (type === 'ended') q('#ppmStatus').value = 'ended';
            load(1);
        });
    });

    body.addEventListener('click', (event) => {
        const editId = event.target.dataset.edit;
        const copyId = event.target.dataset.copy;
        const publishId = event.target.dataset.publish;
        const deleteId = event.target.dataset.delete;
        const imageUrl = event.target.closest('[data-image]')?.dataset.image;

        if (editId) openDrawer(rows.find((item) => String(item.id) === String(editId)));
        if (copyId) performAction(`${page.dataset.baseUrl}/${copyId}/duplicate`, 'POST', 'この商品を複製しますか？');
        if (publishId) performAction(`${page.dataset.baseUrl}/${publishId}/publication`, 'PATCH');
        if (deleteId) performAction(`${page.dataset.baseUrl}/${deleteId}`, 'DELETE', 'この商品を削除しますか？\n過去の交換履歴は保持されます。');
        if (imageUrl) {
            q('#ppmLightboxImage').src = imageUrl;
            q('#ppmLightbox').classList.add('open');
            q('#ppmLightbox').setAttribute('aria-hidden', 'false');
        }
    });

    pagination.addEventListener('click', (event) => {
        const pageNumber = Number(event.target.dataset.page || 0);
        if (pageNumber) load(pageNumber);
    });

    q('#ppmLightboxClose').addEventListener('click', () => {
        q('#ppmLightbox').classList.remove('open');
        q('#ppmLightbox').setAttribute('aria-hidden', 'true');
    });

    load();
})();
