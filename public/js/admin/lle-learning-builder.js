document.addEventListener('DOMContentLoaded', () => {
    const listPage = document.querySelector('[data-builder-list-page]');
    const editPage = document.querySelector('[data-session-edit-page]');
    if (listPage) initListPage(listPage);
    if (editPage) initEditPage(editPage);
});

function safeJson(value, fallback) { try { return JSON.parse(value || ''); } catch (_) { return fallback; } }
function esc(value) { return String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c])); }
function statusOf(session) { if (session.development_complete) return '完成'; return (session.steps || []).length ? '作成中' : '未着手'; }

function initListPage(page) {
    let sessions = safeJson(page.dataset.sessions, []);
    const body = page.querySelector('[data-daily-session-body]');
    const saveUrl = page.dataset.saveUrl;
    const editTemplate = page.dataset.sessionEditUrlTemplate;
    const csrf = page.dataset.csrfToken;
    const publicationStatus = page.querySelector('[data-publication-status]');
    const persistedPublicationStatus = publicationStatus.value;

    const isDefaultSessionTitle = value => !value || /^(?:第\d+回学習|学習\d+日目|\d+日目)$/.test(String(value));
    const normalize = () => sessions.forEach((s, i) => {
        s.session_no = i + 1;
        if (isDefaultSessionTitle(s.title)) s.title = `${i + 1}日目`;
        s.steps ||= [];
    });
    const renderSummary = () => {
        const total = sessions.length || 1;
        const done = sessions.filter(s => statusOf(s) === '完成').length;
        const published = sessions.filter(s => s.is_published).length;
        const empty = sessions.filter(s => statusOf(s) === '未着手').length;
        const set = (sel, text) => { const el = page.querySelector(sel); if (el) el.textContent = text; };
        set('[data-summary-done]', `${done} / ${sessions.length}`); set('[data-summary-published]', `${published} / ${sessions.length}`); set('[data-summary-empty]', empty);
        [['done',done],['published',published],['empty',empty]].forEach(([k,v]) => { const pct = Math.round(v / total * 100); set(`[data-summary-${k}-percent]`, `${pct}%`); const bar = page.querySelector(`[data-summary-${k}-bar]`); if (bar) bar.style.width = `${pct}%`; });
    };
    const render = () => {
        normalize();
        body.innerHTML = sessions.map((s, i) => {
            const status = statusOf(s);
            const editUrl = s.id ? editTemplate.replace('__SESSION__', s.id) : '#';
            return `<tr>
                <td>${i + 1}日目</td><td><strong>${esc(s.title)}</strong></td><td>${esc(s.subtitle || '—')}</td>
                <td>${s.show_subtitle ? '表示' : '非表示'}</td><td>${(s.steps || []).length}</td>
                <td><span class="lle-status-pill ${status === '完成' ? 'done' : status === '作成中' ? 'drafting' : 'not-started'}">${status}</span></td>
                <td><button type="button" class="lle-publish-toggle ${s.is_published ? 'is-on' : ''}" data-action="publish" data-index="${i}">${s.is_published ? '公開' : '非公開'}</button></td>
                <td><div class="lle-row-actions">
                    ${s.id ? `<a class="lle-row-edit-button" href="${editUrl}">編集</a>` : `<button type="button" class="lle-row-edit-button" disabled title="先に保存してください">編集</button>`}
                    <button type="button" data-action="duplicate" data-index="${i}">複製</button>
                    <button type="button" data-action="up" data-index="${i}" ${i===0?'disabled':''}>↑</button>
                    <button type="button" data-action="down" data-index="${i}" ${i===sessions.length-1?'disabled':''}>↓</button>
                    <button type="button" class="danger" data-action="delete" data-index="${i}">削除</button>
                </div></td></tr>`;
        }).join('');
        renderSummary();
    };
    page.querySelector('[data-session-add]').addEventListener('click', () => { sessions.push({id:null,title:`${sessions.length + 1}日目`,subtitle:'',show_subtitle:false,developer_note:'',is_published:false,development_complete:false,steps:[]}); render(); });
    body.addEventListener('click', e => {
        const btn = e.target.closest('[data-action]'); if (!btn) return;
        const i = Number(btn.dataset.index), action = btn.dataset.action, s = sessions[i];
        if (action === 'publish') s.is_published = !s.is_published;
        if (action === 'duplicate') sessions.splice(i + 1, 0, {...JSON.parse(JSON.stringify(s)), id:null, title:`${i + 2}日目`, is_published:false, development_complete:false});
        if (action === 'up' && i > 0) {
            const [moved] = sessions.splice(i, 1);
            sessions.splice(i - 1, 0, moved);
        }
        if (action === 'down' && i < sessions.length - 1) {
            const [moved] = sessions.splice(i, 1);
            sessions.splice(i + 1, 0, moved);
        }
        if (action === 'delete') {
            if (!confirm(`${s.title}を削除しますか？\n\n削除すると、この学習日に設定されているステップ・設定・備考もすべて削除されます。\nこの操作は取り消すことができず、復元できません。\n\n本当に削除しますか？`)) return;
            if (sessions.length <= 1) return alert('学習日は最低1件必要です。');
            sessions.splice(i,1);
        }
        render();
    });
    async function save() {
        const payload = {publication:{status:publicationStatus.value},sessions:sessions.map(s=>({title:s.title,subtitle:s.subtitle||'',show_subtitle:!!s.show_subtitle,developer_note:s.developer_note||s.memo||'',is_published:!!s.is_published,development_complete:!!s.development_complete,steps:s.steps||[]}))};
        const res = await fetch(saveUrl,{method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(payload)});
        const data = await res.json().catch(()=>({})); if (!res.ok) throw new Error(data.message||'保存に失敗しました。'); alert(data.message||'保存しました。'); location.reload();
    }
    page.querySelectorAll('[data-list-save]').forEach(b=>b.addEventListener('click',()=>save().catch(e=>alert(e.message))));
    render();
}

function initEditPage(page) {
    let session = safeJson(page.dataset.session, {});
    session.steps = Array.isArray(session.steps) ? session.steps : [];

    const $ = selector => page.querySelector(selector);
    const csrf = page.dataset.csrfToken;
    const saveUrl = page.dataset.saveUrl;
    const title = $('[data-editor-title]');
    const subtitle = $('[data-editor-subtitle]');
    const showSubtitle = $('[data-editor-show-subtitle]');
    const memo = $('[data-editor-memo]');
    const published = $('[data-editor-published]');
    const complete = $('[data-editor-complete]');
    const backgroundColor = $('[data-editor-background-color]');
    const backgroundImage = $('[data-editor-background-image]');
    const backgroundFile = $('[data-editor-background-file]');
    const backgroundFileName = $('[data-editor-background-file-name]');
    const backgroundRemove = $('[data-editor-background-remove]');
    const backgroundOpacity = $('[data-editor-background-opacity]');
    const backgroundOpacityValue = $('[data-editor-background-opacity-value]');
    const backgroundDisplay = $('[data-editor-background-display]');
    const backgroundCopyOpen = $('[data-background-copy-open]');
    const backgroundCopyModal = $('[data-background-copy-modal]');
    const backgroundCopyList = $('[data-background-copy-list]');
    const backgroundCopySearch = $('[data-background-copy-search]');
    const backgroundCopyCount = $('[data-background-copy-count]');
    const pageHeading = $('[data-page-heading]');
    const list = $('[data-editor-step-list]');
    const palette = $('[data-step-palette]');
    const detail = $('[data-step-detail-editor]');
    const detailTitle = $('[data-step-detail-title]');
    const detailIcon = $('[data-step-detail-icon]');
    const detailSubtitle = $('[data-step-detail-subtitle]');
    const detailFields = $('[data-step-detail-fields]');
    const saveState = $('[data-save-state]');
    const previewSessionLabel = $('[data-preview-session-label]');
    const previewSessionSubtitle = $('[data-preview-session-subtitle]');
    const previewProgressLabel = $('[data-preview-progress-label]');
    const previewProgressBar = $('[data-preview-progress-bar]');
    const previewContent = $('[data-preview-content]');
    const previewHeaderNavigation = $('[data-preview-header-navigation]');
    const saveButton = $('[data-session-save]');
    const backButton = $('[data-session-back]');
    const unsavedLeaveModal = $('[data-unsaved-leave-modal]');
    const unsavedLeaveYes = $('[data-unsaved-leave-yes]');
    const headerActionsAnchor = $('[data-header-actions-anchor]');
    const headerActions = $('[data-header-actions]');

    const updateHeaderActionsPosition = () => {
        if (!headerActionsAnchor || !headerActions) return;
        const shouldFloat = headerActionsAnchor.getBoundingClientRect().top < 76;
        if (shouldFloat && !headerActions.classList.contains('is-floating')) {
            const rect = headerActions.getBoundingClientRect();
            headerActionsAnchor.style.width = `${Math.ceil(rect.width)}px`;
            headerActionsAnchor.style.height = `${Math.ceil(rect.height)}px`;
            headerActions.classList.add('is-floating');
        } else if (!shouldFloat && headerActions.classList.contains('is-floating')) {
            headerActions.classList.remove('is-floating');
            headerActionsAnchor.style.width = '';
            headerActionsAnchor.style.height = '';
        }
    };
    window.addEventListener('scroll', updateHeaderActionsPosition, {passive:true});
    window.addEventListener('resize', updateHeaderActionsPosition);
    requestAnimationFrame(updateHeaderActionsPosition);

    const labels = {description:'説明',example:'例題',video:'動画',material:'教材',question:'問題',survey:'アンケート',result:'結果',complete:'完了',custom:'自由ステップ'};
    const defaultIcons = {description:'fa-book-open',example:'fa-lightbulb',video:'fa-video',material:'fa-file-alt',question:'fa-question-circle',survey:'fa-clipboard-list',result:'fa-chart-bar',complete:'fa-flag-checkered',custom:'fa-shapes'};
    const componentLabels = {heading:'見出し',text:'テキスト',image:'画像',video:'動画',divider:'区切り線',spacer:'余白'};
    const defaultScreenBackground = () => ({
        color: '#f4f7fb',
        image: '',
        display: 'cover',
        size: 'cover',
        repeat: 'no-repeat',
        position: 'center center',
        opacity: 100
    });
    const displayToCss = display => ({
        cover: {size:'cover', repeat:'no-repeat', position:'center center'},
        contain: {size:'contain', repeat:'no-repeat', position:'center center'},
        center: {size:'auto', repeat:'no-repeat', position:'center center'},
        tile: {size:'auto', repeat:'repeat', position:'top left'},
        'tile-x': {size:'auto', repeat:'repeat-x', position:'top left'},
        'tile-y': {size:'auto', repeat:'repeat-y', position:'top left'},
        width: {size:'100% auto', repeat:'no-repeat', position:'top center'},
        height: {size:'auto 100%', repeat:'no-repeat', position:'center center'},
        stretch: {size:'100% 100%', repeat:'no-repeat', position:'center center'}
    }[display] || {size:'cover', repeat:'no-repeat', position:'center center'});
    const inferDisplay = bg => {
        if (bg.display) return bg.display;
        if (bg.size === 'contain') return 'contain';
        if (bg.size === '100% 100%') return 'stretch';
        if (bg.repeat === 'repeat') return 'tile';
        if (bg.repeat === 'repeat-x') return 'tile-x';
        if (bg.repeat === 'repeat-y') return 'tile-y';
        if (bg.size === '100% auto') return 'width';
        if (bg.size === 'auto 100%') return 'height';
        if (bg.size === 'auto') return 'center';
        return 'cover';
    };
    let selected = session.steps.length ? 0 : null;
    let selectedIcon = 'fa-shapes';

    const componentId = () => `cmp_${Date.now()}_${Math.random().toString(36).slice(2,8)}`;
    const makeComponent = type => ({
        id: componentId(), type,
        content: type === 'heading' ? '見出し' : type === 'text' ? '' : '',
        path: '', width: type === 'image' || type === 'video' ? 640 : null,
        height: type === 'image' || type === 'video' ? 360 : null,
        align: 'left', font_size: type === 'heading' ? 28 : 18,
        spacer_height: type === 'spacer' ? 32 : null
    });

    const legacyComponents = step => {
        if (Array.isArray(step.settings?.components)) return step.settings.components;
        const out = [];
        if (step.content_title) out.push({...makeComponent('heading'), content:step.content_title});
        if (step.key === 'description') {
            if (step.body) out.push({...makeComponent('text'), content:step.body});
            if (step.media_path) out.push({...makeComponent('image'), path:step.media_path, width:Number(step.settings?.specific?.media_width||640), height:Number(step.settings?.specific?.media_height||360)});
        } else if (step.key === 'example') {
            const s=step.settings?.specific||{};
            if (s.example_prompt) out.push({...makeComponent('text'), content:s.example_prompt});
            if (s.prompt_image_path) out.push({...makeComponent('image'), path:s.prompt_image_path, width:Number(s.prompt_image_width||640), height:Number(s.prompt_image_height||360)});
            if (s.example_answer) out.push({...makeComponent('heading'), content:'正解', font_size:24}, {...makeComponent('text'), content:s.example_answer});
            if (s.answer_image_path) out.push({...makeComponent('image'), path:s.answer_image_path, width:Number(s.answer_image_width||640), height:Number(s.answer_image_height||360)});
            if (s.explanation) out.push({...makeComponent('heading'), content:'解説', font_size:24}, {...makeComponent('text'), content:s.explanation});
            if (s.explanation_image_path) out.push({...makeComponent('image'), path:s.explanation_image_path, width:Number(s.explanation_image_width||640), height:Number(s.explanation_image_height||360)});
        } else if (step.body) out.push({...makeComponent('text'), content:step.body});
        return out;
    };

    session.steps = session.steps.map(step => ({
        ...step,
        key: step.key || 'custom',
        label: step.label || labels[step.key] || 'ステップ',
        settings: {
            ...(step.settings || {}),
            step_icon: step.settings?.step_icon || defaultIcons[step.key] || 'fa-shapes',
            components: legacyComponents(step),
            screen_background: (()=>{const bg={...defaultScreenBackground(), ...(step.settings?.screen_background || {})}; bg.display=inferDisplay(bg); return bg;})()
        }
    }));

    title.value=session.title||''; subtitle.value=session.subtitle||''; showSubtitle.checked=!!session.show_subtitle;
    memo.value=session.developer_note||session.memo||''; published.checked=!!session.is_published; complete.checked=!!session.development_complete;

    let isDirty = false;
    const markDirty=()=>{isDirty=true; saveState?.classList.remove('is-saved','is-error'); saveState?.classList.add('is-dirty'); const span=saveState?.querySelector('span'); if(span)span.textContent='未保存';};
    const markSaved=()=>{isDirty=false; saveState?.classList.remove('is-dirty','is-error'); saveState?.classList.add('is-saved'); const span=saveState?.querySelector('span'); if(span)span.textContent='保存済み';};
    const stepIcon = step => step.settings?.step_icon || defaultIcons[step.key] || 'fa-shapes';

    const renderSteps=()=>{
        list.innerHTML=session.steps.length?session.steps.map((step,i)=>`<article class="lle-live-step-card ${selected===i?'is-selected':''}" data-index="${i}" draggable="true"><button type="button" class="lle-live-step-select" data-step-action="edit"><span class="lle-live-step-handle">⠿</span><span class="lle-live-step-icon"><i class="fas ${esc(stepIcon(step))}"></i></span><span class="lle-live-step-copy"><strong>${i+1}. ${esc(step.label)}</strong><small>${step.settings.components.length}コンポーネント</small></span></button><div class="lle-live-step-actions"><button type="button" data-step-action="duplicate" title="複製">⧉</button><button type="button" class="danger" data-step-action="delete" title="削除">×</button></div></article>`).join(''):'<div class="lle-empty-step-state"><strong>ステップがありません</strong><span>「＋ 追加」から作成してください。</span></div>';
        renderDetail(); renderPreview();
    };

    const componentCard=(c,i)=>`<article class="lle-component-card" data-component-index="${i}" draggable="true"><header><span class="lle-component-drag">⠿</span><strong><i class="fas ${c.type==='heading'?'fa-heading':c.type==='text'?'fa-align-left':c.type==='image'?'fa-image':c.type==='video'?'fa-video':c.type==='divider'?'fa-minus':'fa-arrows-alt-v'}"></i> ${componentLabels[c.type]||c.type}</strong><div><button type="button" data-component-action="duplicate" title="複製">⧉</button><button type="button" data-component-action="up" title="上へ">↑</button><button type="button" data-component-action="down" title="下へ">↓</button><button type="button" class="danger" data-component-action="delete" title="削除">×</button></div></header><div class="lle-component-fields">${
        c.type==='heading'?`<label>見出し<input data-component-field="content" value="${esc(c.content||'')}"></label><label>文字サイズ<input type="number" min="16" max="72" data-component-field="font_size" value="${Number(c.font_size||28)}"></label><label>配置<select data-component-field="align"><option value="left" ${c.align==='left'?'selected':''}>左</option><option value="center" ${c.align==='center'?'selected':''}>中央</option><option value="right" ${c.align==='right'?'selected':''}>右</option></select></label>`:
        c.type==='text'?`<label class="full">テキスト<textarea rows="5" data-component-field="content">${esc(c.content||'')}</textarea></label><label>文字サイズ<input type="number" min="12" max="48" data-component-field="font_size" value="${Number(c.font_size||18)}"></label><label>配置<select data-component-field="align"><option value="left" ${c.align==='left'?'selected':''}>左</option><option value="center" ${c.align==='center'?'selected':''}>中央</option><option value="right" ${c.align==='right'?'selected':''}>右</option></select></label>`:
        c.type==='image'?`<label class="full">画像URL<input data-component-field="path" value="${esc(c.path||'')}" placeholder="https://..."></label><label class="full lle-component-upload">画像ファイル<input type="file" accept="image/*" data-component-file></label><label>横幅<input type="number" min="80" max="2000" data-component-field="width" value="${Number(c.width||640)}"></label><label>縦幅<input type="number" min="60" max="1400" data-component-field="height" value="${Number(c.height||360)}"></label><label>配置<select data-component-field="align"><option value="left" ${c.align==='left'?'selected':''}>左</option><option value="center" ${c.align==='center'?'selected':''}>中央</option><option value="right" ${c.align==='right'?'selected':''}>右</option></select></label>`:
        c.type==='video'?`<label class="full">動画URL<input data-component-field="path" value="${esc(c.path||'')}" placeholder="YouTube／YouTube Shorts／Instagram／動画URL"></label><label>横幅<input type="number" min="160" max="2000" data-component-field="width" value="${Number(c.width||640)}"></label><label>縦幅<input type="number" min="90" max="1400" data-component-field="height" value="${Number(c.height||360)}"></label><label>配置<select data-component-field="align"><option value="left" ${c.align==='left'?'selected':''}>左</option><option value="center" ${c.align==='center'?'selected':''}>中央</option><option value="right" ${c.align==='right'?'selected':''}>右</option></select></label>`:
        c.type==='spacer'?`<label>余白の高さ<input type="number" min="8" max="300" data-component-field="spacer_height" value="${Number(c.spacer_height||32)}"></label>`:'<p>区切り線を表示します。</p>'}</div></article>`;

    const renderDetail=()=>{
        if(selected===null||!session.steps[selected]){detail.hidden=true;return;}
        const step=session.steps[selected]; detail.hidden=false;
        detailTitle.textContent=`${step.label} 編集`; detailIcon.innerHTML=`<i class="fas ${esc(stepIcon(step))}"></i>`; detailSubtitle.textContent='コンポーネントを自由に追加・入替・削除できます。';
        detailFields.innerHTML=`<section class="lle-step-basic-settings"><label>ステップ名<input data-step-name maxlength="255" value="${esc(step.label)}"></label><div class="lle-current-step-icon"><span>アイコン</span><button type="button" data-change-step-icon><i class="fas ${esc(stepIcon(step))}"></i> 変更</button></div></section><section class="lle-component-builder"><div class="lle-component-toolbar"><strong>コンポーネント</strong><div>${['heading','text','image','video','divider','spacer'].map(t=>`<button type="button" data-add-component="${t}">＋ ${componentLabels[t]}</button>`).join('')}</div></div><div class="lle-component-list">${step.settings.components.length?step.settings.components.map(componentCard).join(''):'<div class="lle-component-empty"><strong>中身は空です</strong><span>上のボタンからコンポーネントを追加してください。</span></div>'}</div></section>`;
    };

    const youtubeEmbed = url => {
        const value = String(url || '').trim();
        const match = value.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/))([A-Za-z0-9_-]{6,})/i);
        return match ? `https://www.youtube.com/embed/${match[1]}` : '';
    };
    const instagramEmbed = url => {
        const value = String(url || '').trim();
        const match = value.match(/instagram\.com\/(p|reel|tv)\/([A-Za-z0-9_-]+)/i);
        return match ? `https://www.instagram.com/${match[1]}/${match[2]}/embed/` : '';
    };
    const renderComponents=step=>step.settings.components.map(c=>{
        const align=`text-align:${esc(c.align||'left')}`;
        if(c.type==='heading')return `<h2 class="lle-cmp-heading" style="${align};font-size:${Number(c.font_size||28)}px">${esc(c.content||'')}</h2>`;
        if(c.type==='text')return `<div class="lle-cmp-text" style="${align};font-size:${Number(c.font_size||18)}px">${esc(c.content||'').replace(/\n/g,'<br>')}</div>`;
        if(c.type==='image')return c.path?`<div class="lle-cmp-media" style="${align}"><img src="${esc(c.path)}" style="width:${Number(c.width||640)}px;height:${Number(c.height||360)}px;object-fit:fill" alt=""></div>`:'<div class="lle-cmp-missing">画像を設定してください</div>';
        if(c.type==='video'){
            const youtube = youtubeEmbed(c.path);
            const instagram = instagramEmbed(c.path);
            const width = Number(c.width || 640);
            const height = Number(c.height || 360);
            const media = youtube
                ? `<iframe src="${esc(youtube)}" style="width:${width}px;height:${height}px" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>`
                : instagram
                    ? `<iframe class="lle-instagram-embed" src="${esc(instagram)}" style="width:${width}px;height:${height}px" allowtransparency="true" allowfullscreen scrolling="no"></iframe>`
                    : `<video controls playsinline src="${esc(c.path)}" style="width:${width}px;height:${height}px"></video>`;
            return c.path ? `<div class="lle-cmp-media" style="${align}">${media}</div>` : '<div class="lle-cmp-missing">動画URLを設定してください</div>';
        }
        if(c.type==='divider')return '<hr class="lle-cmp-divider">';
        if(c.type==='spacer')return `<div style="height:${Number(c.spacer_height||32)}px"></div>`;
        return '';
    }).join('');

    const selectedBackground = () => {
        if (selected === null || !session.steps[selected]) return defaultScreenBackground();
        session.steps[selected].settings ||= {};
        session.steps[selected].settings.screen_background = {
            ...defaultScreenBackground(),
            ...(session.steps[selected].settings.screen_background || {})
        };
        return session.steps[selected].settings.screen_background;
    };

    const backgroundStyle = () => {
        const bg = selectedBackground();
        const safeImage = bg.image ? `url("${String(bg.image).replace(/["\\]/g, '\\$&')}")` : 'none';
        return {
            backgroundColor: bg.color || '#f4f7fb',
            backgroundImage: 'none',
            '--lle-screen-background-color': bg.color || '#f4f7fb',
            '--lle-screen-background-image': safeImage,
            '--lle-screen-background-size': bg.size || 'cover',
            '--lle-screen-background-repeat': bg.repeat || 'no-repeat',
            '--lle-screen-background-position': bg.position || 'center center',
            '--lle-screen-background-opacity': String(Math.max(0, Math.min(100, Number(bg.opacity ?? 100))) / 100)
        };
    };
    const applyBackground = shell => {
        if (!shell) return;
        const style = backgroundStyle();

        shell.style.backgroundColor = style.backgroundColor;
        shell.style.backgroundImage = 'none';
        shell.style.setProperty('--lle-screen-background-color', style['--lle-screen-background-color']);
        shell.style.setProperty('--lle-screen-background-image', style['--lle-screen-background-image']);
        shell.style.setProperty('--lle-screen-background-size', style['--lle-screen-background-size']);
        shell.style.setProperty('--lle-screen-background-repeat', style['--lle-screen-background-repeat']);
        shell.style.setProperty('--lle-screen-background-position', style['--lle-screen-background-position']);
        shell.style.setProperty('--lle-screen-background-opacity', style['--lle-screen-background-opacity']);
    };

    const renderPreview=()=>{
        const total=session.steps.length, current=selected===null?0:selected+1;
        previewSessionLabel.textContent=title.value.trim()||'学習日'; previewSessionSubtitle.textContent=showSubtitle.checked?subtitle.value.trim():'';
        applyBackground($('[data-preview-shell]'));
        previewProgressLabel.textContent=`ステップ ${current} / ${total}`; previewProgressBar.style.width=total?`${current/total*100}%`:'0%';
        previewHeaderNavigation.innerHTML=selected===null||!session.steps[selected]?'':`${selected>0?'<button type="button" class="lle-preview-header-button is-secondary" data-preview-back>戻る</button>':''}${selected<total-1?'<button type="button" class="lle-preview-header-button is-primary" data-preview-next>次へ</button>':'<button type="button" class="lle-preview-header-button is-primary">完了</button>'}`;
        previewContent.innerHTML=selected===null||!session.steps[selected]?'<div class="lle-preview-placeholder"><h3>ステップを追加してください</h3></div>':`<section class="lle-component-preview">${renderComponents(session.steps[selected])}</section>`;
    };

    const setPalette=open=>{palette.hidden=!open; $('[data-step-add-open]').setAttribute('aria-expanded',open?'true':'false');};
    $('[data-step-add-open]').addEventListener('click',e=>{e.stopPropagation();setPalette(palette.hidden);});
    document.addEventListener('click',e=>{if(!palette.hidden&&!palette.contains(e.target)&&!e.target.closest('[data-step-add-open]'))setPalette(false);});

    palette.addEventListener('click',e=>{
        const icon=e.target.closest('[data-icon-choice]'); if(icon){selectedIcon=icon.dataset.iconChoice; palette.querySelectorAll('[data-icon-choice]').forEach(b=>b.classList.toggle('is-selected',b===icon)); return;}
        const preset=e.target.closest('[data-step-add]');
        if(preset){const key=preset.dataset.stepAdd; session.steps.push({id:null,key,label:labels[key]||preset.dataset.stepLabel||'ステップ',settings:{step_icon:defaultIcons[key]||'fa-shapes',components:[],screen_background:defaultScreenBackground()},questions:[]});selected=session.steps.length-1;markDirty();setPalette(false);loadBackgroundControls();renderSteps();return;}
        const custom=e.target.closest('[data-custom-step-create]');
        if(custom){const input=palette.querySelector('[data-custom-step-name]');const name=input.value.trim();if(!name){alert('ステップ名を入力してください。');input.focus();return;}session.steps.push({id:null,key:'custom',label:name,settings:{step_icon:selectedIcon,components:[],screen_background:defaultScreenBackground()},questions:[]});input.value='';selected=session.steps.length-1;markDirty();setPalette(false);loadBackgroundControls();renderSteps();}
    });

    list.addEventListener('click',e=>{const card=e.target.closest('[data-index]');if(!card)return;const i=Number(card.dataset.index),a=e.target.closest('[data-step-action]')?.dataset.stepAction||'edit';if(a==='edit')selected=i;if(a==='duplicate'){const copy=JSON.parse(JSON.stringify(session.steps[i]));copy.id=null;copy.label+= '（複製）';copy.settings.components.forEach(c=>c.id=componentId());session.steps.splice(i+1,0,copy);selected=i+1;markDirty();}if(a==='delete'){if(!confirm(`${session.steps[i].label}を削除しますか？`))return;session.steps.splice(i,1);selected=session.steps.length?Math.min(i,session.steps.length-1):null;markDirty();}loadBackgroundControls();renderSteps();});


    // ステップ構成のドラッグ＆ドロップ並び替え
    let draggedStepIndex = null;

    const clearStepDropState = () => {
        list.querySelectorAll('.is-dragging, .is-drop-before, .is-drop-after').forEach(card => {
            card.classList.remove('is-dragging', 'is-drop-before', 'is-drop-after');
        });
    };

    list.addEventListener('dragstart', e => {
        const card = e.target.closest('.lle-live-step-card[data-index]');
        if (!card) return;
        draggedStepIndex = Number(card.dataset.index);
        if (!Number.isInteger(draggedStepIndex)) return;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(draggedStepIndex));
        requestAnimationFrame(() => card.classList.add('is-dragging'));
    });

    list.addEventListener('dragover', e => {
        if (draggedStepIndex === null) return;
        const target = e.target.closest('.lle-live-step-card[data-index]');
        if (!target || Number(target.dataset.index) === draggedStepIndex) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        list.querySelectorAll('.is-drop-before, .is-drop-after').forEach(card => {
            card.classList.remove('is-drop-before', 'is-drop-after');
        });
        const rect = target.getBoundingClientRect();
        target.classList.add(e.clientY < rect.top + rect.height / 2 ? 'is-drop-before' : 'is-drop-after');
    });

    list.addEventListener('drop', e => {
        if (draggedStepIndex === null) return;
        const target = e.target.closest('.lle-live-step-card[data-index]');
        if (!target) return;
        e.preventDefault();

        const fromIndex = draggedStepIndex;
        const targetIndex = Number(target.dataset.index);
        if (!Number.isInteger(fromIndex) || !Number.isInteger(targetIndex) || fromIndex === targetIndex) {
            clearStepDropState();
            draggedStepIndex = null;
            return;
        }

        const rect = target.getBoundingClientRect();
        let insertIndex = targetIndex + (e.clientY >= rect.top + rect.height / 2 ? 1 : 0);

        const selectedStep = selected !== null ? session.steps[selected] : null;
        const uploadStep = Number.isInteger(session._backgroundUploadStepIndex)
            ? session.steps[session._backgroundUploadStepIndex]
            : null;
        const backgroundCopySteps = (session._backgroundCopyTargets || [])
            .map(index => session.steps[index])
            .filter(Boolean);

        const [movedStep] = session.steps.splice(fromIndex, 1);
        if (fromIndex < insertIndex) insertIndex -= 1;
        insertIndex = Math.max(0, Math.min(insertIndex, session.steps.length));
        session.steps.splice(insertIndex, 0, movedStep);

        selected = selectedStep ? session.steps.indexOf(selectedStep) : null;
        if (uploadStep) session._backgroundUploadStepIndex = session.steps.indexOf(uploadStep);
        if (backgroundCopySteps.length) {
            session._backgroundCopyTargets = backgroundCopySteps
                .map(step => session.steps.indexOf(step))
                .filter(index => index >= 0);
        }

        draggedStepIndex = null;
        clearStepDropState();
        markDirty();
        loadBackgroundControls();
        renderSteps();
    });

    list.addEventListener('dragend', () => {
        draggedStepIndex = null;
        clearStepDropState();
    });

    detailFields.addEventListener('click',e=>{
        if(selected===null)return;const step=session.steps[selected];
        const add=e.target.closest('[data-add-component]');if(add){step.settings.components.push(makeComponent(add.dataset.addComponent));markDirty();renderDetail();renderPreview();return;}
        if(e.target.closest('[data-change-step-icon]')){setPalette(true);return;}
        const card=e.target.closest('[data-component-index]');const action=e.target.closest('[data-component-action]')?.dataset.componentAction;if(!card||!action)return;const i=Number(card.dataset.componentIndex),arr=step.settings.components;if(action==='delete')arr.splice(i,1);if(action==='duplicate'){const cp=JSON.parse(JSON.stringify(arr[i]));cp.id=componentId();arr.splice(i+1,0,cp);}if(action==='up'&&i>0)[arr[i-1],arr[i]]=[arr[i],arr[i-1]];if(action==='down'&&i<arr.length-1)[arr[i+1],arr[i]]=[arr[i],arr[i+1]];markDirty();renderDetail();renderPreview();
    });
    detailFields.addEventListener('input',e=>{if(selected===null)return;const step=session.steps[selected];if(e.target.matches('[data-step-name]')){step.label=e.target.value;markDirty();renderSteps();return;}const card=e.target.closest('[data-component-index]');const field=e.target.dataset.componentField;if(!card||!field)return;const c=step.settings.components[Number(card.dataset.componentIndex)];c[field]=e.target.type==='number'?Number(e.target.value):e.target.value;markDirty();renderPreview();});
    detailFields.addEventListener('change',e=>{const input=e.target.closest('[data-component-file]');if(!input||!input.files?.[0]||selected===null)return;const i=Number(input.closest('[data-component-index]').dataset.componentIndex),step=session.steps[selected],c=step.settings.components[i];step._uploads ||= {};const slot=`component_${c.id}`;step._uploads[slot]=input.files[0];c.path=URL.createObjectURL(input.files[0]);c.upload_slot=slot;markDirty();renderDetail();renderPreview();});

    previewHeaderNavigation.addEventListener('click',e=>{if(e.target.closest('[data-preview-next]')&&selected<session.steps.length-1){selected++;loadBackgroundControls();renderSteps();}if(e.target.closest('[data-preview-back]')&&selected>0){selected--;loadBackgroundControls();renderSteps();}});
    [title,subtitle,showSubtitle,memo,published,complete].forEach(el=>{el.addEventListener('input',()=>{markDirty();renderPreview();});el.addEventListener('change',()=>{markDirty();renderPreview();});});
    const loadBackgroundControls = () => {
        const bg = selectedBackground();
        if (backgroundColor) backgroundColor.value = bg.color;
        if (backgroundImage) backgroundImage.value = bg.image && !String(bg.image).startsWith('blob:') ? bg.image : '';
        if (backgroundDisplay) backgroundDisplay.value = inferDisplay(bg);
        if (backgroundOpacity) backgroundOpacity.value = String(bg.opacity ?? 100);
        refreshBackgroundUploadState();
    };
    const refreshBackgroundUploadState = () => {
        const bg = selectedBackground();
        const selectedHasPendingUpload = !!session._backgroundUpload && session._backgroundUploadStepIndex === selected;
        if (backgroundFileName) backgroundFileName.textContent = selectedHasPendingUpload ? session._backgroundUpload.name : (bg.image ? '設定済み' : '未選択');
        if (backgroundOpacityValue) backgroundOpacityValue.textContent = `${Math.round(Number(backgroundOpacity?.value ?? bg.opacity ?? 100))}%`;
        if (backgroundRemove) backgroundRemove.disabled = !bg.image && !selectedHasPendingUpload;
    };
    const updateBackground = () => {
        const bg = selectedBackground();
        const display = backgroundDisplay?.value || inferDisplay(bg);
        const css = displayToCss(display);
        session.steps[selected].settings.screen_background = {
            color: backgroundColor?.value || '#f4f7fb',
            image: (session._backgroundUpload && session._backgroundUploadStepIndex === selected) ? bg.image : (backgroundImage?.value.trim() || bg.image || ''),
            display,
            size: css.size,
            repeat: css.repeat,
            position: css.position,
            opacity: Math.max(0, Math.min(100, Number(backgroundOpacity?.value ?? 100)))
        };
        refreshBackgroundUploadState();
        markDirty();
        renderPreview();
        applyBackground(exactShell);
    };
    [backgroundColor, backgroundDisplay, backgroundOpacity].filter(Boolean).forEach(el => {
        el.addEventListener('input', updateBackground);
        el.addEventListener('change', updateBackground);
    });
    backgroundImage?.addEventListener('input', () => {
        if (session._backgroundBlobUrl) URL.revokeObjectURL(session._backgroundBlobUrl);
        session._backgroundBlobUrl = null;
        session._backgroundUpload = null;
        selectedBackground().image = backgroundImage.value.trim();
        if (backgroundFile) backgroundFile.value = '';
        updateBackground();
    });
    backgroundFile?.addEventListener('change', () => {
        const file = backgroundFile.files?.[0];
        if (!file) return;
        if (!/^image\/(jpeg|png|gif|webp)$/i.test(file.type)) { alert('JPG・PNG・GIF・WebP画像を選択してください。'); backgroundFile.value = ''; return; }
        if (session._backgroundBlobUrl) URL.revokeObjectURL(session._backgroundBlobUrl);
        session._backgroundUpload = file;
        session._backgroundUploadStepIndex = selected;
        session._backgroundBlobUrl = URL.createObjectURL(file);
        selectedBackground().image = session._backgroundBlobUrl;
        if (backgroundImage) backgroundImage.value = '';
        updateBackground();
    });
    backgroundRemove?.addEventListener('click', () => {
        if (session._backgroundBlobUrl) URL.revokeObjectURL(session._backgroundBlobUrl);
        session._backgroundBlobUrl = null;
        session._backgroundUpload = null;
        session._backgroundUploadStepIndex = null;
        selectedBackground().image = '';
        if (backgroundImage) backgroundImage.value = '';
        if (backgroundFile) backgroundFile.value = '';
        updateBackground();
    });
    loadBackgroundControls();

    const cloneBackground = bg => JSON.parse(JSON.stringify({...defaultScreenBackground(), ...bg, display: inferDisplay(bg)}));
    const renderBackgroundCopyList = () => {
        if (!backgroundCopyList) return;
        const query = (backgroundCopySearch?.value || '').trim().toLowerCase();
        backgroundCopyList.innerHTML = session.steps.map((step, index) => {
            const isSource = index === selected;
            const matches = !query || `${index + 1} ${step.label}`.toLowerCase().includes(query);
            return `<label class="lle-background-copy-item ${isSource?'is-source':''}" data-copy-row ${matches?'':'hidden'}><input type="checkbox" value="${index}" data-background-copy-target ${isSource?'disabled':''}><span><strong>ステップ${index+1}：${esc(step.label)}</strong>${isSource?'<small>コピー元</small>':''}</span></label>`;
        }).join('');
        updateBackgroundCopyCount();
    };
    const updateBackgroundCopyCount = () => {
        const count = backgroundCopyList?.querySelectorAll('[data-background-copy-target]:checked').length || 0;
        if (backgroundCopyCount) backgroundCopyCount.textContent = `${count}件選択`;
    };
    const closeBackgroundCopy = () => { if(backgroundCopyModal){backgroundCopyModal.hidden=true; document.body.classList.remove('lle-background-copy-open');} };
    backgroundCopyOpen?.addEventListener('click',()=>{
        if(selected===null){alert('コピー元のステップを選択してください。');return;}
        if(session.steps.length<=1){alert('コピー先のステップがありません。');return;}
        if(backgroundCopySearch) backgroundCopySearch.value='';
        renderBackgroundCopyList();
        backgroundCopyModal.hidden=false;
        document.body.classList.add('lle-background-copy-open');
    });
    page.querySelectorAll('[data-background-copy-close]').forEach(el=>el.addEventListener('click',closeBackgroundCopy));
    backgroundCopySearch?.addEventListener('input',renderBackgroundCopyList);
    backgroundCopyList?.addEventListener('change',updateBackgroundCopyCount);
    $('[data-background-copy-select-all]')?.addEventListener('click',()=>{backgroundCopyList?.querySelectorAll('[data-background-copy-target]:not(:disabled)').forEach(cb=>{const row=cb.closest('[data-copy-row]');if(!row?.hasAttribute('hidden'))cb.checked=true;});updateBackgroundCopyCount();});
    $('[data-background-copy-clear]')?.addEventListener('click',()=>{backgroundCopyList?.querySelectorAll('[data-background-copy-target]').forEach(cb=>cb.checked=false);updateBackgroundCopyCount();});
    $('[data-background-copy-apply]')?.addEventListener('click',()=>{
        const targets=[...(backgroundCopyList?.querySelectorAll('[data-background-copy-target]:checked')||[])].map(cb=>Number(cb.value));
        if(!targets.length){alert('コピー先のステップを選択してください。');return;}
        const source=cloneBackground(selectedBackground());
        targets.forEach(index=>{session.steps[index].settings.screen_background=cloneBackground(source);});
        if(session._backgroundUpload && session._backgroundUploadStepIndex===selected){session._backgroundCopyTargets=targets;}
        markDirty();
        closeBackgroundCopy();
        renderPreview();
        alert(`${targets.length}件のステップへ背景設定をコピーしました。`);
    });

    const modal=$('[data-exact-preview-modal]'), exactContent=$('[data-exact-preview-content]'), exactShell=$('[data-exact-preview-shell]');
    $('[data-exact-preview-open]')?.addEventListener('click',()=>{if(!session.steps.length){alert('プレビューするステップがありません。');return;}exactContent.innerHTML=`<section class="lle-component-preview">${renderComponents(session.steps[selected??0])}</section>`;applyBackground(exactShell);modal.hidden=false;document.body.classList.add('lle-exact-preview-open');});
    page.querySelectorAll('[data-exact-preview-close]').forEach(b=>b.addEventListener('click',()=>{modal.hidden=true;document.body.classList.remove('lle-exact-preview-open');}));
    page.querySelectorAll('[data-exact-preview-device]').forEach(b=>b.addEventListener('click',()=>{page.querySelectorAll('[data-exact-preview-device]').forEach(x=>x.classList.remove('is-active'));b.classList.add('is-active');exactShell.dataset.device=b.dataset.exactPreviewDevice;}));

    async function save(){
        session.title=title.value.trim();session.subtitle=subtitle.value.trim();session.show_subtitle=showSubtitle.checked;session.developer_note=memo.value;session.is_published=published.checked;session.development_complete=complete.checked;
        if(!session.title)throw new Error('タイトルを入力してください。');saveButton.disabled=true;saveButton.textContent='保存中…';
        try{const backgroundCopyTargets=[...(session._backgroundCopyTargets||[])];const payload=JSON.parse(JSON.stringify(session,(k,v)=>k.startsWith('_')?undefined:v));const form=new FormData();form.append('_method','PUT');form.append('payload',JSON.stringify(payload));if(session._backgroundUpload){form.append('background_image_file',session._backgroundUpload);form.append('background_step_index',String(session._backgroundUploadStepIndex ?? selected ?? 0));}session.steps.forEach((step,i)=>Object.entries(step._uploads||{}).forEach(([slot,file])=>form.append(`files[${i}][${slot}]`,file)));const res=await fetch(saveUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf},body:form});let data=await res.json().catch(()=>({}));if(!res.ok)throw new Error(data.message||'保存に失敗しました。');if(Array.isArray(data.steps))data.steps.forEach((saved,i)=>{if(!session.steps[i])return;session.steps[i].id=saved.id;session.steps[i].settings=saved.settings||session.steps[i].settings;session.steps[i]._uploads={};});const savedIndex=session._backgroundUploadStepIndex ?? selected ?? 0;const savedBackground=data.steps?.[savedIndex]?.settings?.screen_background;if(savedBackground&&session.steps[savedIndex]){session.steps[savedIndex].settings.screen_background={...defaultScreenBackground(),...savedBackground};if(selected===savedIndex&&backgroundImage)backgroundImage.value=session.steps[savedIndex].settings.screen_background.image||'';}if(savedBackground&&backgroundCopyTargets.length){backgroundCopyTargets.forEach(index=>{if(session.steps[index])session.steps[index].settings.screen_background=cloneBackground(savedBackground);});const copiedPayload=JSON.parse(JSON.stringify(session,(k,v)=>k.startsWith('_')?undefined:v));const copiedForm=new FormData();copiedForm.append('_method','PUT');copiedForm.append('payload',JSON.stringify(copiedPayload));const copiedRes=await fetch(saveUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf},body:copiedForm});data=await copiedRes.json().catch(()=>({}));if(!copiedRes.ok)throw new Error(data.message||'背景設定のコピー保存に失敗しました。');if(Array.isArray(data.steps))data.steps.forEach((saved,i)=>{if(session.steps[i])session.steps[i].settings=saved.settings||session.steps[i].settings;});}if(session._backgroundBlobUrl)URL.revokeObjectURL(session._backgroundBlobUrl);session._backgroundBlobUrl=null;session._backgroundUpload=null;session._backgroundUploadStepIndex=null;session._backgroundCopyTargets=[];if(backgroundFile)backgroundFile.value='';refreshBackgroundUploadState();markSaved();renderSteps();}
        finally{saveButton.disabled=false;saveButton.textContent='保存';}
    }
    saveButton.addEventListener('click',()=>save().catch(e=>alert(e.message)));

    const closeUnsavedLeaveModal=()=>{
        if(!unsavedLeaveModal)return;
        unsavedLeaveModal.hidden=true;
        document.body.classList.remove('lle-unsaved-leave-open');
    };
    backButton?.addEventListener('click',e=>{
        if(!isDirty)return;
        e.preventDefault();
        if(!unsavedLeaveModal)return;
        unsavedLeaveModal.hidden=false;
        document.body.classList.add('lle-unsaved-leave-open');
    });
    page.querySelectorAll('[data-unsaved-leave-no]').forEach(button=>button.addEventListener('click',closeUnsavedLeaveModal));
    unsavedLeaveYes?.addEventListener('click',()=>{
        window.location.href=backButton?.href || page.dataset.backUrl;
    });
    document.addEventListener('keydown',e=>{
        if(e.key==='Escape' && unsavedLeaveModal && !unsavedLeaveModal.hidden)closeUnsavedLeaveModal();
    });

    markSaved();renderSteps();
}

