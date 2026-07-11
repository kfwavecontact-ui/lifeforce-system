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
            if (!confirm(`${s.title}を削除しますか？\n\n削除すると、この学習日に設定されているステップ・設定・開発メモもすべて削除されます。\nこの操作は取り消すことができず、復元できません。\n\n本当に削除しますか？`)) return;
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
    let session = safeJson(page.dataset.session, {}); session.steps ||= [];
    const csrf = page.dataset.csrfToken, saveUrl = page.dataset.saveUrl;
    const title = page.querySelector('[data-editor-title]'), subtitle = page.querySelector('[data-editor-subtitle]'), showSubtitle = page.querySelector('[data-editor-show-subtitle]'), memo = page.querySelector('[data-editor-memo]'), published = page.querySelector('[data-editor-published]'), complete = page.querySelector('[data-editor-complete]');
    const list = page.querySelector('[data-editor-step-list]'), palette = page.querySelector('[data-step-palette]'), detail = page.querySelector('[data-step-detail-editor]'), detailTitle = page.querySelector('[data-step-detail-title]'), detailFields = page.querySelector('[data-step-detail-fields]');
    let selected = null;
    title.value=session.title||''; subtitle.value=session.subtitle||''; showSubtitle.checked=!!session.show_subtitle; memo.value=session.developer_note||session.memo||''; published.checked=!!session.is_published; complete.checked=!!session.development_complete;

    const defaults = key => ({key,label:{description:'説明',example:'例題',video:'動画',material:'教材',question:'問題',survey:'アンケート',result:'結果',complete:'完了'}[key]||'ステップ',content_title:'',body:'',media_type:'',media_path:'',settings:{},questions:[]});
    function renderSteps(){
        list.innerHTML = session.steps.length ? session.steps.map((s,i)=>`<div class="lle-session-step-card ${selected===i?'is-selected':''}" data-index="${i}"><div class="lle-step-card-main"><span class="lle-step-order">${i+1}</span><strong>${esc(s.label)}</strong><small>${esc(s.content_title||'未設定')}</small></div><div class="lle-step-card-actions"><button data-step-action="edit">編集</button><button data-step-action="up" ${i===0?'disabled':''}>↑</button><button data-step-action="down" ${i===session.steps.length-1?'disabled':''}>↓</button><button class="danger" data-step-action="delete">削除</button></div></div>`).join('') : '<p class="lle-empty-steps">ステップがありません。「＋ ステップ追加」から追加してください。</p>';
        renderDetail();
    }
    function renderDetail(){
        if(selected===null||!session.steps[selected]){detail.hidden=true;return;} detail.hidden=false; const s=session.steps[selected]; detailTitle.textContent=`${s.label}ステップ編集`;
        detailFields.innerHTML=`<div class="lle-session-form-grid"><div class="lle-editor-field"><label>表示名</label><input data-step-field="label" value="${esc(s.label)}"></div><div class="lle-editor-field"><label>コンテンツタイトル</label><input data-step-field="content_title" value="${esc(s.content_title||'')}"></div><div class="lle-editor-field full"><label>本文・説明</label><textarea rows="5" data-step-field="body">${esc(s.body||'')}</textarea></div><div class="lle-editor-field"><label>メディア種別</label><select data-step-field="media_type"><option value="">なし</option><option value="image" ${s.media_type==='image'?'selected':''}>画像</option><option value="pdf" ${s.media_type==='pdf'?'selected':''}>PDF</option><option value="video" ${s.media_type==='video'?'selected':''}>動画</option><option value="url" ${s.media_type==='url'?'selected':''}>URL</option></select></div><div class="lle-editor-field"><label>メディアパス・URL</label><input data-step-field="media_path" value="${esc(s.media_path||'')}"></div><div class="lle-step-specific-note full">ステップ固有設定は、この編集画面内に順次追加します。</div></div>`;
    }
    page.querySelector('[data-step-add-open]').addEventListener('click',()=>palette.hidden=!palette.hidden);
    palette.addEventListener('click',e=>{const b=e.target.closest('[data-step-add]');if(!b)return;session.steps.push(defaults(b.dataset.stepAdd));selected=session.steps.length-1;palette.hidden=true;renderSteps();});
    list.addEventListener('click',e=>{const card=e.target.closest('[data-index]');if(!card)return;const i=Number(card.dataset.index),a=e.target.closest('[data-step-action]')?.dataset.stepAction;if(!a){selected=i;renderSteps();return;}if(a==='edit')selected=i;if(a==='up'&&i>0){[session.steps[i-1],session.steps[i]]=[session.steps[i],session.steps[i-1]];selected=i-1;}if(a==='down'&&i<session.steps.length-1){[session.steps[i+1],session.steps[i]]=[session.steps[i],session.steps[i+1]];selected=i+1;}if(a==='delete'){if(!confirm(`${session.steps[i].label}ステップを削除しますか？\n\nこの操作は取り消すことができず、復元できません。`))return;session.steps.splice(i,1);selected=null;}renderSteps();});
    page.querySelector('[data-step-detail-close]').addEventListener('click',()=>{selected=null;renderSteps();});
    detailFields.addEventListener('input',e=>{const f=e.target.dataset.stepField;if(f&&selected!==null)session.steps[selected][f]=e.target.value;});
    async function save(){session.title=title.value;session.subtitle=subtitle.value;session.show_subtitle=showSubtitle.checked;session.developer_note=memo.value;session.is_published=published.checked;session.development_complete=complete.checked;const res=await fetch(saveUrl,{method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(session)});const data=await res.json().catch(()=>({}));if(!res.ok)throw new Error(data.message||'保存に失敗しました。');alert(data.message||'保存しました。');}
    page.querySelector('[data-session-save]').addEventListener('click',()=>save().catch(e=>alert(e.message)));
    renderSteps();
}
