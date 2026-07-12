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
    const componentLabels = {
        heading:'見出し', text:'テキスト', image:'画像', video:'動画', divider:'区切り線', spacer:'余白',
        timed_display:'制限表示', answer_input:'回答入力', answer_timer:'回答タイマー',
        submit_answer:'回答送信', judgment:'正誤判定', question_set:'問題セット', choice_question_set:'X択問題セット'
    };
    const componentIcons = {
        heading:'fa-heading', text:'fa-align-left', image:'fa-image', video:'fa-video', divider:'fa-minus',
        spacer:'fa-arrows-alt-v', timed_display:'fa-stopwatch', answer_input:'fa-keyboard',
        answer_timer:'fa-hourglass-half', submit_answer:'fa-paper-plane', judgment:'fa-check-circle', question_set:'fa-list-ol', choice_question_set:'fa-list-check'
    };
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
        content: type === 'heading' ? '見出し' : '',
        path: '', width: ['image','video','timed_display'].includes(type) ? 640 : null,
        height: ['image','video','timed_display'].includes(type) ? 360 : null,
        align: 'left', font_size: type === 'heading' ? 28 : 18,
        spacer_height: type === 'spacer' ? 32 : null,
        stimulus_type: type === 'timed_display' ? 'text' : null,
        display_seconds: type === 'timed_display' ? 3 : null,
        start_mode: type === 'timed_display' ? 'auto' : null,
        countdown_mode: type === 'timed_display' ? 'both' : null,
        end_effect: type === 'timed_display' ? 'hide' : null,
        allow_replay: type === 'timed_display' ? false : null,
        input_type: type === 'answer_input' ? 'text' : null,
        question: type === 'answer_input' ? '覚えた内容を入力してください' : null,
        placeholder: type === 'answer_input' ? '' : null,
        required: type === 'answer_input' ? true : null,
        max_length: type === 'answer_input' ? 100 : null,
        normalize_fullwidth: type === 'answer_input' ? true : null,
        answer_seconds: type === 'answer_timer' ? 10 : null,
        timer_display: type === 'answer_timer' ? 'both' : null,
        timer_start: type === 'answer_timer' ? 'display_end' : null,
        timeout_action: type === 'answer_timer' ? 'incorrect' : null,
        warning_seconds: type === 'answer_timer' ? 3 : null,
        button_label: type === 'submit_answer' ? '回答する' : null,
        correct_answer: type === 'judgment' ? '' : null,
        match_mode: type === 'judgment' ? 'exact' : null,
        ignore_spaces: type === 'judgment' ? true : null,
        ignore_case: type === 'judgment' ? true : null,
        success_message: type === 'judgment' ? '正解です！' : null,
        failure_message: type === 'judgment' ? 'もう一度確認してみましょう。' : null,
        show_correct_answer: type === 'judgment' ? true : null,
        set_title: type === 'question_set' ? '問題セット' : (type === 'choice_question_set' ? 'X択問題セット' : null),
        answer_mode: ['question_set','choice_question_set'].includes(type) ? 'per_question_summary' : null,
        question_order: type === 'question_set' ? 'registered' : null,
        show_result_text: type === 'question_set' ? true : null,
        question_font_size: type === 'question_set' ? 64 : null,
        answer_font_size: type === 'question_set' ? 32 : null,
        result_font_size: type === 'question_set' ? 24 : null,
        questions: type === 'question_set' ? [] : null,
        choice_count: type === 'choice_question_set' ? 4 : null,
        explanation_mode: type === 'choice_question_set' ? 'common' : null,
        choices: type === 'choice_question_set' ? [] : null,
        choice_questions: type === 'choice_question_set' ? [] : null
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

    const option = (value, current, label) => `<option value="${value}" ${String(current)===String(value)?'selected':''}>${label}</option>`;
    const checked = value => value ? 'checked' : '';
    const componentFields = c => {
        if(c.type==='heading') return `<label>見出し<input data-component-field="content" value="${esc(c.content||'')}"></label><label>文字サイズ<input type="number" min="16" max="72" data-component-field="font_size" value="${Number(c.font_size||28)}"></label><label>配置<select data-component-field="align">${option('left',c.align,'左')}${option('center',c.align,'中央')}${option('right',c.align,'右')}</select></label>`;
        if(c.type==='text') return `<label class="full">テキスト<textarea rows="5" data-component-field="content">${esc(c.content||'')}</textarea></label><label>文字サイズ<input type="number" min="12" max="48" data-component-field="font_size" value="${Number(c.font_size||18)}"></label><label>配置<select data-component-field="align">${option('left',c.align,'左')}${option('center',c.align,'中央')}${option('right',c.align,'右')}</select></label>`;
        if(c.type==='image') return `<label class="full">画像URL<input data-component-field="path" value="${esc(c.path||'')}" placeholder="https://..."></label><label class="full lle-component-upload">画像ファイル<input type="file" accept="image/*" data-component-file></label><label>横幅<input type="number" min="80" max="2000" data-component-field="width" value="${Number(c.width||640)}"></label><label>縦幅<input type="number" min="60" max="1400" data-component-field="height" value="${Number(c.height||360)}"></label><label>配置<select data-component-field="align">${option('left',c.align,'左')}${option('center',c.align,'中央')}${option('right',c.align,'右')}</select></label>`;
        if(c.type==='video') return `<label class="full">動画URL<input data-component-field="path" value="${esc(c.path||'')}" placeholder="YouTube／YouTube Shorts／Instagram／動画URL"></label><label>横幅<input type="number" min="160" max="2000" data-component-field="width" value="${Number(c.width||640)}"></label><label>縦幅<input type="number" min="90" max="1400" data-component-field="height" value="${Number(c.height||360)}"></label><label>配置<select data-component-field="align">${option('left',c.align,'左')}${option('center',c.align,'中央')}${option('right',c.align,'右')}</select></label>`;
        if(c.type==='timed_display') return `
            <label>表示内容<select data-component-field="stimulus_type">${option('text',c.stimulus_type,'文字・数字')}${option('image',c.stimulus_type,'画像')}</select></label>
            <label class="full">表示する文字・数字<textarea rows="3" data-component-field="content" placeholder="例：583194">${esc(c.content||'')}</textarea></label>
            <label class="full">画像URL<input data-component-field="path" value="${esc(c.path||'')}" placeholder="画像を使う場合のみ指定"></label>
            <label class="full lle-component-upload">画像ファイル<input type="file" accept="image/*" data-component-file></label>
            <label>横幅<input type="number" min="80" max="2000" data-component-field="width" value="${Number(c.width||640)}"></label>
            <label>縦幅<input type="number" min="60" max="1400" data-component-field="height" value="${Number(c.height||360)}"></label>
            <label>表示時間（秒）<input type="number" min="0.1" max="600" step="0.1" data-component-field="display_seconds" value="${Number(c.display_seconds||3)}"></label>
            <label>開始方法<select data-component-field="start_mode">${option('auto',c.start_mode,'ステップ表示時に自動開始')}${option('button',c.start_mode,'開始ボタンを押して開始')}</select></label>
            <label>カウントダウン<select data-component-field="countdown_mode">${option('none',c.countdown_mode,'表示しない')}${option('number',c.countdown_mode,'数字')}${option('bar',c.countdown_mode,'プログレスバー')}${option('both',c.countdown_mode,'数字＋プログレスバー')}</select></label>
            <label>終了後<select data-component-field="end_effect">${option('hide',c.end_effect,'非表示')}${option('blur',c.end_effect,'ぼかす')}${option('mask',c.end_effect,'黒塗り')}</select></label>
            <label class="lle-inline-check"><input type="checkbox" data-component-field="allow_replay" ${checked(c.allow_replay)}> 再表示を許可する</label>`;
        if(c.type==='answer_input') return `
            <label class="full">質問文<input data-component-field="question" value="${esc(c.question||'')}"></label>
            <label>入力形式<select data-component-field="input_type">${option('text',c.input_type,'1行テキスト')}${option('number',c.input_type,'数字')}</select></label>
            <label>プレースホルダー<input data-component-field="placeholder" value="${esc(c.placeholder||'')}"></label>
            <label>最大文字数・桁数<input type="number" min="1" max="1000" data-component-field="max_length" value="${Number(c.max_length||100)}"></label>
            <label class="lle-inline-check"><input type="checkbox" data-component-field="required" ${checked(c.required)}> 必須回答</label>
            <label class="lle-inline-check"><input type="checkbox" data-component-field="normalize_fullwidth" ${checked(c.normalize_fullwidth)}> 全角英数字を半角へ変換</label>`;
        if(c.type==='answer_timer') return `
            <label>制限時間（秒）<input type="number" min="1" max="3600" data-component-field="answer_seconds" value="${Number(c.answer_seconds||10)}"></label>
            <label>開始条件<select data-component-field="timer_start">${option('display_end',c.timer_start,'制限表示の終了後')}${option('step_open',c.timer_start,'ステップ表示時')}${option('input_focus',c.timer_start,'入力欄を選択した時')}</select></label>
            <label>表示方法<select data-component-field="timer_display">${option('none',c.timer_display,'表示しない')}${option('number',c.timer_display,'数字')}${option('bar',c.timer_display,'プログレスバー')}${option('both',c.timer_display,'数字＋プログレスバー')}</select></label>
            <label>時間切れ時<select data-component-field="timeout_action">${option('incorrect',c.timeout_action,'不正解として確定')}${option('unanswered',c.timeout_action,'未回答として確定')}${option('submit',c.timeout_action,'入力内容を自動送信')}</select></label>
            <label>警告を始める残り秒数<input type="number" min="0" max="60" data-component-field="warning_seconds" value="${Number(c.warning_seconds||3)}"></label>`;
        if(c.type==='submit_answer') return `<label>ボタン表示名<input data-component-field="button_label" value="${esc(c.button_label||'回答する')}"></label>`;
        if(c.type==='judgment') return `
            <label class="full">正解<input data-component-field="correct_answer" value="${esc(c.correct_answer||'')}" placeholder="複数正解は | で区切る"></label>
            <label>判定方法<select data-component-field="match_mode">${option('exact',c.match_mode,'完全一致')}${option('partial',c.match_mode,'部分一致')}</select></label>
            <label class="lle-inline-check"><input type="checkbox" data-component-field="ignore_spaces" ${checked(c.ignore_spaces)}> 空白を無視する</label>
            <label class="lle-inline-check"><input type="checkbox" data-component-field="ignore_case" ${checked(c.ignore_case)}> 大文字・小文字を区別しない</label>
            <label class="full">正解時の表示<input data-component-field="success_message" value="${esc(c.success_message||'正解です！')}"></label>
            <label class="full">不正解時の表示<input data-component-field="failure_message" value="${esc(c.failure_message||'もう一度確認してみましょう。')}"></label>
            <label class="lle-inline-check"><input type="checkbox" data-component-field="show_correct_answer" ${checked(c.show_correct_answer)}> 不正解時に正解を表示する</label>`;
        if(c.type==='question_set') {
            if(c.answer_mode==='per_question') c.answer_mode='per_question_feedback';
            if(c.answer_mode==='sequential_batch') c.answer_mode='per_question_summary';
            c.answer_mode = c.answer_mode || 'per_question_summary';
            c.questions = Array.isArray(c.questions) ? c.questions : [];
            c.questions.forEach(q => {
                if (q.question_font_size == null) q.question_font_size = Number(c.question_font_size || 64);
                if (q.answer_font_size == null) q.answer_font_size = Number(c.answer_font_size || 32);
                if (q.result_font_size == null) q.result_font_size = Number(c.result_font_size || 24);
            });
            const selectedQuestionIndex = Math.min(Math.max(Number(c._editor_question_index ?? 0), 0), Math.max(c.questions.length - 1, 0));
            c._editor_question_index = selectedQuestionIndex;
            const selectedQuestion = c.questions[selectedQuestionIndex] || null;
            const rows = c.questions.length ? c.questions.map((q, qi) => {
                const questionText = (q.content || q.path || '未設定').replace(/\n/g, ' ');
                const answerType = q.answer_type === 'number' ? '数字入力' : 'テキスト入力';
                const explanationText = String(q.explanation || '').trim();
                return `
                <tr class="${qi===selectedQuestionIndex?'is-selected':''}" data-question-index="${qi}">
                    <td class="lle-question-table-number"><span>${qi+1}</span></td>
                    <td>
                        <div class="lle-question-table-block lle-question-table-block--question">
                            <span class="lle-question-table-label">問題</span>
                            <strong class="lle-question-table-content" title="${esc(q.content||q.path||'未設定')}">${esc(questionText)}</strong>
                            <small>${q.stimulus_type==='image'?'画像問題':'文字・数字'}・表示 ${q.no_display_time?'時間制限なし':`${Number(q.display_seconds||3)}秒`}</small>
                        </div>
                    </td>
                    <td>
                        <div class="lle-question-table-block lle-question-table-block--answer">
                            <span class="lle-question-table-label">回答</span>
                            <strong>${answerType}</strong>
                            <small>制限 ${q.no_answer_time?'なし':(Number(q.answer_seconds||0)>0?`${Number(q.answer_seconds)}秒`:'なし')}</small>
                        </div>
                    </td>
                    <td>
                        <div class="lle-question-table-block lle-question-table-block--correct">
                            <span class="lle-question-table-label">正解</span>
                            <strong class="lle-question-table-answer" title="${esc(q.correct_answer||'')}">${esc(q.correct_answer||'未設定')}</strong>
                        </div>
                    </td>
                    <td>
                        <div class="lle-question-table-block lle-question-table-block--explanation">
                            <span class="lle-question-table-label">解説</span>
                            <strong>${explanationText ? '設定済み' : '未設定'}</strong>
                            ${explanationText ? `<small title="${esc(explanationText)}">${esc(explanationText.replace(/\n/g,' '))}</small>` : '<small>結果画面には表示されません</small>'}
                        </div>
                    </td>
                    <td><div class="lle-question-table-actions">
                        <button type="button" class="primary" data-question-action="edit">編集</button>
                        <button type="button" data-question-action="duplicate" title="複製">複製</button>
                        <button type="button" data-question-action="up" title="上へ" ${qi===0?'disabled':''}>↑</button>
                        <button type="button" data-question-action="down" title="下へ" ${qi===c.questions.length-1?'disabled':''}>↓</button>
                        <button type="button" class="danger" data-question-action="delete" title="削除">削除</button>
                    </div></td>
                </tr>`;
            }).join('') : '<tr><td colspan="6" class="lle-question-set-empty">問題がありません。「問題を追加」から作成してください。</td></tr>';
            const modal = c._editor_modal_open && selectedQuestion ? `
                <div class="lle-question-edit-modal" data-question-modal>
                    <div class="lle-question-edit-backdrop" data-question-modal-close></div>
                    <section class="lle-question-edit-dialog" role="dialog" aria-modal="true" aria-label="問題 ${selectedQuestionIndex+1} の編集" data-question-index="${selectedQuestionIndex}">
                        <header>
                            <div><strong>問題 ${selectedQuestionIndex+1} を編集</strong><span>変更内容は入力と同時に反映されます。</span></div>
                            <button type="button" data-question-modal-close aria-label="閉じる">×</button>
                        </header>
                        <div class="lle-question-detail-sections">
                            <section class="lle-question-edit-section lle-question-edit-section--question">
                                <header><span>1</span><div><strong>問題</strong><small>生徒に見せる内容と表示方法を設定します。</small></div></header>
                                <div class="lle-question-detail-grid">
                                    <label>問題形式<select data-question-field="stimulus_type">${option('text',selectedQuestion.stimulus_type,'文字・数字')}${option('image',selectedQuestion.stimulus_type,'画像')}</select></label>
                                    <label>表示時間（秒）<input type="number" min="0.1" max="600" step="0.1" data-question-field="display_seconds" value="${Number(selectedQuestion.display_seconds||3)}" ${selectedQuestion.no_display_time?'disabled':''}></label>
                                    <label class="lle-inline-check"><input type="checkbox" data-question-field="no_display_time" ${checked(selectedQuestion.no_display_time)}> 表示時間を設けない</label>
                                    <label>問題のフォントサイズ（px）<input type="number" min="12" max="160" data-question-field="question_font_size" value="${Number(selectedQuestion.question_font_size||c.question_font_size||64)}"></label>
                                    <label class="full">問題文・表示内容<textarea rows="4" data-question-field="content" placeholder="例：1＋6＝">${esc(selectedQuestion.content||'')}</textarea></label>
                                    <label class="full">画像URL<input data-question-field="path" value="${esc(selectedQuestion.path||'')}" placeholder="画像問題の場合のみ指定します"></label>
                                </div>
                            </section>
                            <section class="lle-question-edit-section lle-question-edit-section--answer">
                                <header><span>2</span><div><strong>回答</strong><small>回答欄の形式・制限時間・文字サイズを設定します。</small></div></header>
                                <div class="lle-question-detail-grid">
                                    <label>回答形式<select data-question-field="answer_type">${option('text',selectedQuestion.answer_type,'1行テキスト')}${option('number',selectedQuestion.answer_type,'数字')}</select></label>
                                    <label>回答制限時間（秒）<input type="number" min="0" max="3600" data-question-field="answer_seconds" value="${Number(selectedQuestion.answer_seconds||10)}" ${selectedQuestion.no_answer_time?'disabled':''}></label>
                                    <label class="lle-inline-check"><input type="checkbox" data-question-field="no_answer_time" ${checked(selectedQuestion.no_answer_time)}> 回答制限時間を設けない</label>
                                    <label>回答欄のフォントサイズ（px）<input type="number" min="12" max="96" data-question-field="answer_font_size" value="${Number(selectedQuestion.answer_font_size||c.answer_font_size||32)}"></label>
                                </div>
                            </section>
                            <section class="lle-question-edit-section lle-question-edit-section--correct">
                                <header><span>3</span><div><strong>正解</strong><small>判定に使用する正解と、結果画面の文字サイズを設定します。</small></div></header>
                                <div class="lle-question-detail-grid">
                                    <label class="full">正解<input data-question-field="correct_answer" value="${esc(selectedQuestion.correct_answer||'')}" placeholder="複数正解は | で区切ります"></label>
                                    <label>結果画面のフォントサイズ（px）<input type="number" min="12" max="72" data-question-field="result_font_size" value="${Number(selectedQuestion.result_font_size||c.result_font_size||24)}"></label>
                                </div>
                            </section>
                            <section class="lle-question-edit-section lle-question-edit-section--explanation">
                                <header><span>4</span><div><strong>解説</strong><small>採点後に生徒へ表示する説明を入力します。</small></div></header>
                                <div class="lle-question-detail-grid">
                                    <label class="full">解説<textarea rows="4" data-question-field="explanation" placeholder="正解の理由や考え方を入力してください">${esc(selectedQuestion.explanation||'')}</textarea></label>
                                </div>
                            </section>
                        </div>
                        <footer><button type="button" class="lle-runtime-primary" data-question-modal-close>編集を閉じる</button></footer>
                    </section>
                </div>` : '';
            return `
                <label class="full">問題タイトル名<input data-component-field="set_title" value="${esc(c.set_title||'問題セット')}"></label>
                <label>回答方式<select data-component-field="answer_mode">
                    ${option('per_question_summary',c.answer_mode,'① 問題 → 回答を繰り返し、最後に結果を一覧表示')}
                    ${option('per_question_feedback',c.answer_mode,'② 問題 → 回答 → 解答・解説を繰り返し、最後に結果を一覧表示')}
                    ${option('list',c.answer_mode,'③ すべての問題を並べて表示し、まとめて回答')}
                    ${option('per_question_feedback_only',c.answer_mode,'④ 問題 → 回答 → 解答・解説のみ（結果一覧なし）')}
                </select></label>
                <label>問題順<select data-component-field="question_order">${option('registered',c.question_order,'登録順')}${option('random',c.question_order,'ランダム')}</select></label>
                <label class="lle-inline-check"><input type="checkbox" data-component-field="show_result_text" ${checked(c.show_result_text)}> 正解・不正解の文言を表示</label>
                <div class="lle-question-set-editor full">
                    <div class="lle-question-set-editor-head"><div><strong>問題一覧（${c.questions.length}問）</strong><span>一覧から編集する問題を選択してください。</span></div><button type="button" class="lle-secondary-button lle-question-add-button" data-question-add>＋ 問題を追加</button></div>
                    <div class="lle-question-table-wrap">
                        <table class="lle-question-table"><thead><tr><th>No</th><th>問題</th><th>回答</th><th>正解</th><th>解説</th><th>操作</th></tr></thead><tbody>${rows}</tbody></table>
                    </div>
                </div>${modal}`;
        }
        if(c.type==='choice_question_set'){
            c.choice_questions ||= [];
            const selectedIndex=Math.max(0,Math.min(Number(c._choice_editor_index||0),Math.max(c.choice_questions.length-1,0)));
            const q=c.choice_questions[selectedIndex];
            const choiceCount=Math.max(2,Math.min(6,Number(c.choice_count||4)));
            c.choice_questions.forEach(item=>{
                item.options ||= [];
                if(item.question_font_size==null)item.question_font_size=48;
                if(item.answer_font_size==null)item.answer_font_size=24;
                if(item.result_font_size==null)item.result_font_size=24;
                if(item.explanation_font_size==null)item.explanation_font_size=18;
                if(item.display_seconds==null)item.display_seconds=3;
                if(item.answer_seconds==null)item.answer_seconds=10;
                if(item.no_display_time==null)item.no_display_time=false;
                if(item.no_answer_time==null)item.no_answer_time=false;
            });
            const rows=c.choice_questions.length?c.choice_questions.map((item,qi)=>{
                const correct=(item.options||[]).find(o=>o.is_correct);
                const problemText=String(item.content||item.path||'未設定').replace(/\n/g,' ');
                const explanationText=c.explanation_mode==='per_choice'
                    ? (item.options||[]).map((o,i)=>`${i+1}. ${o.explanation||''}`).filter(v=>!/\.\s*$/.test(v)).join(' / ')
                    : String(item.explanation||'');
                return `<tr data-choice-question-index="${qi}" class="${qi===selectedIndex?'is-selected':''}">
                    <td class="lle-question-table-number"><span>${qi+1}</span></td>
                    <td><div class="lle-question-table-block lle-question-table-block--question">
                        <span class="lle-question-table-label">問題</span>
                        <strong class="lle-question-table-content" title="${esc(item.content||item.path||'未設定')}">${esc(problemText)}</strong>
                        <small>${item.problem_type==='image'?'画像':item.problem_type==='text_image'?'文字＋画像':'テキスト'}・表示 ${item.no_display_time?'時間制限なし':`${Number(item.display_seconds||3)}秒`}</small>
                    </div></td>
                    <td><div class="lle-question-table-block lle-question-table-block--answer">
                        <span class="lle-question-table-label">回答</span>
                        <strong>${(item.options||[]).length}択</strong>
                        <small>制限 ${item.no_answer_time?'なし':`${Number(item.answer_seconds||10)}秒`}</small>
                    </div></td>
                    <td><div class="lle-question-table-block lle-question-table-block--correct">
                        <span class="lle-question-table-label">正解</span>
                        <strong class="lle-question-table-answer">${esc(correct?.text||(correct?.path?'画像選択肢':'未設定'))}</strong>
                    </div></td>
                    <td><div class="lle-question-table-block lle-question-table-block--explanation">
                        <span class="lle-question-table-label">解説</span>
                        <strong>${explanationText?'設定済み':'未設定'}</strong>
                        <small title="${esc(explanationText)}">${explanationText?esc(explanationText.replace(/\n/g,' ')):'解説は未設定です'}</small>
                    </div></td>
                    <td><div class="lle-question-table-actions">
                        <button type="button" class="primary" data-choice-question-action="edit">編集</button>
                        <button type="button" data-choice-question-action="duplicate">複製</button>
                        <button type="button" data-choice-question-action="up" ${qi===0?'disabled':''}>↑</button>
                        <button type="button" data-choice-question-action="down" ${qi===c.choice_questions.length-1?'disabled':''}>↓</button>
                        <button type="button" class="danger" data-choice-question-action="delete">削除</button>
                    </div></td>
                </tr>`;
            }).join(''):'<tr><td colspan="6" class="lle-question-set-empty">問題がありません。「問題を追加」から作成してください。</td></tr>';
            const modal=(c._choice_editor_open&&q)?(()=>{
                q.options ||= Array.from({length:choiceCount},(_,i)=>({id:componentId(),type:'text',text:'',path:'',is_correct:i===0,explanation:''}));
                while(q.options.length<choiceCount)q.options.push({id:componentId(),type:'text',text:'',path:'',is_correct:false,explanation:''});
                q.options=q.options.slice(0,choiceCount);
                const optionRows=q.options.map((o,oi)=>`<tr data-choice-option-index="${oi}">
                    <td>${oi+1}</td>
                    <td><select data-choice-option-field="type">${option('text',o.type,'テキスト')}${option('image',o.type,'画像')}${option('text_image',o.type,'テキスト＋画像')}</select></td>
                    <td><input data-choice-option-field="text" value="${esc(o.text||'')}" placeholder="選択肢テキスト"></td>
                    <td><input data-choice-option-field="path" value="${esc(o.path||'')}" placeholder="画像URL"><label class="lle-choice-inline-upload">画像を選択<input type="file" accept="image/*" data-choice-option-file></label></td>
                    <td class="lle-choice-correct-cell"><input type="radio" name="choice-correct-${esc(q.id)}" data-choice-option-correct ${o.is_correct?'checked':''} aria-label="選択肢${oi+1}を正解にする"></td>
                    ${c.explanation_mode==='per_choice'?`<td><textarea rows="2" data-choice-option-field="explanation" placeholder="理由">${esc(o.explanation||'')}</textarea></td>`:''}
                </tr>`).join('');
                return `<div class="lle-question-edit-modal" data-choice-question-modal>
                    <div class="lle-question-edit-backdrop" data-choice-question-close></div>
                    <section class="lle-question-edit-dialog lle-choice-question-dialog" data-choice-question-index="${selectedIndex}">
                        <header><div><strong>X択問題 ${selectedIndex+1} を編集</strong><small>問題・回答・正解・解説を整理して設定します。</small></div><button type="button" data-choice-question-close>×</button></header>
                        <div class="lle-question-detail-sections">
                            <section class="lle-question-edit-section lle-question-edit-section--question">
                                <header><span>1</span><div><strong>問題</strong><small>問題内容、表示時間、文字サイズを設定します。</small></div></header>
                                <div class="lle-question-detail-grid">
                                    <label>問題形式<select data-choice-question-field="problem_type">${option('text',q.problem_type,'テキスト')}${option('image',q.problem_type,'画像')}${option('text_image',q.problem_type,'テキスト＋画像')}</select></label>
                                    <label>問題フォントサイズ（px）<input type="number" min="12" max="160" data-choice-question-field="question_font_size" value="${Number(q.question_font_size||48)}"></label>
                                    <label>表示時間（秒）<input type="number" min="0.1" max="600" step="0.1" data-choice-question-field="display_seconds" value="${Number(q.display_seconds||3)}" ${q.no_display_time?'disabled':''}></label>
                                    <label class="lle-inline-check"><input type="checkbox" data-choice-question-field="no_display_time" ${checked(q.no_display_time)}> 表示時間を設けない</label>
                                    <label class="full">問題文<textarea rows="3" data-choice-question-field="content">${esc(q.content||'')}</textarea></label>
                                    <label class="full">問題画像URL<input data-choice-question-field="path" value="${esc(q.path||'')}"></label>
                                    <label class="full lle-component-upload">問題画像アップロード<input type="file" accept="image/*" data-choice-question-file></label>
                                </div>
                            </section>
                            <section class="lle-question-edit-section lle-question-edit-section--answer">
                                <header><span>2</span><div><strong>回答（選択肢）</strong><small>表内で選択肢を直接編集し、正解を1つ選びます。</small></div></header>
                                <div class="lle-question-detail-grid">
                                    <label>回答欄のフォントサイズ（px）<input type="number" min="12" max="96" data-choice-question-field="answer_font_size" value="${Number(q.answer_font_size||24)}"></label>
                                    <label>回答制限時間（秒）<input type="number" min="1" max="3600" data-choice-question-field="answer_seconds" value="${Number(q.answer_seconds||10)}" ${q.no_answer_time?'disabled':''}></label>
                                    <label class="lle-inline-check"><input type="checkbox" data-choice-question-field="no_answer_time" ${checked(q.no_answer_time)}> 回答制限時間を設けない</label>
                                </div>
                                <div class="lle-choice-options-table-wrap"><table class="lle-choice-options-table"><thead><tr><th>No</th><th>形式</th><th>テキスト</th><th>画像</th><th>正解</th>${c.explanation_mode==='per_choice'?'<th>理由</th>':''}</tr></thead><tbody>${optionRows}</tbody></table></div>
                            </section>
                            <section class="lle-question-edit-section lle-question-edit-section--correct">
                                <header><span>3</span><div><strong>正解・結果表示</strong><small>結果画面の文字サイズを設定します。</small></div></header>
                                <div class="lle-question-detail-grid">
                                    <label>結果画面のフォントサイズ（px）<input type="number" min="12" max="96" data-choice-question-field="result_font_size" value="${Number(q.result_font_size||24)}"></label>
                                    <label>解説のフォントサイズ（px）<input type="number" min="12" max="72" data-choice-question-field="explanation_font_size" value="${Number(q.explanation_font_size||18)}"></label>
                                </div>
                            </section>
                            <section class="lle-question-edit-section lle-question-edit-section--explanation">
                                <header><span>4</span><div><strong>解説</strong><small>${c.explanation_mode==='common'?'問題全体の解説を設定します。':'各選択肢の理由は回答表で設定します。'}</small></div></header>
                                ${c.explanation_mode==='common'?`<label class="full">共通解説<textarea rows="4" data-choice-question-field="explanation">${esc(q.explanation||'')}</textarea></label>`:'<p class="lle-choice-explanation-note">各選択肢の理由を、上の表で入力してください。</p>'}
                            </section>
                        </div>
                        <footer><button type="button" class="lle-runtime-primary" data-choice-question-close>編集を閉じる</button></footer>
                    </section>
                </div>`;
            })():'';
            return `<label class="full">問題タイトル名<input data-component-field="set_title" value="${esc(c.set_title||'X択問題セット')}"></label>
                <label>選択肢数<select data-component-field="choice_count">${[2,3,4,5,6].map(n=>option(n,c.choice_count,`${n}択`)).join('')}</select></label>
                <label>解説方式<select data-component-field="explanation_mode">${option('common',c.explanation_mode,'問題ごとに共通解説を1つ')}${option('per_choice',c.explanation_mode,'選択肢ごとに理由を記載')}</select></label>
                <label>回答方式<select data-component-field="answer_mode">${option('per_question_summary',c.answer_mode,'① 問題 → 回答を繰り返し、最後に結果一覧')}${option('per_question_feedback',c.answer_mode,'② 問題 → 回答 → 解答・解説を繰り返し、最後に結果一覧')}${option('list',c.answer_mode,'③ 全問題を並べ、まとめて回答')}${option('per_question_feedback_only',c.answer_mode,'④ 問題 → 回答 → 解答・解説のみ')}</select></label>
                <div class="lle-question-set-editor full">
                    <div class="lle-question-set-editor-head"><div><strong>X択問題一覧（${c.choice_questions.length}問）</strong><span>一覧から編集する問題を選択してください。</span></div><button type="button" class="lle-secondary-button lle-question-add-button" data-choice-question-add>＋ 問題を追加</button></div>
                    <div class="lle-question-table-wrap"><table class="lle-question-table"><thead><tr><th>No</th><th>問題</th><th>回答</th><th>正解</th><th>解説</th><th>操作</th></tr></thead><tbody>${rows}</tbody></table></div>
                </div>${modal}`;
        }
        if(c.type==='spacer') return `<label>余白の高さ<input type="number" min="8" max="300" data-component-field="spacer_height" value="${Number(c.spacer_height||32)}"></label>`;
        return '<p>区切り線を表示します。</p>';
    };

    const componentCard=(c,i)=>`<article class="lle-component-card lle-component-card--${esc(c.type)}" data-component-index="${i}" draggable="true"><header><span class="lle-component-drag">⠿</span><strong><i class="fas ${componentIcons[c.type]||'fa-cube'}"></i> ${componentLabels[c.type]||c.type}</strong><div><button type="button" data-component-action="duplicate" title="複製">⧉</button><button type="button" data-component-action="up" title="上へ">↑</button><button type="button" data-component-action="down" title="下へ">↓</button><button type="button" class="danger" data-component-action="delete" title="削除">×</button></div></header><div class="lle-component-fields">${componentFields(c)}</div></article>`;

    const renderDetail=()=>{
        if(selected===null||!session.steps[selected]){detail.hidden=true;return;}
        const step=session.steps[selected]; detail.hidden=false;
        detailTitle.textContent=`${step.label} 編集`; detailIcon.innerHTML=`<i class="fas ${esc(stepIcon(step))}"></i>`; detailSubtitle.textContent='コンポーネントを自由に追加・入替・削除できます。';
        const basicTypes=['heading','text','image','video','divider','spacer'];
        const questionTypes=['question_set','choice_question_set','timed_display','answer_input','answer_timer','submit_answer','judgment'];
        const toolbarButtons = types => types.map(t=>`<button type="button" data-add-component="${t}"><i class="fas ${componentIcons[t]||'fa-cube'}"></i> ＋ ${componentLabels[t]}</button>`).join('');
        detailFields.innerHTML=`<section class="lle-step-basic-settings"><label>ステップ名<input data-step-name maxlength="255" value="${esc(step.label)}"></label><div class="lle-current-step-icon"><span>アイコン</span><button type="button" data-change-step-icon><i class="fas ${esc(stepIcon(step))}"></i> 変更</button></div></section><section class="lle-component-builder"><div class="lle-component-toolbar"><strong>コンポーネント</strong><div class="lle-component-toolbar-groups"><div><span>基本</span>${toolbarButtons(basicTypes)}</div><div><span>問題・時間・判定</span>${toolbarButtons(questionTypes)}</div></div></div><div class="lle-component-list">${step.settings.components.length?step.settings.components.map(componentCard).join(''):'<div class="lle-component-empty"><strong>中身は空です</strong><span>上のボタンからコンポーネントを追加してください。</span></div>'}</div></section>`;
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
        if(c.type==='timed_display'){
            const seconds=Math.max(.1,Number(c.display_seconds||3));
            const stimulus=c.stimulus_type==='image'
                ? (c.path?`<img src="${esc(c.path)}" style="width:${Number(c.width||640)}px;height:${Number(c.height||360)}px;object-fit:fill" alt="">`:'<div class="lle-cmp-missing">表示する画像を設定してください</div>')
                : `<div class="lle-timed-display-text">${esc(c.content||'').replace(/\n/g,'<br>')}</div>`;
            return `<section class="lle-timed-display" data-runtime-timed-display data-component-id="${esc(c.id)}" data-seconds="${seconds}" data-start-mode="${esc(c.start_mode||'auto')}" data-countdown="${esc(c.countdown_mode||'both')}" data-end-effect="${esc(c.end_effect||'hide')}" data-allow-replay="${c.allow_replay?'1':'0'}">
                <div class="lle-timed-display-status">
                    <strong>表示時間</strong>
                    <span data-timed-number>${seconds.toFixed(seconds%1?1:0)}秒</span>
                </div>
                <div class="lle-timer-bar" data-timed-bar><span></span></div>
                <div class="lle-timed-display-stimulus" data-timed-stimulus>${stimulus}</div>
                <div class="lle-timed-display-actions"><button type="button" class="lle-runtime-primary" data-timed-start>${c.start_mode==='button'?'開始':'再表示'}</button></div>
            </section>`;
        }
        if(c.type==='answer_input')return `<section class="lle-answer-input" data-runtime-answer-input data-component-id="${esc(c.id)}">
            <label><strong>${esc(c.question||'回答を入力してください')}</strong>
            <input type="${c.input_type==='number'?'text':'text'}" inputmode="${c.input_type==='number'?'numeric':'text'}" maxlength="${Number(c.max_length||100)}" placeholder="${esc(c.placeholder||'')}" data-answer-value ${c.required?'required':''}>
            </label></section>`;
        if(c.type==='answer_timer'){
            const seconds=Math.max(1,Number(c.answer_seconds||10));
            return `<section class="lle-answer-timer" data-runtime-answer-timer data-component-id="${esc(c.id)}" data-seconds="${seconds}" data-start="${esc(c.timer_start||'display_end')}" data-display="${esc(c.timer_display||'both')}" data-timeout-action="${esc(c.timeout_action||'incorrect')}" data-warning="${Number(c.warning_seconds||3)}">
                <div class="lle-answer-timer-line"><strong>残り時間</strong><span data-answer-timer-number>${seconds}秒</span></div>
                <div class="lle-timer-bar" data-answer-timer-bar><span></span></div>
            </section>`;
        }
        if(c.type==='submit_answer')return `<div class="lle-submit-answer"><button type="button" class="lle-runtime-primary" data-runtime-submit>${esc(c.button_label||'回答する')}</button></div>`;
        if(c.type==='judgment')return `<section class="lle-judgment" data-runtime-judgment data-component-id="${esc(c.id)}" data-correct-answer="${esc(c.correct_answer||'')}" data-match-mode="${esc(c.match_mode||'exact')}" data-ignore-spaces="${c.ignore_spaces?'1':'0'}" data-ignore-case="${c.ignore_case?'1':'0'}" data-show-correct="${c.show_correct_answer?'1':'0'}" hidden>
            <div class="lle-judgment-result" data-judgment-result></div>
            <template data-success-message>${esc(c.success_message||'正解です！')}</template>
            <template data-failure-message>${esc(c.failure_message||'もう一度確認してみましょう。')}</template>
        </section>`;
        if(c.type==='question_set'){
            const payload=encodeURIComponent(JSON.stringify({
                title:c.set_title||'問題セット', answerMode:c.answer_mode||'per_question_summary', questionOrder:c.question_order||'registered',
                showResultText:c.show_result_text!==false,
                questionFontSize:Number(c.question_font_size||64), answerFontSize:Number(c.answer_font_size||32), resultFontSize:Number(c.result_font_size||24),
                questions:Array.isArray(c.questions)?c.questions:[]
            }));
            return `<section class="lle-question-set-runtime" data-runtime-question-set data-question-set="${payload}"><div class="lle-question-set-loading">問題セットを準備しています…</div></section>`;
        }
        if(c.type==='choice_question_set'){
            const payload=encodeURIComponent(JSON.stringify({title:c.set_title||'X択問題セット',answerMode:c.answer_mode||'per_question_feedback',explanationMode:c.explanation_mode||'common',questions:Array.isArray(c.choice_questions)?c.choice_questions:[]}));
            return `<section class="lle-question-set-runtime lle-choice-set-runtime" data-runtime-choice-set data-choice-set="${payload}"><div class="lle-question-set-loading">X択問題セットを準備しています…</div></section>`;
        }
        if(c.type==='divider')return '<hr class="lle-cmp-divider">';
        if(c.type==='spacer')return `<div style="height:${Number(c.spacer_height||32)}px"></div>`;
        return '';
    }).join('');


    let runtimeCleanups = [];
    const clearRuntime = () => {
        runtimeCleanups.forEach(fn => { try { fn(); } catch (_) {} });
        runtimeCleanups = [];
    };
    const normalizeAnswer = (value, judgment, input) => {
        let result=String(value??'');
        if(input?.dataset.normalizeFullwidth==='1') result=result.replace(/[Ａ-Ｚａ-ｚ０-９]/g,ch=>String.fromCharCode(ch.charCodeAt(0)-0xFEE0));
        if(judgment?.dataset.ignoreSpaces==='1') result=result.replace(/\s+/g,'');
        if(judgment?.dataset.ignoreCase==='1') result=result.toLowerCase();
        return result;
    };
    const initializeRuntime = root => {
        if(!root) return;
        clearRuntime();
        root.querySelectorAll('[data-runtime-question-set]').forEach(box=>{
            let config={}; try{config=JSON.parse(decodeURIComponent(box.dataset.questionSet||''));}catch(_){config={};}
            if(config.answerMode==='per_question') config.answerMode='per_question_feedback';
            if(config.answerMode==='sequential_batch') config.answerMode='per_question_summary';
            let questions=Array.isArray(config.questions)?config.questions.map((q,i)=>({...q,_index:i})):[];
            if(config.questionOrder==='random') questions=questions.sort(()=>Math.random()-.5);
            let current=0, phase='stimulus', timer=null, answers=new Array(questions.length).fill(''), results=new Array(questions.length).fill(null);
            const cleanup=()=>{if(timer)clearInterval(timer);}; runtimeCleanups.push(cleanup);
            const normalize=v=>String(v??'').replace(/[Ａ-Ｚａ-ｚ０-９]/g,ch=>String.fromCharCode(ch.charCodeAt(0)-0xFEE0)).replace(/\s+/g,'').toLowerCase();
            const judge=(q,value)=>String(q.correct_answer||'').split('|').some(a=>normalize(a)===normalize(value));
            const markHtml=correct=>`<span class="lle-answer-mark ${correct?'is-correct':'is-incorrect'}">${correct?'○':'×'}</span>`;
            const textHtml=correct=>config.showResultText===false?'':`<strong>${correct?'正解！':'不正解'}</strong>`;
            const stimulusHtml=q=>q.stimulus_type==='image'?(q.path?`<img src="${esc(q.path)}" alt="">`:'<div class="lle-cmp-missing">画像を設定してください</div>'):`<div class="lle-question-stimulus-text" style="font-size:${Number(q.question_font_size||config.questionFontSize||64)}px">${esc(q.content||'').replace(/\n/g,'<br>')}</div>`;
            const answerInput=(q,i)=>`<input style="font-size:${Number(q.answer_font_size||config.answerFontSize||32)}px" data-qset-answer="${i}" inputmode="${q.answer_type==='number'?'numeric':'text'}" value="${esc(answers[i]||'')}" placeholder="回答を入力">`;
            const resultSummary=()=>`<div class="lle-question-set-summary"><h3>結果</h3><div class="lle-question-score">${results.filter(Boolean).length} / ${questions.length} 問正解</div>${questions.map((q,i)=>`<article style="font-size:${Number(q.result_font_size||config.resultFontSize||24)}px"><div class="lle-question-result-question"><span>問題 ${i+1}</span>${stimulusHtml(q)}</div><div class="lle-question-result-judgment">${markHtml(!!results[i])}${textHtml(!!results[i])}</div><p>あなたの回答：${esc(answers[i]||'未回答')}</p><p>正解：${esc(String(q.correct_answer||'').split('|')[0])}</p>${q.explanation?`<small>${esc(q.explanation)}</small>`:''}</article>`).join('')}</div>`;
            const stopTimer=()=>{if(timer)clearInterval(timer);timer=null;};
            const runCountdown=(seconds,onDone)=>{stopTimer(); const total=Math.max(.1,Number(seconds||3)); const started=performance.now(); timer=setInterval(()=>{const remain=Math.max(0,total-(performance.now()-started)/1000); const n=box.querySelector('[data-qset-countdown]'); const f=box.querySelector('[data-qset-bar-fill]'); if(n)n.textContent=`${remain<10?remain.toFixed(1):Math.ceil(remain)}秒`; if(f)f.style.width=`${remain/total*100}%`; if(remain<=0){stopTimer();onDone();}},50);};
            const renderPerQuestion=()=>{
                const q=questions[current]; if(!q){box.innerHTML=resultSummary();return;}
                if(phase==='stimulus'){
                    if(q.no_display_time){
                        box.innerHTML=`<header><h3>${esc(config.title||'問題セット')}</h3><span>問題 ${current+1} / ${questions.length}</span></header><div class="lle-question-stimulus">${stimulusHtml(q)}</div><div class="lle-question-result-actions"><button type="button" class="lle-runtime-primary" data-qset-start-answer>回答へ</button></div>`;
                        box.querySelector('[data-qset-start-answer]').onclick=()=>{phase='answer';renderPerQuestion();};
                    }else{
                        box.innerHTML=`<header><h3>${esc(config.title||'問題セット')}</h3><span>問題 ${current+1} / ${questions.length}</span></header><div class="lle-question-countdown"><span data-qset-countdown>${Number(q.display_seconds||3)}秒</span><div><i data-qset-bar-fill></i></div></div><div class="lle-question-stimulus">${stimulusHtml(q)}</div>`;
                        runCountdown(q.display_seconds||3,()=>{phase='answer';renderPerQuestion();});
                    }
                }else if(phase==='answer'){
                    box.innerHTML=`<header><h3>${esc(config.title||'問題セット')}</h3><span>問題 ${current+1} / ${questions.length}</span></header><div class="lle-question-answer-panel"><label>回答${answerInput(q,current)}</label><button type="button" class="lle-runtime-primary" data-qset-submit>回答する</button></div>`;
                    box.querySelector('[data-qset-submit]').addEventListener('click',()=>{
                        stopTimer();
                        answers[current]=box.querySelector('[data-qset-answer]').value;
                        results[current]=judge(q,answers[current]);
                        if(config.answerMode==='per_question_summary'){
                            current++;
                            phase='stimulus';
                        }else{
                            phase='result';
                        }
                        renderPerQuestion();
                    });
                    if(!q.no_answer_time&&Number(q.answer_seconds||0)>0){
                        const answerQuestionIndex=current;
                        runCountdown(q.answer_seconds,()=>{
                            if(phase!=='answer'||current!==answerQuestionIndex)return;
                            answers[current]=box.querySelector('[data-qset-answer]')?.value||'';
                            results[current]=judge(q,answers[current]);
                            if(config.answerMode==='per_question_summary'){
                                current++;
                                phase='stimulus';
                            }else{
                                phase='result';
                            }
                            renderPerQuestion();
                        });
                    }
                }else{
                    const correct=!!results[current];
                    const correctAnswer=String(q.correct_answer||'').split('|')[0];
                    box.innerHTML=`<header><h3>${esc(config.title||'問題セット')}</h3><span>問題 ${current+1} / ${questions.length}</span></header>
                        <div class="lle-question-feedback" style="--lle-question-result-font-size:${Number(q.result_font_size||config.resultFontSize||24)}px">
                            <section class="lle-question-feedback-problem">
                                <span class="lle-question-feedback-label">問題</span>
                                <div class="lle-question-feedback-stimulus">${stimulusHtml(q)}</div>
                            </section>
                            <section class="lle-question-feedback-judgment">
                                ${markHtml(correct)}${textHtml(correct)}
                            </section>
                            <div class="lle-question-feedback-grid">
                                <section><span class="lle-question-feedback-label">あなたの回答</span><strong>${esc(answers[current]||'未回答')}</strong></section>
                                <section><span class="lle-question-feedback-label">正解</span><strong>${esc(correctAnswer)}</strong></section>
                            </div>
                            <section class="lle-question-feedback-explanation">
                                <span class="lle-question-feedback-label">解説</span>
                                <p>${q.explanation?esc(q.explanation).replace(/\n/g,'<br>'):'解説は設定されていません。'}</p>
                            </section>
                        </div>
                        ${current===questions.length-1&&config.answerMode==='per_question_feedback_only'?'':`<div class="lle-question-result-actions"><button type="button" class="lle-runtime-primary" data-qset-next>${current===questions.length-1?'結果を見る':'次の問題へ'}</button></div>`}`;
                    const nextButton=box.querySelector('[data-qset-next]');
                    if(nextButton){const next=()=>{stopTimer();if(current===questions.length-1){box.innerHTML=resultSummary();return;}current++;phase='stimulus';renderPerQuestion();};nextButton.addEventListener('click',next);}
                }
            };
            const renderBatchAnswers=()=>{box.innerHTML=`<header><h3>${esc(config.title||'問題セット')}</h3><span>全${questions.length}問</span></header><div class="lle-question-batch-list">${questions.map((q,i)=>`<label><span>問題 ${i+1}</span>${answerInput(q,i)}</label>`).join('')}</div><button type="button" class="lle-runtime-primary" data-qset-batch-submit>まとめて回答する</button>`;box.querySelector('[data-qset-batch-submit]').addEventListener('click',()=>{box.querySelectorAll('[data-qset-answer]').forEach(el=>answers[Number(el.dataset.qsetAnswer)]=el.value);results=questions.map((q,i)=>judge(q,answers[i]));box.innerHTML=resultSummary();});};
            const renderSequential=()=>{const q=questions[current];if(!q){renderBatchAnswers();return;}if(q.no_display_time){box.innerHTML=`<header><h3>${esc(config.title||'問題セット')}</h3><span>問題 ${current+1} / ${questions.length}</span></header><div class="lle-question-stimulus">${stimulusHtml(q)}</div><div class="lle-question-result-actions"><button type="button" class="lle-runtime-primary" data-qset-sequential-next>次の問題へ</button></div>`;box.querySelector('[data-qset-sequential-next]').onclick=()=>{current++;renderSequential();};}else{box.innerHTML=`<header><h3>${esc(config.title||'問題セット')}</h3><span>問題 ${current+1} / ${questions.length}</span></header><div class="lle-question-countdown"><span data-qset-countdown>${Number(q.display_seconds||3)}秒</span><div><i data-qset-bar-fill></i></div></div><div class="lle-question-stimulus">${stimulusHtml(q)}</div>`;runCountdown(q.display_seconds||3,()=>{current++;renderSequential();});}};
            const renderList=()=>{box.innerHTML=`<header><h3>${esc(config.title||'問題セット')}</h3><span>全${questions.length}問</span></header><div class="lle-question-list-mode">${questions.map((q,i)=>`<article><div class="lle-question-list-stimulus">${stimulusHtml(q)}</div><label>回答${answerInput(q,i)}</label></article>`).join('')}</div><button type="button" class="lle-runtime-primary" data-qset-list-submit>まとめて回答する</button>`;box.querySelector('[data-qset-list-submit]').addEventListener('click',()=>{box.querySelectorAll('[data-qset-answer]').forEach(el=>answers[Number(el.dataset.qsetAnswer)]=el.value);results=questions.map((q,i)=>judge(q,answers[i]));box.innerHTML=resultSummary();});};
            if(!questions.length){
                box.innerHTML='<div class="lle-cmp-missing">問題を追加してください</div>';
            }else if(config.answerMode==='list'){
                renderList();
            }else{
                renderPerQuestion();
            }
        });
        root.querySelectorAll('[data-runtime-choice-set]').forEach(box=>{
            let config={};try{config=JSON.parse(decodeURIComponent(box.dataset.choiceSet||''));}catch(_){config={};}
            const questions=Array.isArray(config.questions)?config.questions:[];let current=0,phase='stimulus',timer=null;
            const answers=new Array(questions.length).fill(null),results=new Array(questions.length).fill(null);
            const stopTimer=()=>{if(timer)clearInterval(timer);timer=null;};
            runtimeCleanups.push(stopTimer);
            const runCountdown=(seconds,onDone,prefix='')=>{stopTimer();const total=Math.max(.1,Number(seconds||3));const started=performance.now();timer=setInterval(()=>{const remain=Math.max(0,total-(performance.now()-started)/1000);const n=box.querySelector('[data-choice-countdown]');const f=box.querySelector('[data-choice-bar-fill]');if(n)n.textContent=`${prefix}${remain<10?remain.toFixed(1):Math.ceil(remain)}秒`;if(f)f.style.width=`${remain/total*100}%`;if(remain<=0){stopTimer();onDone();}},50);};
            const qCorrect=(q,a)=>(q.options||[])[a]?.is_correct===true;
            const problemHtml=q=>`<div class="lle-choice-problem" style="font-size:${Number(q.question_font_size||48)}px">${['text','text_image'].includes(q.problem_type)?`<div>${esc(q.content||'').replace(/\n/g,'<br>')}</div>`:''}${['image','text_image'].includes(q.problem_type)&&q.path?`<img src="${esc(q.path)}" alt="">`:''}</div>`;
            const optionHtml=(o,qi,oi,disabled=false,q=null)=>`<label class="lle-choice-runtime-option is-${esc(o.type||'text')}" style="font-size:${Number(q?.answer_font_size||24)}px"><input type="radio" name="choice-${qi}" value="${oi}" data-choice-answer="${qi}" ${disabled?'disabled':''}><span class="lle-choice-option-content">${['image','text_image'].includes(o.type)&&o.path?`<span class="lle-choice-option-image"><img src="${esc(o.path)}" alt=""></span>`:''}${['text','text_image'].includes(o.type)?`<strong class="lle-choice-option-text">${esc(o.text||'')}</strong>`:''}</span></label>`;
            const optionLabel=o=>o?.text|| (o?.path?'画像選択肢':'未回答');
            const compactProblemHtml=q=>`<div class="lle-choice-result-problem">${['image','text_image'].includes(q.problem_type)&&q.path?`<span class="lle-choice-result-thumb"><img src="${esc(q.path)}" alt=""></span>`:''}${['text','text_image'].includes(q.problem_type)?`<strong>${esc(q.content||'').replace(/\n/g,'<br>')}</strong>`:''}</div>`;
            const compactOptionHtml=o=>`<div class="lle-choice-result-option ${o?'':'is-empty'}">${o&&['image','text_image'].includes(o.type)&&o.path?`<span class="lle-choice-result-thumb"><img src="${esc(o.path)}" alt=""></span>`:''}<span>${esc(optionLabel(o))}</span></div>`;
            const resultOptionHtml=(o,oi,selected,correctIndex,q)=>{
                const isSelected=selected===oi;
                const isCorrect=correctIndex===oi;
                const classes=['lle-choice-review-option'];
                if(isSelected) classes.push('is-selected');
                if(isCorrect) classes.push('is-correct');
                if(isSelected&&!isCorrect) classes.push('is-selected-wrong');
                const badges=`${isSelected?'<span class="lle-choice-review-badge is-selected">あなたの回答</span>':''}${isCorrect?'<span class="lle-choice-review-badge is-correct">正解</span>':''}`;
                const reason=config.explanationMode==='per_choice'?(o?.explanation||'理由は設定されていません。'):'';
                return `<article class="${classes.join(' ')}"><div class="lle-choice-review-option-main"><span class="lle-choice-review-index">${oi+1}</span><div class="lle-choice-review-content">${o&&['image','text_image'].includes(o.type)&&o.path?`<span class="lle-choice-result-thumb"><img src="${esc(o.path)}" alt=""></span>`:''}${o&&['text','text_image'].includes(o.type)&&o.text?`<strong>${esc(o.text)}</strong>`:(!o?.path?'<strong>未設定</strong>':'')}</div><div class="lle-choice-review-badges">${badges}</div></div>${config.explanationMode==='per_choice'?`<div class="lle-choice-review-reason" style="font-size:${Number(q.explanation_font_size||18)}px"><span>理由</span><p>${esc(reason).replace(/\n/g,'<br>')}</p></div>`:''}</article>`;
            };
            const feedbackHtml=(q,qi,summaryMode=false)=>{
                const selected=answers[qi];
                const correctIndex=(q.options||[]).findIndex(o=>o.is_correct);
                const ok=selected===correctIndex;
                const selectedOption=(q.options||[])[selected];
                const correctOption=(q.options||[])[correctIndex];
                const explanation=config.explanationMode==='per_choice'?(selectedOption?.explanation||correctOption?.explanation||'理由は設定されていません。'):(q.explanation||'解説は設定されていません。');
                if(summaryMode){
                    const options=(q.options||[]).map((o,oi)=>resultOptionHtml(o,oi,selected,correctIndex,q)).join('');
                    return `<div class="lle-question-feedback is-summary" style="--lle-question-result-font-size:${Number(q.result_font_size||24)}px"><section class="lle-choice-result-question-card"><div class="lle-choice-result-question-meta"><span class="lle-choice-result-number">問題 ${qi+1}</span><section class="lle-question-feedback-judgment"><span class="lle-answer-mark ${ok?'is-correct':'is-incorrect'}">${ok?'○':'×'}</span><strong>${ok?'正解！':'不正解'}</strong></section></div><div class="lle-choice-result-question-body">${compactProblemHtml(q)}</div></section><section class="lle-choice-review-options"><div class="lle-choice-review-title"><strong>選択肢と解説</strong><span>あなたの回答と正解を確認できます。</span></div><div class="lle-choice-review-list">${options}</div></section>${config.explanationMode==='common'?`<section class="lle-question-feedback-explanation" style="font-size:${Number(q.explanation_font_size||18)}px"><span class="lle-question-feedback-label">解説</span><p>${esc(q.explanation||'解説は設定されていません。').replace(/\n/g,'<br>')}</p></section>`:''}</div>`;
                }
                const options=(q.options||[]).map((o,oi)=>resultOptionHtml(o,oi,selected,correctIndex,q)).join('');
                const reviewCard=`<div class="lle-question-feedback is-summary" style="--lle-question-result-font-size:${Number(q.result_font_size||24)}px"><section class="lle-choice-result-question-card"><div class="lle-choice-result-question-meta"><span class="lle-choice-result-number">問題 ${qi+1}</span><section class="lle-question-feedback-judgment"><span class="lle-answer-mark ${ok?'is-correct':'is-incorrect'}">${ok?'○':'×'}</span><strong>${ok?'正解！':'不正解'}</strong></section></div><div class="lle-choice-result-question-body">${compactProblemHtml(q)}</div></section><section class="lle-choice-review-options"><div class="lle-choice-review-title"><strong>選択肢と解説</strong><span>あなたの回答と正解を確認できます。</span></div><div class="lle-choice-review-list">${options}</div></section>${config.explanationMode==='common'?`<section class="lle-question-feedback-explanation" style="font-size:${Number(q.explanation_font_size||18)}px"><span class="lle-question-feedback-label">解説</span><p>${esc(q.explanation||'解説は設定されていません。').replace(/\n/g,'<br>')}</p></section>`:''}</div>`;
                return `<div class="lle-choice-summary-list lle-choice-single-review"><article style="--lle-question-result-font-size:${Number(q.result_font_size||24)}px">${reviewCard}</article></div>`;
            };
            const summary=()=>`<div class="lle-question-set-summary lle-choice-result-summary"><header class="lle-choice-summary-header"><div><span>結果</span><h3>${results.filter(Boolean).length} / ${questions.length} 問正解</h3></div><strong>${questions.length?Math.round(results.filter(Boolean).length/questions.length*100):0}%</strong></header><div class="lle-choice-summary-list">${questions.map((q,i)=>`<article style="--lle-question-result-font-size:${Number(q.result_font_size||24)}px">${feedbackHtml(q,i,true)}</article>`).join('')}</div></div>`;
            const finishCurrent=()=>{
                const checked=box.querySelector(`input[name="choice-${current}"]:checked`);
                answers[current]=checked?Number(checked.value):null;
                results[current]=qCorrect(questions[current],answers[current]);
                stopTimer();
                if(config.answerMode==='per_question_summary'){current++;phase='stimulus';render();}else{phase='feedback';render();}
            };
            const renderList=()=>{box.innerHTML=`<header><h3>${esc(config.title||'X択問題セット')}</h3><span>全${questions.length}問</span></header><div class="lle-choice-list-mode">${questions.map((q,qi)=>`<article>${problemHtml(q)}<div class="lle-choice-options">${(q.options||[]).map((o,oi)=>optionHtml(o,qi,oi,false,q)).join('')}</div></article>`).join('')}</div><div class="lle-question-result-actions"><button type="button" class="lle-runtime-primary" data-choice-batch>まとめて回答する</button></div>`;box.querySelector('[data-choice-batch]').onclick=()=>{questions.forEach((q,qi)=>{const el=box.querySelector(`input[name="choice-${qi}"]:checked`);answers[qi]=el?Number(el.value):null;results[qi]=qCorrect(q,answers[qi]);});box.innerHTML=summary();};};
            const render=()=>{
                stopTimer();
                if(!questions.length){box.innerHTML='<div class="lle-cmp-missing">問題を追加してください</div>';return;}
                if(config.answerMode==='list'){renderList();return;}
                if(current>=questions.length){box.innerHTML=summary();return;}
                const q=questions[current];
                if(phase==='stimulus'){
                    if(q.no_display_time){
                        box.innerHTML=`<header><h3>${esc(config.title||'X択問題セット')}</h3><span>問題 ${current+1} / ${questions.length}</span></header>${problemHtml(q)}<div class="lle-question-result-actions"><button type="button" class="lle-runtime-primary" data-choice-start-answer>回答へ</button></div>`;
                        box.querySelector('[data-choice-start-answer]').onclick=()=>{phase='answer';render();};
                    }else{
                        box.innerHTML=`<header><h3>${esc(config.title||'X択問題セット')}</h3><span>問題 ${current+1} / ${questions.length}</span></header><div class="lle-question-countdown"><span data-choice-countdown>${Number(q.display_seconds||3)}秒</span><div><i data-choice-bar-fill></i></div></div>${problemHtml(q)}`;
                        runCountdown(q.display_seconds||3,()=>{phase='answer';render();});
                    }
                    return;
                }
                if(phase==='answer'){
                    box.innerHTML=`<header><h3>${esc(config.title||'X択問題セット')}</h3><span>問題 ${current+1} / ${questions.length}</span></header><div class="lle-choice-options">${(q.options||[]).map((o,oi)=>optionHtml(o,current,oi,false,q)).join('')}</div>${q.no_answer_time?'':`<div class="lle-question-countdown lle-question-countdown--answer"><span data-choice-countdown>残り ${Number(q.answer_seconds||10)}秒</span><div><i data-choice-bar-fill></i></div></div>`}<div class="lle-question-result-actions"><button type="button" class="lle-runtime-primary" data-choice-submit>回答する</button></div>`;
                    box.querySelector('[data-choice-submit]').onclick=()=>{if(!box.querySelector(`input[name="choice-${current}"]:checked`))return;finishCurrent();};
                    if(!q.no_answer_time&&Number(q.answer_seconds||0)>0)runCountdown(q.answer_seconds,finishCurrent,'残り ');
                    return;
                }
                const isLastFeedbackOnly=current===questions.length-1&&config.answerMode==='per_question_feedback_only';
                box.innerHTML=`<header><h3>${esc(config.title||'X択問題セット')}</h3><span>問題 ${current+1} / ${questions.length}</span></header>${feedbackHtml(q,current)}${isLastFeedbackOnly?'':`<div class="lle-question-result-actions"><button type="button" class="lle-runtime-primary" data-choice-next>${current===questions.length-1?'結果を見る':'次の問題へ'}</button></div>`}`;
                const nextButton=box.querySelector('[data-choice-next]');
                if(nextButton) nextButton.onclick=()=>{if(current===questions.length-1){box.innerHTML=summary();return;}current++;phase='stimulus';render();};
            };
            render();
        });
        const answerInputs=[...root.querySelectorAll('[data-runtime-answer-input]')];
        answerInputs.forEach(box=>{
            const component=session.steps[selected??0]?.settings?.components?.find(c=>c.id===box.dataset.componentId);
            const input=box.querySelector('[data-answer-value]');
            if(input) input.dataset.normalizeFullwidth=component?.normalize_fullwidth?'1':'0';
        });
        const judgments=[...root.querySelectorAll('[data-runtime-judgment]')];
        const showJudgment = forced => {
            const input=answerInputs[0]?.querySelector('[data-answer-value]');
            const raw=input?.value??'';
            const judgment=judgments[0];
            if(!judgment) return;
            const answers=String(judgment.dataset.correctAnswer||'').split('|').map(v=>normalizeAnswer(v,judgment,input));
            const actual=normalizeAnswer(raw,judgment,input);
            const correct=forced==='incorrect'?false:forced==='unanswered'?false:answers.some(answer=>judgment.dataset.matchMode==='partial'?actual.includes(answer):actual===answer);
            const result=judgment.querySelector('[data-judgment-result]');
            const success=judgment.querySelector('[data-success-message]')?.content?.textContent||'正解です！';
            const failure=judgment.querySelector('[data-failure-message]')?.content?.textContent||'もう一度確認してみましょう。';
            judgment.hidden=false;
            judgment.classList.toggle('is-correct',correct);
            judgment.classList.toggle('is-incorrect',!correct);
            const suffix=!correct&&judgment.dataset.showCorrect==='1'&&answers[0]?`<small>正解：${esc(String(judgment.dataset.correctAnswer||'').split('|')[0])}</small>`:'';
            result.innerHTML=`<strong>${esc(correct?success:(forced==='unanswered'?'時間切れ（未回答）':failure))}</strong>${suffix}`;
            root.dispatchEvent(new CustomEvent('lle:answer-submitted',{detail:{correct,raw}}));
        };
        root.querySelectorAll('[data-runtime-submit]').forEach(button=>button.addEventListener('click',()=>showJudgment()));
        const displayTimers=[...root.querySelectorAll('[data-runtime-timed-display]')];
        displayTimers.forEach(box=>{
            let interval=null;
            const total=Math.max(.1,Number(box.dataset.seconds||3));
            const number=box.querySelector('[data-timed-number]');
            const bar=box.querySelector('[data-timed-bar]');
            const barFill=bar?.querySelector('span');
            const stimulus=box.querySelector('[data-timed-stimulus]');
            const startButton=box.querySelector('[data-timed-start]');
            const mode=box.dataset.countdown||'both';
            if(number) number.hidden=!['number','both'].includes(mode);
            if(bar) bar.hidden=!['bar','both'].includes(mode);
            const reset=()=>{
                stimulus?.classList.remove('is-hidden','is-blurred','is-masked');
                if(number) number.textContent=`${total%1?total.toFixed(1):total}秒`;
                if(barFill) barFill.style.width='100%';
            };
            const finish=()=>{
                if(interval) clearInterval(interval);
                interval=null;
                stimulus?.classList.add(box.dataset.endEffect==='blur'?'is-blurred':box.dataset.endEffect==='mask'?'is-masked':'is-hidden');
                if(number) number.textContent='0秒';
                if(barFill) barFill.style.width='0%';
                if(startButton) startButton.hidden=box.dataset.allowReplay!=='1';
                root.dispatchEvent(new CustomEvent('lle:display-ended',{bubbles:true,detail:{componentId:box.dataset.componentId}}));
            };
            const start=()=>{
                if(interval) clearInterval(interval);
                reset();
                if(startButton) startButton.hidden=true;
                const started=performance.now();
                interval=setInterval(()=>{
                    const elapsed=(performance.now()-started)/1000;
                    const remain=Math.max(0,total-elapsed);
                    if(number) number.textContent=`${remain>0&&remain<10?remain.toFixed(1):Math.ceil(remain)}秒`;
                    if(barFill) barFill.style.width=`${remain/total*100}%`;
                    if(remain<=0) finish();
                },50);
            };
            startButton?.addEventListener('click',start);
            runtimeCleanups.push(()=>interval&&clearInterval(interval));
            if(box.dataset.startMode==='auto') setTimeout(start,50); else reset();
        });
        root.querySelectorAll('[data-runtime-answer-timer]').forEach(timer=>{
            let interval=null,started=false;
            const total=Math.max(1,Number(timer.dataset.seconds||10));
            const number=timer.querySelector('[data-answer-timer-number]');
            const bar=timer.querySelector('[data-answer-timer-bar]');
            const fill=bar?.querySelector('span');
            const mode=timer.dataset.display||'both';
            if(number) number.hidden=!['number','both'].includes(mode);
            if(bar) bar.hidden=!['bar','both'].includes(mode);
            const stop=()=>{if(interval)clearInterval(interval);interval=null;};
            const start=()=>{
                if(started)return;started=true;
                const startedAt=performance.now();
                interval=setInterval(()=>{
                    const remain=Math.max(0,total-(performance.now()-startedAt)/1000);
                    if(number) number.textContent=`${Math.ceil(remain)}秒`;
                    if(fill) fill.style.width=`${remain/total*100}%`;
                    timer.classList.toggle('is-warning',remain<=Number(timer.dataset.warning||3));
                    if(remain<=0){
                        stop();
                        answerInputs.forEach(box=>box.querySelectorAll('input,textarea,select').forEach(el=>el.disabled=true));
                        showJudgment(timer.dataset.timeoutAction==='submit'?undefined:timer.dataset.timeoutAction);
                    }
                },100);
            };
            const displayEnd=()=>start();
            const focusStart=()=>start();
            if(timer.dataset.start==='step_open') setTimeout(start,50);
            if(timer.dataset.start==='display_end') root.addEventListener('lle:display-ended',displayEnd,{once:true});
            if(timer.dataset.start==='input_focus') answerInputs.forEach(box=>box.addEventListener('focusin',focusStart,{once:true}));
            root.addEventListener('lle:answer-submitted',stop,{once:true});
            runtimeCleanups.push(stop);
        });
    };

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
        initializeRuntime(previewContent);
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
        const componentCardEl=e.target.closest('[data-component-index]');
        if(componentCardEl){
            const componentIndex=Number(componentCardEl.dataset.componentIndex), component=step.settings.components[componentIndex];
            if(e.target.closest('[data-choice-question-add]')){component.choice_questions ||= [];const count=Math.max(2,Math.min(6,Number(component.choice_count||4)));component.choice_questions.push({id:componentId(),problem_type:'text',content:'',path:'',question_font_size:48,answer_font_size:24,result_font_size:24,explanation_font_size:18,display_seconds:3,no_display_time:false,answer_seconds:10,no_answer_time:false,explanation:'',options:Array.from({length:count},(_,i)=>({id:componentId(),type:'text',text:'',path:'',is_correct:i===0,explanation:''}))});component._choice_editor_index=component.choice_questions.length-1;component._choice_editor_open=true;markDirty();renderDetail();renderPreview();return;}
            if(e.target.closest('[data-choice-question-close]')){component._choice_editor_open=false;renderDetail();return;}
            if(e.target.closest('[data-choice-question-modal]'))return;
            const choiceRow=e.target.closest('[data-choice-question-index]');const choiceAction=e.target.closest('[data-choice-question-action]')?.dataset.choiceQuestionAction;
            if(choiceRow){const qi=Number(choiceRow.dataset.choiceQuestionIndex),arr=component.choice_questions||[];if(!choiceAction||choiceAction==='edit'){component._choice_editor_index=qi;component._choice_editor_open=true;}else if(choiceAction==='delete'){arr.splice(qi,1);component._choice_editor_open=false;}else if(choiceAction==='duplicate'){const cp=JSON.parse(JSON.stringify(arr[qi]));cp.id=componentId();cp.options.forEach(o=>o.id=componentId());arr.splice(qi+1,0,cp);component._choice_editor_index=qi+1;}else if(choiceAction==='up'&&qi>0){[arr[qi-1],arr[qi]]=[arr[qi],arr[qi-1]];component._choice_editor_index=qi-1;}else if(choiceAction==='down'&&qi<arr.length-1){[arr[qi+1],arr[qi]]=[arr[qi],arr[qi+1]];component._choice_editor_index=qi+1;}markDirty();renderDetail();renderPreview();return;}
            if(e.target.closest('[data-question-add]')){component.questions ||= [];component.questions.push({id:componentId(),stimulus_type:'text',content:'',path:'',display_seconds:3,no_display_time:false,answer_type:'text',answer_seconds:10,no_answer_time:false,question_font_size:Number(component.question_font_size||64),answer_font_size:Number(component.answer_font_size||32),result_font_size:Number(component.result_font_size||24),correct_answer:'',explanation:''});component._editor_question_index=component.questions.length-1;markDirty();renderDetail();renderPreview();return;}
            if(e.target.closest('[data-question-modal-close]')){component._editor_modal_open=false;renderDetail();return;}
            // 問題編集モーダル内の入力操作では、問題一覧の行選択や再描画を行わない。
            // 再描画すると input / textarea / select のフォーカスとカーソル位置が失われる。
            if(e.target.closest('[data-question-modal]'))return;
            const questionCard=e.target.closest('[data-question-index]');const questionAction=e.target.closest('[data-question-action]')?.dataset.questionAction;
            if(questionCard&&!questionAction){component._editor_question_index=Number(questionCard.dataset.questionIndex);component._editor_modal_open=true;renderDetail();return;}
            if(questionCard&&questionAction){const qi=Number(questionCard.dataset.questionIndex),arr=component.questions||[];if(questionAction==='edit'){component._editor_question_index=qi;component._editor_modal_open=true;renderDetail();return;}if(questionAction==='delete'){arr.splice(qi,1);component._editor_question_index=Math.min(qi,Math.max(arr.length-1,0));component._editor_modal_open=false;}if(questionAction==='duplicate'){const cp=JSON.parse(JSON.stringify(arr[qi]));cp.id=componentId();arr.splice(qi+1,0,cp);component._editor_question_index=qi+1;}if(questionAction==='up'&&qi>0){[arr[qi-1],arr[qi]]=[arr[qi],arr[qi-1]];component._editor_question_index=qi-1;}if(questionAction==='down'&&qi<arr.length-1){[arr[qi+1],arr[qi]]=[arr[qi],arr[qi+1]];component._editor_question_index=qi+1;}markDirty();renderDetail();renderPreview();return;}
        }
        const card=e.target.closest('[data-component-index]');const action=e.target.closest('[data-component-action]')?.dataset.componentAction;if(!card||!action)return;const i=Number(card.dataset.componentIndex),arr=step.settings.components;if(action==='delete')arr.splice(i,1);if(action==='duplicate'){const cp=JSON.parse(JSON.stringify(arr[i]));cp.id=componentId();if(Array.isArray(cp.questions))cp.questions.forEach(q=>q.id=componentId());if(Array.isArray(cp.choice_questions))cp.choice_questions.forEach(q=>{q.id=componentId();if(Array.isArray(q.options))q.options.forEach(o=>o.id=componentId());});arr.splice(i+1,0,cp);}if(action==='up'&&i>0)[arr[i-1],arr[i]]=[arr[i],arr[i-1]];if(action==='down'&&i<arr.length-1)[arr[i+1],arr[i]]=[arr[i],arr[i+1]];markDirty();renderDetail();renderPreview();
    });
    detailFields.addEventListener('input',e=>{if(selected===null)return;const step=session.steps[selected];if(e.target.matches('[data-step-name]')){step.label=e.target.value;markDirty();renderSteps();return;}const card=e.target.closest('[data-component-index]');if(!card)return;const c=step.settings.components[Number(card.dataset.componentIndex)];const cqModal=e.target.closest('[data-choice-question-index]');const cqField=e.target.dataset.choiceQuestionField;if(cqModal&&cqField&&c.type==='choice_question_set'){const q=(c.choice_questions||[])[Number(cqModal.dataset.choiceQuestionIndex)];if(q){q[cqField]=e.target.type==='checkbox'?e.target.checked:(e.target.type==='number'?Number(e.target.value):e.target.value);markDirty();renderPreview();}return;}const optEl=e.target.closest('[data-choice-option-index]');const optField=e.target.dataset.choiceOptionField;if(optEl&&optField&&c.type==='choice_question_set'){const q=(c.choice_questions||[])[Number(e.target.closest('[data-choice-question-index]').dataset.choiceQuestionIndex)];const o=q?.options?.[Number(optEl.dataset.choiceOptionIndex)];if(o){o[optField]=e.target.value;markDirty();renderPreview();}return;}if(e.target.matches('[data-choice-option-correct]')&&c.type==='choice_question_set'){const q=(c.choice_questions||[])[Number(e.target.closest('[data-choice-question-index]').dataset.choiceQuestionIndex)];if(q){q.options.forEach((o,i)=>o.is_correct=i===Number(e.target.closest('[data-choice-option-index]').dataset.choiceOptionIndex));markDirty();renderPreview();}return;}const qCard=e.target.closest('[data-question-index]');const qField=e.target.dataset.questionField;if(qCard&&qField){const q=(c.questions||[])[Number(qCard.dataset.questionIndex)];if(!q)return;q[qField]=e.target.type==='checkbox'?e.target.checked:e.target.type==='number'?Number(e.target.value):e.target.value;markDirty();renderPreview();return;}const field=e.target.dataset.componentField;if(!field)return;c[field]=e.target.type==='checkbox'?e.target.checked:e.target.type==='number'?Number(e.target.value):e.target.value;markDirty();renderPreview();});
    detailFields.addEventListener('change',e=>{if(selected===null)return;
        if(e.target.matches('[data-question-field="no_display_time"],[data-question-field="no_answer_time"],[data-choice-question-field="no_display_time"],[data-choice-question-field="no_answer_time"]')){
            markDirty();renderDetail();renderPreview();return;
        }const changedCard=e.target.closest('[data-component-index]');if(changedCard&&e.target.matches('[data-component-field="choice_count"],[data-component-field="explanation_mode"]')){const component=session.steps[selected].settings.components[Number(changedCard.dataset.componentIndex)];if(component?.type==='choice_question_set'){component[e.target.dataset.componentField]=e.target.dataset.componentField==='choice_count'?Number(e.target.value):e.target.value;const count=Math.max(2,Math.min(6,Number(component.choice_count||4)));(component.choice_questions||[]).forEach(q=>{q.options ||= [];while(q.options.length<count)q.options.push({id:componentId(),type:'text',text:'',path:'',is_correct:q.options.length===0,explanation:''});q.options=q.options.slice(0,count);if(!q.options.some(o=>o.is_correct)&&q.options[0])q.options[0].is_correct=true;});markDirty();renderDetail();renderPreview();return;}}const choiceFile=e.target.closest('[data-choice-question-file],[data-choice-option-file]');if(choiceFile&&choiceFile.files?.[0]){const card=choiceFile.closest('[data-component-index]'),c=session.steps[selected].settings.components[Number(card.dataset.componentIndex)],qIndex=Number(choiceFile.closest('[data-choice-question-index]').dataset.choiceQuestionIndex),q=c.choice_questions[qIndex];session.steps[selected]._uploads ||= {};if(choiceFile.matches('[data-choice-question-file]')){const slot=`choice_question_${c.id}_${q.id}`;session.steps[selected]._uploads[slot]=choiceFile.files[0];q.path=URL.createObjectURL(choiceFile.files[0]);q.upload_slot=slot;}else{const oi=Number(choiceFile.closest('[data-choice-option-index]').dataset.choiceOptionIndex),o=q.options[oi],slot=`choice_option_${c.id}_${q.id}_${o.id}`;session.steps[selected]._uploads[slot]=choiceFile.files[0];o.path=URL.createObjectURL(choiceFile.files[0]);o.upload_slot=slot;}markDirty();renderDetail();renderPreview();return;}const input=e.target.closest('[data-component-file]');if(!input||!input.files?.[0])return;const i=Number(input.closest('[data-component-index]').dataset.componentIndex),step=session.steps[selected],c=step.settings.components[i];step._uploads ||= {};const slot=`component_${c.id}`;step._uploads[slot]=input.files[0];c.path=URL.createObjectURL(input.files[0]);c.upload_slot=slot;markDirty();renderDetail();renderPreview();});

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
    $('[data-exact-preview-open]')?.addEventListener('click',()=>{if(!session.steps.length){alert('プレビューするステップがありません。');return;}exactContent.innerHTML=`<section class="lle-component-preview">${renderComponents(session.steps[selected??0])}</section>`;applyBackground(exactShell);modal.hidden=false;document.body.classList.add('lle-exact-preview-open');initializeRuntime(exactContent);});
    page.querySelectorAll('[data-exact-preview-close]').forEach(b=>b.addEventListener('click',()=>{modal.hidden=true;document.body.classList.remove('lle-exact-preview-open');}));
    page.querySelectorAll('[data-exact-preview-device]').forEach(b=>b.addEventListener('click',()=>{page.querySelectorAll('[data-exact-preview-device]').forEach(x=>x.classList.remove('is-active'));b.classList.add('is-active');exactShell.dataset.device=b.dataset.exactPreviewDevice;}));

    async function save(){
        session.title=title.value.trim();session.subtitle=subtitle.value.trim();session.show_subtitle=showSubtitle.checked;session.developer_note=memo.value;session.is_published=published.checked;session.development_complete=complete.checked;
        if(!session.title)throw new Error('タイトルを入力してください。');saveButton.disabled=true;saveButton.textContent='保存中…';
        try{const backgroundCopyTargets=[...(session._backgroundCopyTargets||[])];const payload=JSON.parse(JSON.stringify(session,(k,v)=>(k.startsWith('_')||k==='show_result_mark'||k==='result_display_seconds')?undefined:v));const form=new FormData();form.append('_method','PUT');form.append('payload',JSON.stringify(payload));if(session._backgroundUpload){form.append('background_image_file',session._backgroundUpload);form.append('background_step_index',String(session._backgroundUploadStepIndex ?? selected ?? 0));}session.steps.forEach((step,i)=>Object.entries(step._uploads||{}).forEach(([slot,file])=>form.append(`files[${i}][${slot}]`,file)));const res=await fetch(saveUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf},body:form});let data=await res.json().catch(()=>({}));if(!res.ok)throw new Error(data.message||'保存に失敗しました。');if(Array.isArray(data.steps))data.steps.forEach((saved,i)=>{if(!session.steps[i])return;session.steps[i].id=saved.id;session.steps[i].settings=saved.settings||session.steps[i].settings;session.steps[i]._uploads={};});const savedIndex=session._backgroundUploadStepIndex ?? selected ?? 0;const savedBackground=data.steps?.[savedIndex]?.settings?.screen_background;if(savedBackground&&session.steps[savedIndex]){session.steps[savedIndex].settings.screen_background={...defaultScreenBackground(),...savedBackground};if(selected===savedIndex&&backgroundImage)backgroundImage.value=session.steps[savedIndex].settings.screen_background.image||'';}if(savedBackground&&backgroundCopyTargets.length){backgroundCopyTargets.forEach(index=>{if(session.steps[index])session.steps[index].settings.screen_background=cloneBackground(savedBackground);});const copiedPayload=JSON.parse(JSON.stringify(session,(k,v)=>(k.startsWith('_')||k==='show_result_mark'||k==='result_display_seconds')?undefined:v));const copiedForm=new FormData();copiedForm.append('_method','PUT');copiedForm.append('payload',JSON.stringify(copiedPayload));const copiedRes=await fetch(saveUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf},body:copiedForm});data=await copiedRes.json().catch(()=>({}));if(!copiedRes.ok)throw new Error(data.message||'背景設定のコピー保存に失敗しました。');if(Array.isArray(data.steps))data.steps.forEach((saved,i)=>{if(session.steps[i])session.steps[i].settings=saved.settings||session.steps[i].settings;});}if(session._backgroundBlobUrl)URL.revokeObjectURL(session._backgroundBlobUrl);session._backgroundBlobUrl=null;session._backgroundUpload=null;session._backgroundUploadStepIndex=null;session._backgroundCopyTargets=[];if(backgroundFile)backgroundFile.value='';refreshBackgroundUploadState();markSaved();renderSteps();}
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

