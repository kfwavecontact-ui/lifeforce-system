(function(){
    'use strict';

    const PIECES=[
        ['pawn','歩'],['lance','香'],['knight','桂'],['silver','銀'],['gold','金'],['bishop','角'],['rook','飛'],['king','玉'],
        ['tokin','と'],['promotedLance','成香'],['promotedKnight','成桂'],['promotedSilver','成銀'],['horse','馬'],['dragon','龍']
    ];
    const HAND_PIECES=[['pawn','歩'],['lance','香'],['knight','桂'],['silver','銀'],['gold','金'],['bishop','角'],['rook','飛']];
    const GLYPHS={pawn:'歩',lance:'香',knight:'桂',silver:'銀',gold:'金',bishop:'角',rook:'飛',king:'玉',tokin:'と',promotedLance:'杏',promotedKnight:'圭',promotedSilver:'全',horse:'馬',dragon:'龍'};
    const LEGACY_TYPES={P:'pawn',L:'lance',N:'knight',S:'silver',G:'gold',B:'bishop',R:'rook',K:'king','+P':'tokin','+L':'promotedLance','+N':'promotedKnight','+S':'promotedSilver','+B':'horse','+R':'dragon'};
    const emptyBoard=()=>Array.from({length:9},()=>Array(9).fill(null));
    const clone=value=>JSON.parse(JSON.stringify(value));
    const canonicalType=type=>LEGACY_TYPES[String(type||'')]||String(type||'pawn');
    const normalizePiece=piece=>piece&&typeof piece==='object'?{type:canonicalType(piece.type||piece.kind),side:(piece.side||piece.owner)==='white'?'white':'black'}:null;
    const normalizeBoard=board=>Array.from({length:9},(_,r)=>Array.from({length:9},(_,c)=>normalizePiece(board?.[r]?.[c])));
    const initialBoard=()=>{
        const board=emptyBoard();
        const back=['lance','knight','silver','gold','king','gold','silver','knight','lance'];
        back.forEach((type,c)=>{board[0][c]={type,side:'white'};board[8][8-c]={type,side:'black'};});
        board[1][1]={type:'rook',side:'white'}; board[1][7]={type:'bishop',side:'white'};
        board[7][1]={type:'bishop',side:'black'}; board[7][7]={type:'rook',side:'black'};
        for(let c=0;c<9;c++){board[2][c]={type:'pawn',side:'white'};board[6][c]={type:'pawn',side:'black'};}
        return board;
    };
    const normalizeHands=hands=>{
        const result={black:{},white:{}};
        const legacy={pawn:'P',lance:'L',knight:'N',silver:'S',gold:'G',bishop:'B',rook:'R'};
        ['black','white'].forEach(side=>HAND_PIECES.forEach(([type])=>{result[side][type]=Math.max(0,Number(hands?.[side]?.[type]??hands?.[side]?.[legacy[type]]??0));}));
        return result;
    };

    function createBoardEditor(root,rawConfig={},onChange=()=>{}){
        if(!root) throw new Error('盤面エディタの表示先がありません。');
        const api=window.LLEShogiMate||{};
        const normalized=api.normalizeConfig?api.normalizeConfig(rawConfig):rawConfig;
        let state={
            board:normalizeBoard(normalized.board),
            hands:normalizeHands(normalized.hands),
            turn:normalized.turn==='white'?'white':'black',
            reversed:false,
            selected:{type:'pawn',side:'black'},
            erase:false
        };
        const emit=()=>{
            let sfen='';
            try{sfen=api.generateSfen?api.generateSfen(state.board,state.hands,state.turn,1):'';}catch(error){console.warn('[LLE BoardEditor] SFEN生成失敗',error);}
            onChange({...(rawConfig||{}),board:clone(state.board),hands:clone(state.hands),turn:state.turn,sfen});
            renderStatus(sfen);
        };
        const coord=index=>state.reversed?8-index:index;
        const pieceButton=([type,label])=>`<button type="button" data-board-piece="${type}" title="${label}" style="min-width:48px;padding:7px 8px;border:1px solid #c8b38c;border-radius:7px;background:#fff8e8;cursor:pointer">${label}</button>`;
        root.innerHTML=`<section class="lle-shogi-board-editor" style="display:grid;gap:14px;padding:14px;border:1px solid #dcc9a7;border-radius:12px;background:#fffdf8">
            <header style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap"><div><strong style="font-size:16px">盤面エディタ</strong><div style="font-size:12px;color:#6b6254">駒を選び、盤上のマスをクリックして配置します。</div></div><div style="display:flex;gap:8px;flex-wrap:wrap"><button type="button" class="lle-shogi-toolbar-button" data-board-empty>空盤から作成</button><button type="button" class="lle-shogi-toolbar-button is-danger" data-board-reset>盤面をリセット</button></div></header>
            <div style="display:grid;grid-template-columns:minmax(250px,1fr) minmax(220px,310px);gap:16px;align-items:start">
                <div><div data-board-grid class="lle-shogi-admin-board"></div></div>
                <aside style="display:grid;gap:14px">
                    <section><strong>配置する駒</strong><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:7px" data-board-palette>${PIECES.map(pieceButton).join('')}</div></section>
                    <section><strong>駒の向き</strong><div style="display:flex;gap:8px;margin-top:7px"><label><input type="radio" name="lle-board-owner" value="black" checked> 先手</label><label><input type="radio" name="lle-board-owner" value="white"> 後手</label><label><input type="checkbox" data-board-erase> 消去</label></div></section>
                    <section><strong>手番</strong><select data-board-turn style="width:100%;margin-top:7px"><option value="black">先手</option><option value="white">後手</option></select></section>
                    <section><strong>持ち駒</strong><div data-board-hands style="display:grid;gap:7px;margin-top:7px"></div></section>
                    <section><strong>SFEN</strong><textarea data-board-sfen rows="3" readonly style="width:100%;margin-top:7px"></textarea><small data-board-status style="display:block;margin-top:5px;color:#5d6f42"></small></section>
                </aside>
            </div>
        </section>`;
        const grid=root.querySelector('[data-board-grid]');
        const turn=root.querySelector('[data-board-turn]');
        const erase=root.querySelector('[data-board-erase]');
        const handHost=root.querySelector('[data-board-hands]');
        const sfenBox=root.querySelector('[data-board-sfen]');
        const status=root.querySelector('[data-board-status]');
        function renderStatus(sfen){if(sfenBox)sfenBox.value=sfen||'';if(status)status.textContent=sfen?'SFENを自動生成しました。':'SFENを生成できませんでした。';}
        function renderBoard(){
            grid.innerHTML='';
            for(let vr=0;vr<9;vr++)for(let vc=0;vc<9;vc++){
                const r=coord(vr),c=coord(vc),piece=state.board[r][c];
                const cell=document.createElement('button');cell.type='button';cell.dataset.row=String(r);cell.dataset.col=String(c);
                cell.className='lle-shogi-admin-cell';
                if(piece){cell.textContent=GLYPHS[piece.type]||piece.type;cell.style.transform=piece.side==='white'?'rotate(180deg)':'none';cell.title=`${piece.side==='black'?'先手':'後手'} ${GLYPHS[piece.type]||piece.type}`;}
                grid.appendChild(cell);
            }
        }
        function renderHands(){
            handHost.innerHTML=['black','white'].map(owner=>`<div style="display:grid;grid-template-columns:42px repeat(7,1fr);gap:4px;align-items:center"><strong>${owner==='black'?'先手':'後手'}</strong>${HAND_PIECES.map(([type,label])=>`<label title="${label}" style="display:grid;gap:2px;font-size:11px;text-align:center">${label}<input type="number" min="0" max="18" value="${state.hands[owner][type]}" data-hand-owner="${owner}" data-hand-type="${type}" class="lle-shogi-hand-count"></label>`).join('')}</div>`).join('');
        }
        root.addEventListener('click',event=>{
            const pieceButton=event.target.closest('[data-board-piece]');
            if(pieceButton){state.selected.type=pieceButton.dataset.boardPiece;state.erase=false;erase.checked=false;root.querySelectorAll('[data-board-piece]').forEach(button=>button.style.outline='');pieceButton.style.outline='3px solid #d48c24';return;}
            const cell=event.target.closest('[data-row][data-col]');
            if(cell){
                const r=Number(cell.dataset.row),c=Number(cell.dataset.col);
                if(state.erase){state.board[r][c]=null;renderBoard();emit();return;}
                if(state.selected.type==='king'&&state.selected.side===state.turn){window.alert('攻め方の玉は配置できません。');return;}
                if(state.selected.type==='king'){
                    for(let rr=0;rr<9;rr+=1)for(let cc=0;cc<9;cc+=1){const p=state.board[rr][cc];if(p?.type==='king'&&p?.side===state.selected.side)state.board[rr][cc]=null;}
                }
                state.board[r][c]={type:state.selected.type,side:state.selected.side};renderBoard();emit();return;
            }
            if(event.target.closest('[data-board-initial]')){state.board=initialBoard();renderBoard();emit();return;}
            if(event.target.closest('[data-board-empty]')){state.board=emptyBoard();state.hands=normalizeHands({});renderBoard();renderHands();emit();return;}
            if(event.target.closest('[data-board-reverse]')){state.reversed=!state.reversed;renderBoard();return;}
            if(event.target.closest('[data-board-reset]')){state.board=normalizeBoard(normalized.board);state.hands=normalizeHands(normalized.hands);state.turn=normalized.turn==='white'?'white':'black';turn.value=state.turn;renderBoard();renderHands();emit();}
        });
        root.addEventListener('change',event=>{
            if(event.target.matches('input[name="lle-board-owner"]'))state.selected.side=event.target.value;
            if(event.target===erase){state.erase=erase.checked;}
            if(event.target===turn){state.turn=turn.value;emit();}
            if(event.target.matches('[data-hand-owner][data-hand-type]')){const owner=event.target.dataset.handOwner,type=event.target.dataset.handType;state.hands[owner][type]=Math.max(0,Number(event.target.value||0));emit();}
        });
        turn.value=state.turn;renderBoard();renderHands();emit();
        return {
            getValue:()=>({board:clone(state.board),hands:clone(state.hands),turn:state.turn,sfen:sfenBox.value}),
            setValue:config=>{const next=api.normalizeConfig?api.normalizeConfig(config):config;state.board=normalizeBoard(next.board);state.hands=normalizeHands(next.hands);state.turn=next.turn==='white'?'white':'black';turn.value=state.turn;renderBoard();renderHands();emit();},
            destroy:()=>{root.innerHTML='';}
        };
    }

    function createSolutionRouteRecorder(root,rawConfig={},onChange=()=>{}){
        if(!root)throw new Error('正解手順レコーダーの表示先がありません。');
        const api=window.LLEShogiMate||{};
        if(typeof api.createEngine!=='function')throw new Error('将棋エンジンを読み込めませんでした。');
        const base=api.normalizeConfig?api.normalizeConfig(rawConfig):rawConfig;
        const recorderHands=[['pawn','歩'],['lance','香'],['knight','桂'],['silver','銀'],['gold','金'],['bishop','角'],['rook','飛']];
        let moves=Array.isArray(base.solution_moves)?clone(base.solution_moves):[];
        let engine=null,selected=null,lastMessage='盤上の駒、または持ち駒を選んでください。';
        const rebuild=()=>{
            engine=api.createEngine({...base,solution_moves:[]});
            for(let i=0;i<moves.length;i+=1){
                const result=engine.apply(moves[i]);
                if(!result?.ok){moves=moves.slice(0,i);lastMessage=`${i+1}手目以降を取り除きました：${result?.message||'不正な指し手です。'}`;break;}
            }
            selected=null;
        };
        const ownerOf=piece=>piece?.owner||piece?.side;
        const glyphMap={pawn:'歩',lance:'香',knight:'桂',silver:'銀',gold:'金',bishop:'角',rook:'飛',king:'玉',tokin:'と',promotedLance:'杏',promotedKnight:'圭',promotedSilver:'全',horse:'馬',dragon:'龍'};
        const cellLabel=piece=>piece?(glyphMap[piece.type]||GLYPHS[piece.type]||GLYPHS[piece.kind]||piece.type||piece.kind):'';
        const emit=()=>onChange(clone(moves));
        const routeValidation=()=>{
            if(typeof api.validateSolutionRoute!=='function')return {valid:false,errors:[],warnings:[],pending:true};
            try{return api.validateSolutionRoute({...base,solution_moves:clone(moves)})||{valid:false,errors:['正解手順を検証できませんでした。'],warnings:[]};}
            catch(error){return {valid:false,errors:[error?.message||'正解手順の検証に失敗しました。'],warnings:[]};}
        };
        const legalTargets=()=>{
            if(!selected)return [];
            if(selected.kind==='hand')return engine.dropMoves?.(selected.piece,engine.turn)||[];
            return engine.pseudoMoves?.(selected.row,selected.col)||[];
        };
        const render=()=>{
            const turn=engine?.turn==='white'?'後手':'先手';
            const validation=routeValidation();
            const completed=Boolean(validation.valid);
            const validationErrors=Array.isArray(validation.errors)?validation.errors:[];
            const validationWarnings=Array.isArray(validation.warnings)?validation.warnings:[];
            const statusText=completed
                ? '詰み成立：この正解手順は完成しています。'
                : moves.length
                    ? (validationErrors[0]||validationWarnings[0]||'手順を続けて、最後に詰ませてください。')
                    : '開始局面から正解手順を登録してください。';
            const statusBg=completed?'#eaf7e5':(validationErrors.length?'#fff1ee':'#fff8e6');
            const statusColor=completed?'#2f6c2a':(validationErrors.length?'#a33a2e':'#795b19');
            const targets=completed?[]:legalTargets();
            const targetKeys=new Set(targets.map(item=>`${item.row}:${item.col}`));
            const handHtml=recorderHands.map(([type,label])=>{
                const count=Number(engine?.hands?.[engine.turn]?.[type]||0);
                const active=selected?.kind==='hand'&&selected.piece===type;
                return `<button type="button" data-recorder-hand="${type}" ${(count&&!completed)?'':'disabled'} style="min-width:56px;padding:7px 8px;border:1px solid ${active?'#d9362b':'#c8b38c'};border-radius:7px;background:${active?'#fff0e8':'#fff8e8'};cursor:${count&&!completed?'pointer':'not-allowed'};opacity:${count&&!completed?1:.45}">${label}<small style="display:block;font-size:10px">×${count}</small></button>`;
            }).join('');
            root.innerHTML=`<section style="border:1px solid #eadfce;border-radius:9px;background:#fff;padding:12px"><div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:9px"><div><strong>盤面を動かして指し手を追加</strong><div style="font-size:12px;color:#6b6254;margin-top:2px">駒を実際に動かすと、その手が正解手順へ追加されます。現在は${turn}の手です。奇数手は生徒、偶数手は固定応手として保存されます。</div></div><div style="display:flex;gap:6px"><button type="button" class="lle-shogi-toolbar-button" data-recorder-undo ${moves.length?'':'disabled'}>1手戻す</button><button type="button" class="lle-shogi-toolbar-button" data-recorder-reset ${moves.length?'':'disabled'}>開始局面へ</button></div></div><div style="margin-bottom:10px;padding:9px 11px;border-radius:8px;background:${statusBg};color:${statusColor};font-size:12px"><strong>${completed?'詰み成立':'手順確認中'}</strong><div style="margin-top:3px">${statusText}</div></div><div data-recorder-board class="lle-shogi-admin-board${completed?' is-completed':''}"></div><section style="margin-top:10px"><strong style="font-size:13px">${turn}の持ち駒</strong><div data-recorder-hands style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">${handHtml}</div></section><div data-recorder-message style="margin-top:9px;padding:8px 10px;border-radius:7px;background:#f6eddf;color:#5e4a31;font-size:12px">${completed?'正解手順が完成しました。修正する場合は「1手戻す」を押してください。':lastMessage}</div></section>`;
            const board=root.querySelector('[data-recorder-board]');
            for(let r=0;r<9;r+=1)for(let c=0;c<9;c+=1){
                const piece=engine.pieceAt?.(r,c);
                const cell=document.createElement('button');cell.type='button';cell.dataset.row=String(r);cell.dataset.col=String(c);
                const isSelected=selected?.kind==='board'&&selected.row===r&&selected.col===c;
                const isTarget=targetKeys.has(`${r}:${c}`);
                cell.style.cssText=`border:1px solid rgba(70,43,14,.72);background:${isTarget?'linear-gradient(135deg,#f8e7a8,#e7be69)':'linear-gradient(135deg,#efc77e,#dca457)'};font-size:clamp(15px,2.2vw,25px);font-weight:700;display:flex;align-items:center;justify-content:center;cursor:${completed?'default':'pointer'};padding:0;position:relative;${isSelected?'outline:3px solid #d9362b;z-index:1;':''}`;
                if(isTarget){const dot=document.createElement('span');dot.setAttribute('aria-hidden','true');dot.style.cssText='position:absolute;width:12px;height:12px;border-radius:50%;background:rgba(64,102,37,.6);';cell.appendChild(dot);}
                if(piece){const text=document.createElement('span');text.textContent=cellLabel(piece);text.style.cssText=`position:relative;z-index:1;${ownerOf(piece)==='white'?'transform:rotate(180deg);':''}`;cell.appendChild(text);}
                board.appendChild(cell);
            }
            root.querySelector('[data-recorder-undo]')?.addEventListener('click',()=>{moves.pop();lastMessage='1手戻しました。';rebuild();emit();render();});
            root.querySelector('[data-recorder-reset]')?.addEventListener('click',()=>{moves=[];lastMessage='開始局面へ戻しました。';rebuild();emit();render();});
            root.querySelectorAll('[data-recorder-hand]').forEach(button=>button.addEventListener('click',()=>{
                const piece=button.dataset.recorderHand;
                if(selected?.kind==='hand'&&selected.piece===piece){selected=null;lastMessage='持ち駒の選択を解除しました。';}
                else{selected={kind:'hand',piece};lastMessage=`${glyphMap[piece]||piece}を打つマスを選んでください。`;}
                render();
            }));
        };
        root.addEventListener('click',event=>{
            const cell=event.target.closest('[data-recorder-board] [data-row][data-col]');if(!cell)return;
            if(routeValidation().valid){lastMessage='正解手順はすでに詰みまで完成しています。修正する場合は「1手戻す」を押してください。';render();return;}
            const row=Number(cell.dataset.row),col=Number(cell.dataset.col),piece=engine.pieceAt?.(row,col);
            if(!selected){
                if(!piece){lastMessage='移動する駒、または持ち駒を選んでください。';render();return;}
                if(ownerOf(piece)!==engine.turn){lastMessage='現在の手番の駒を選んでください。';render();return;}
                selected={kind:'board',row,col,piece};lastMessage='移動先を選んでください。';render();return;
            }
            if(selected.kind==='hand'){
                const move={kind:'drop',side:engine.turn,piece:selected.piece,to:{row,col}};
                const result=engine.apply(move);
                if(!result?.ok){lastMessage=result?.message||'そのマスには打てません。';selected=null;render();return;}
                moves.push(move);selected=null;lastMessage=`${moves.length}手目（${glyphMap[move.piece]||move.piece}打ち）を登録しました。`;emit();render();return;
            }
            if(selected.row===row&&selected.col===col){selected=null;lastMessage='選択を解除しました。';render();return;}
            const type=selected.piece?.type||selected.piece?.kind||'';
            const canPromote=engine.canPromote?.(selected.piece,selected.row,row)||false;
            const mustPromote=engine.mustPromote?.(selected.piece,row)||false;
            let promote=mustPromote;
            if(canPromote&&!mustPromote)promote=window.confirm('この手で成りますか？\n「キャンセル」を選ぶと成らずに指します。');
            const move={kind:'move',side:engine.turn,piece:type,from:{row:selected.row,col:selected.col},to:{row,col},promote};
            const result=engine.apply(move);
            if(!result?.ok){lastMessage=result?.message||'その場所には移動できません。';selected=null;render();return;}
            moves.push(move);selected=null;lastMessage=`${moves.length}手目を登録しました。`;emit();render();
        });
        rebuild();render();
        return {getMoves:()=>clone(moves),setMoves:next=>{moves=Array.isArray(next)?clone(next):[];rebuild();render();},destroy:()=>{root.innerHTML='';}};
    }

    function validateShogiComponents(steps=[]){
        const issues=[];
        const api=window.LLEShogiMate||{};
        (Array.isArray(steps)?steps:[]).forEach((step,stepIndex)=>{
            const components=Array.isArray(step?.settings?.components)?step.settings.components:[];
            components.forEach((component,componentIndex)=>{
                if(component?.type!=='shogi_mate')return;
                const config=api.normalizeConfig?api.normalizeConfig(component.shogi_mate||{}):(component.shogi_mate||{});
                let result=null;
                try{result=api.validateSolutionRoute?api.validateSolutionRoute(config):null;}
                catch(error){result={valid:false,errors:[error?.message||'正解手順の検証に失敗しました。'],warnings:[]};}
                const base={stepIndex,componentIndex,stepLabel:step?.label||`ステップ${stepIndex+1}`,componentLabel:component?.label||'詰将棋問題セット'};
                if(!result){
                    issues.push({...base,severity:'error',message:'将棋検証機能を読み込めませんでした。'});
                    return;
                }
                (result.errors||[]).forEach(message=>issues.push({...base,severity:'error',message:String(message)}));
                (result.warnings||[]).forEach(message=>issues.push({...base,severity:'warning',message:String(message)}));
            });
        });
        return {
            valid:!issues.some(issue=>issue.severity==='error'),
            issues,
            errors:issues.filter(issue=>issue.severity==='error'),
            warnings:issues.filter(issue=>issue.severity==='warning')
        };
    }
    window.LLELearningBuilder=Object.assign(window.LLELearningBuilder||{},{createBoardEditor,createSolutionRouteRecorder,validateShogiComponents});
})();

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
        submit_answer:'回答送信', judgment:'正誤判定', question_set:'問題セット', choice_question_set:'X択問題セット', shogi_mate:'詰将棋問題セット'
    };
    const componentIcons = {
        heading:'fa-heading', text:'fa-align-left', image:'fa-image', video:'fa-video', divider:'fa-minus',
        spacer:'fa-arrows-alt-v', timed_display:'fa-stopwatch', answer_input:'fa-keyboard',
        answer_timer:'fa-hourglass-half', submit_answer:'fa-paper-plane', judgment:'fa-check-circle', question_set:'fa-list-ol', choice_question_set:'fa-list-check', shogi_mate:'fa-chess-board'
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
        choice_questions: type === 'choice_question_set' ? [] : null,
        shogi_mate: type === 'shogi_mate' ? (window.LLEShogiMate?.defaultConfig?.() || {}) : null
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
        if(c.type==='shogi_mate') return `<div class="lle-shogi-component-editor-host full" data-shogi-component-editor></div>`;
        if(c.type==='spacer') return `<label>余白の高さ<input type="number" min="8" max="300" data-component-field="spacer_height" value="${Number(c.spacer_height||32)}"></label>`;
        return '<p>区切り線を表示します。</p>';
    };

    const componentCard=(c,i)=>`<article class="lle-component-card lle-component-card--${esc(c.type)}" data-component-index="${i}" draggable="true"><header><span class="lle-component-drag">⠿</span><strong><i class="fas ${componentIcons[c.type]||'fa-cube'}"></i> ${componentLabels[c.type]||c.type}</strong><div><button type="button" data-component-action="duplicate" title="複製">⧉</button><button type="button" data-component-action="up" title="上へ">↑</button><button type="button" data-component-action="down" title="下へ">↓</button><button type="button" class="danger" data-component-action="delete" title="削除">×</button></div></header><div class="lle-component-fields">${componentFields(c)}</div></article>`;

    const renderDetail=()=>{
        if(selected===null||!session.steps[selected]){detail.hidden=true;return;}
        const step=session.steps[selected]; detail.hidden=false;
        detailTitle.textContent=`${step.label} 編集`; detailIcon.innerHTML=`<i class="fas ${esc(stepIcon(step))}"></i>`; detailSubtitle.textContent='コンポーネントを自由に追加・入替・削除できます。';
        const basicTypes=['heading','text','image','video','divider','spacer'];
        const questionTypes=['question_set','choice_question_set','shogi_mate','timed_display','answer_input','answer_timer','submit_answer','judgment'];
        const toolbarButtons = types => types.map(t=>`<button type="button" data-add-component="${t}"><i class="fas ${componentIcons[t]||'fa-cube'}"></i> ＋ ${componentLabels[t]}</button>`).join('');
        detailFields.innerHTML=`<section class="lle-step-basic-settings"><label>ステップ名<input data-step-name maxlength="255" value="${esc(step.label)}"></label><div class="lle-current-step-icon"><span>アイコン</span><button type="button" data-change-step-icon><i class="fas ${esc(stepIcon(step))}"></i> 変更</button></div></section><section class="lle-component-builder"><div class="lle-component-toolbar"><strong>コンポーネント</strong><div class="lle-component-toolbar-groups"><div><span>基本</span>${toolbarButtons(basicTypes)}</div><div><span>問題・時間・判定</span>${toolbarButtons(questionTypes)}</div></div></div><div class="lle-component-list">${step.settings.components.length?step.settings.components.map(componentCard).join(''):'<div class="lle-component-empty"><strong>中身は空です</strong><span>上のボタンからコンポーネントを追加してください。</span></div>'}</div></section>`;
        detailFields.querySelectorAll('[data-shogi-component-editor]').forEach(host=>{
            const card=host.closest('[data-component-index]');
            const component=step.settings.components[Number(card?.dataset.componentIndex)];
            if(!component)return;
            component.shogi_mate=window.LLEShogiMate?.normalizeConfig?.(component.shogi_mate)||component.shogi_mate||{};
            host.innerHTML=`<section class="lle-shogi-v1-editor" data-shogi-v1-editor>
                <header class="lle-shogi-v1-header">
                    <div><span class="lle-shogi-v1-version">Version 1.0</span><strong>詰将棋問題セット</strong><p>問題設定から生徒プレビューまで、上から順に完成させてください。</p></div>
                    <div class="lle-shogi-v1-status" data-shogi-v1-status></div>
                </header>
                <nav class="lle-shogi-editor-tabs" aria-label="詰将棋作成手順">
                    <button type="button" data-shogi-tab="settings" class="is-active"><span>1</span>問題・ヒント・解説</button>
                    <button type="button" data-shogi-tab="board"><span>2</span>初期局面</button>
                    <button type="button" data-shogi-tab="route-edit"><span>3</span>正解手順</button>
                    <button type="button" data-shogi-tab="route"><span>4</span>手順確認</button>
                    <button type="button" data-shogi-tab="preview"><span>5</span>生徒プレビュー</button>
                </nav>
                <input type="hidden" data-shogi-component-json>
                <div class="lle-shogi-v1-panel" data-shogi-settings-host></div>
                <div class="lle-shogi-v1-panel" data-shogi-board-host hidden></div>
                <div class="lle-shogi-v1-panel" data-shogi-route-edit-host hidden></div>
                <div class="lle-shogi-v1-panel" data-shogi-route-host hidden></div>
                <div class="lle-shogi-v1-panel" data-shogi-preview-host hidden>
                    <div class="lle-shogi-preview-heading"><div><strong>生徒画面プレビュー</strong><p>登録済みの固定応手を含む正解手順で、実際に解答できます。</p></div><button type="button" data-shogi-preview-reload>最新内容で再読込</button></div>
                    <div class="lle-shogi-preview-message" data-shogi-preview-message hidden></div><div data-shogi-preview-player></div>
                </div>
            </section>`;
            const settingsHost=host.querySelector('[data-shogi-settings-host]');
            const boardHost=host.querySelector('[data-shogi-board-host]');
            const routeEditHost=host.querySelector('[data-shogi-route-edit-host]');
            const routeHost=host.querySelector('[data-shogi-route-host]');
            const previewHost=host.querySelector('[data-shogi-preview-host]');
            const previewPlayer=host.querySelector('[data-shogi-preview-player]');
            const previewMessage=host.querySelector('[data-shogi-preview-message]');
            const jsonField=host.querySelector('[data-shogi-component-json]');
            const statusHost=host.querySelector('[data-shogi-v1-status]');
            const syncShogiMeta=()=>{
                const config=window.LLEShogiMate?.normalizeConfig?.(component.shogi_mate)||component.shogi_mate||{};
                if(jsonField)jsonField.value=JSON.stringify(config);
                const route=window.LLEShogiMate?.validateSolutionRoute?.(config)||{valid:false};
                const boardReady=Array.isArray(config.board)&&config.board.length===9;
                const textReady=Boolean(String(config.title||'').trim()&&String(config.hint||'').trim()&&String(config.explanation||'').trim());
                const items=[['問題情報',textReady],['初期局面',boardReady],['正解手順',route.valid]];
                if(statusHost)statusHost.innerHTML=items.map(([label,ok])=>`<span class="${ok?'is-complete':'is-incomplete'}"><i></i>${label}</span>`).join('');
                host.querySelectorAll('[data-shogi-tab]').forEach(button=>{
                    const mode=button.dataset.shogiTab;
                    const complete=mode==='settings'?textReady:mode==='board'?boardReady:(mode==='route-edit'||mode==='route'||mode==='preview')?route.valid:false;
                    button.classList.toggle('is-complete',complete);
                });
            };
            const updateShogi=next=>{
                const normalized=window.LLEShogiMate?.normalizeConfig?.(next)||next;
                if(Array.isArray(next?.solution_routes)){
                    normalized.solution_routes=next.solution_routes.map(route=>Array.isArray(route)?route.map(move=>({...move})):[]);
                    normalized.solution_moves=normalized.solution_routes[0]?.map(move=>({...move}))||[];
                }
                const before=JSON.stringify(component.shogi_mate||{});
                const after=JSON.stringify(normalized||{});
                component.shogi_mate=normalized;
                syncShogiMeta();
                if(before!==after){markDirty();renderPreview();}
            };
            window.LLEShogiMate?.mountEditor?.(settingsHost,component.shogi_mate,updateShogi);
            syncShogiMeta();
            const boardEditor=window.LLELearningBuilder?.createBoardEditor?.(boardHost,component.shogi_mate,next=>{updateShogi({...component.shogi_mate,...next});});
            let routeEditActiveIndex=0;
            let routeReviewActiveIndex=0;
            const renderRouteEditor=()=>{
                if(!routeEditHost)return;
                const config=window.LLEShogiMate?.normalizeConfig?.(component.shogi_mate)||component.shogi_mate||{};
                const routes=Array.isArray(config.solution_routes)&&config.solution_routes.length?config.solution_routes:[Array.isArray(config.solution_moves)?config.solution_moves:[]];
                routeEditActiveIndex=Math.max(0,Math.min(routeEditActiveIndex,routes.length-1));
                const moves=Array.isArray(routes[routeEditActiveIndex])?routes[routeEditActiveIndex]:[];
                const validation=window.LLEShogiMate?.validateSolutionRoute?.(config,routeEditActiveIndex)||{valid:false,errors:['正解手順を確認できません。']};
                const routeTabs=routes.map((route,index)=>`<button type="button" class="lle-shogi-toolbar-button ${index===routeEditActiveIndex?'is-primary':''}" data-route-select="${index}" style="-webkit-appearance:none;appearance:none;display:inline-flex;align-items:center;justify-content:center;min-height:36px;margin:0;padding:7px 13px;border:1px solid ${index===routeEditActiveIndex?'#9a672c':'#d5c4aa'};border-radius:9px;background:${index===routeEditActiveIndex?'#9a672c':'#fffaf1'};color:${index===routeEditActiveIndex?'#fff':'#594329'};box-shadow:0 1px 2px rgba(69,45,20,.08);font:inherit;font-size:13px;font-weight:700;line-height:1.2;text-align:center;text-decoration:none;white-space:nowrap;cursor:pointer">正解ルート${index+1}${route.length?`（${route.length}手）`:''}</button>`).join('');
                const rows=moves.length?moves.map((move,index)=>{
                    let label=`${index+1}手目`;
                    try{label=window.LLEShogiMate?.formatJapaneseMove?.(move,index)||label;}catch(_){/* 表示用フォールバック */}
                    const role=index%2===0?'生徒の手':'固定応手';
                    return `<li data-route-edit-index="${index}" style="display:grid;grid-template-columns:76px minmax(120px,1fr) auto;gap:8px;align-items:center;padding:9px 10px;border-bottom:1px solid #eadfce"><span style="font-size:12px;color:#7a6b57">${role}</span><strong>${esc(label)}</strong><span style="display:flex;gap:4px"><button type="button" class="lle-shogi-icon-button" data-route-up="${index}" ${index===0?'disabled':''}>↑</button><button type="button" class="lle-shogi-icon-button" data-route-down="${index}" ${index===moves.length-1?'disabled':''}>↓</button><button type="button" class="lle-shogi-action-button is-danger" data-route-delete="${index}">削除</button></span></li>`;
                }).join(''):'<li style="padding:16px;color:#7a6b57">正解手順がまだ登録されていません。問題設定タブで指し手を登録してください。</li>';
                const status=validation.valid?'詰み成立':((validation.errors||[])[0]||'正解手順を完成させてください。');
                routeEditHost.innerHTML=`<section style="border:1px solid #dcc9a7;border-radius:12px;background:#fffdf8;overflow:hidden"><header style="display:flex;justify-content:space-between;gap:10px;align-items:center;padding:13px 14px;background:#f6eddf;flex-wrap:wrap"><div><strong>正解手順編集</strong><div style="font-size:12px;color:#6b6254;margin-top:3px">指し手の追加・順番変更・削除ができます。奇数手は生徒、偶数手は固定応手です。</div></div><div style="display:flex;gap:6px;flex-wrap:wrap"><button type="button" class="lle-shogi-toolbar-button" data-route-add style="-webkit-appearance:none;appearance:none;display:inline-flex;align-items:center;justify-content:center;min-height:36px;margin:0;padding:7px 13px;border:1px solid #d5c4aa;border-radius:9px;background:#fffaf1;color:#594329;box-shadow:0 1px 2px rgba(69,45,20,.08);font:inherit;font-size:13px;font-weight:700;line-height:1.2;text-align:center;text-decoration:none;white-space:nowrap;cursor:pointer">＋正解ルート追加</button><button type="button" class="lle-shogi-toolbar-button" data-route-validate>手順を検証</button><button type="button" class="lle-shogi-toolbar-button is-danger" data-route-clear ${moves.length?'':'disabled'}>全削除</button></div></header><div style="display:flex;gap:6px;flex-wrap:wrap;margin:12px 12px 0">${routeTabs}</div><div data-route-edit-status style="margin:12px;padding:10px 12px;border-radius:8px;background:${validation.valid?'#edf9f1':'#fff1ec'};color:${validation.valid?'#176b3a':'#9a3d22'}"><strong>${validation.valid?'詰み成立':'要確認'}</strong><div style="font-size:12px;margin-top:3px">${esc(status)}</div></div><div data-route-recorder-host style="margin:0 12px 12px"></div><ol style="list-style:none;margin:0 12px 12px;padding:0;border:1px solid #eadfce;border-radius:8px;overflow:hidden">${rows}</ol><footer style="padding:12px 14px;font-size:12px;color:#6b6254;border-top:1px solid #eadfce">登録手数：${moves.length}手 ／ 想定：${Number(config.mate_in||moves.length||0)}手詰</footer></section>`;
                const saveMoves=nextMoves=>{
                    const nextRoutes=routes.map(route=>Array.isArray(route)?route.map(move=>({...move})):[]);
                    nextRoutes[routeEditActiveIndex]=nextMoves;
                    updateShogi({...component.shogi_mate,solution_routes:nextRoutes,solution_moves:nextRoutes[0]||[]});
                    renderRouteEditor();
                };
                routeEditHost.querySelectorAll('[data-route-select]').forEach(button=>button.addEventListener('click',()=>{
                    routeEditActiveIndex=Number(button.dataset.routeSelect)||0;
                    renderRouteEditor();
                }));
                routeEditHost.querySelector('[data-route-add]')?.addEventListener('click',()=>{
                    const nextRoutes=routes.map(route=>Array.isArray(route)?route.map(move=>({...move})):[]);
                    nextRoutes.push([]);
                    routeEditActiveIndex=nextRoutes.length-1;
                    updateShogi({...component.shogi_mate,solution_routes:nextRoutes,solution_moves:nextRoutes[0]||[]});
                    renderRouteEditor();
                });

                const recorderHost=routeEditHost.querySelector('[data-route-recorder-host]');
                if(recorderHost&&window.LLELearningBuilder?.createSolutionRouteRecorder){
                    window.LLELearningBuilder.createSolutionRouteRecorder(recorderHost,{...config,solution_routes:[moves],solution_moves:moves},nextMoves=>{
                        const nextRoutes=routes.map(route=>Array.isArray(route)?route.map(move=>({...move})):[]);
                        nextRoutes[routeEditActiveIndex]=nextMoves;
                        updateShogi({...component.shogi_mate,solution_routes:nextRoutes,solution_moves:nextRoutes[0]||[]});
                    });
                }

                routeEditHost.querySelectorAll('[data-route-up]').forEach(button=>button.addEventListener('click',()=>{
                    const index=Number(button.dataset.routeUp);if(index<=0)return;
                    const next=[...moves];[next[index-1],next[index]]=[next[index],next[index-1]];saveMoves(next);
                }));
                routeEditHost.querySelectorAll('[data-route-down]').forEach(button=>button.addEventListener('click',()=>{
                    const index=Number(button.dataset.routeDown);if(index<0||index>=moves.length-1)return;
                    const next=[...moves];[next[index+1],next[index]]=[next[index],next[index+1]];saveMoves(next);
                }));
                routeEditHost.querySelectorAll('[data-route-delete]').forEach(button=>button.addEventListener('click',()=>{
                    const index=Number(button.dataset.routeDelete);saveMoves(moves.filter((_,i)=>i!==index));
                }));
                routeEditHost.querySelector('[data-route-clear]')?.addEventListener('click',()=>{
                    if(!confirm('登録済みの正解手順をすべて削除しますか？'))return;saveMoves([]);
                });
                routeEditHost.querySelector('[data-route-validate]')?.addEventListener('click',()=>{
                    const result=window.LLEShogiMate?.validateSolutionRoute?.(component.shogi_mate)||{valid:false,errors:['検証できませんでした。']};
                    const box=routeEditHost.querySelector('[data-route-edit-status]');
                    if(box){box.style.background=result.valid?'#edf9f1':'#fff1ec';box.style.color=result.valid?'#176b3a':'#9a3d22';box.innerHTML=`<strong>${result.valid?'詰み成立':'要確認'}</strong><div style="font-size:12px;margin-top:3px">${esc(result.valid?'登録された最終手で詰みが成立しています。':(result.errors?.[0]||'正解手順を確認してください。'))}</div>`;}
                });
            };
            const renderSolutionRoute=()=>{
                if(!routeHost)return;
                const config=window.LLEShogiMate?.normalizeConfig?.(component.shogi_mate)||component.shogi_mate||{};
                const routes=Array.isArray(config.solution_routes)&&config.solution_routes.length
                    ?config.solution_routes
                    :[Array.isArray(config.solution_moves)?config.solution_moves:[]];
                routeReviewActiveIndex=Math.max(0,Math.min(routeReviewActiveIndex,routes.length-1));
                const moves=Array.isArray(routes[routeReviewActiveIndex])?routes[routeReviewActiveIndex]:[];
                const validation=window.LLEShogiMate?.validateSolutionRoute?.(config,routeReviewActiveIndex)||{valid:false,errors:['正解手順を確認できません。']};
                const routeButtons=routes.map((route,index)=>`<button type="button" class="lle-shogi-toolbar-button ${index===routeReviewActiveIndex?'is-primary':''}" data-route-review-select="${index}" style="-webkit-appearance:none;appearance:none;display:inline-flex;align-items:center;justify-content:center;min-height:36px;margin:0;padding:7px 13px;border:1px solid ${index===routeReviewActiveIndex?'#9a672c':'#d5c4aa'};border-radius:9px;background:${index===routeReviewActiveIndex?'#9a672c':'#fffaf1'};color:${index===routeReviewActiveIndex?'#fff':'#594329'};box-shadow:0 1px 2px rgba(69,45,20,.08);font:inherit;font-size:13px;font-weight:700;line-height:1.2;text-align:center;text-decoration:none;white-space:nowrap;cursor:pointer">正解ルート${index+1}${route.length?`（${route.length}手）`:''}</button>`).join('');
                const moveRows=moves.length?moves.map((move,index)=>{
                    let label=`${index+1}手目`;
                    try{label=window.LLEShogiMate?.formatJapaneseMove?.(move,index)||label;}catch(_){/* 表示用フォールバック */}
                    const role=index%2===0?'生徒の手':'固定応手';
                    return `<li style="display:grid;grid-template-columns:72px 1fr;gap:10px;padding:9px 10px;border-bottom:1px solid #eadfce"><span style="font-size:12px;color:#7a6b57">${role}</span><strong>${esc(label)}</strong></li>`;
                }).join(''):'<li style="padding:14px;color:#7a6b57">正解手順がまだ登録されていません。</li>';
                const statusColor=validation.valid?'#176b3a':'#9a3d22';
                const statusBg=validation.valid?'#edf9f1':'#fff1ec';
                const detail=validation.valid?'登録された最終手で詰みが成立しています。':esc((validation.errors||[])[0]||'正解手順を完成させてください。');
                routeHost.innerHTML=`<section style="border:1px solid #dcc9a7;border-radius:12px;background:#fffdf8;overflow:hidden"><header style="padding:13px 14px;background:#f6eddf"><strong>固定応手を含む正解ルート</strong><div style="font-size:12px;color:#6b6254;margin-top:3px">確認する正解ルートを選択してください。奇数手が生徒、偶数手が問題側の固定応手です。</div></header><div style="display:flex;gap:6px;flex-wrap:wrap;margin:12px 12px 0">${routeButtons}</div><div style="margin:12px;padding:10px 12px;border-radius:8px;background:${statusBg};color:${statusColor}"><strong>${validation.valid?'詰み成立':'手順を確認してください'}</strong><div style="font-size:12px;margin-top:3px">${detail}</div></div><div style="display:grid;grid-template-columns:minmax(250px,1fr) minmax(220px,330px);gap:14px;padding:0 12px 12px;align-items:start"><div><div data-shogi-route-replay-board class="lle-shogi-admin-board"></div><div style="display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin-top:10px"><button type="button" class="lle-shogi-replay-button" data-route-first>最初へ</button><button type="button" class="lle-shogi-replay-button" data-route-prev>戻る</button><button type="button" class="lle-shogi-replay-button is-primary" data-route-play>自動再生</button><button type="button" class="lle-shogi-replay-button" data-route-next>進む</button><button type="button" class="lle-shogi-replay-button" data-route-last>最後へ</button></div><div data-route-position style="text-align:center;font-size:12px;color:#6b6254;margin-top:7px">開始局面</div></div><ol data-route-move-list style="list-style:none;margin:0;padding:0;border:1px solid #eadfce;border-radius:8px;overflow:auto;max-height:430px">${moveRows}</ol></div><footer style="padding:12px 14px;font-size:12px;color:#6b6254;border-top:1px solid #eadfce">登録手数：${moves.length}手 ／ 想定：${Number(config.mate_in||moves.length||0)}手詰</footer></section>`;
                routeHost.querySelectorAll('[data-route-review-select]').forEach(button=>button.addEventListener('click',()=>{
                    routeReviewActiveIndex=Number(button.dataset.routeReviewSelect)||0;
                    renderSolutionRoute();
                }));
                const replayApi=window.LLEShogiMate?.createReplaySession;
                if(typeof replayApi==='function'){
                    const replay=replayApi({...config,solution_routes:[moves],solution_moves:moves},moves);
                    const board=routeHost.querySelector('[data-shogi-route-replay-board]');
                    const position=routeHost.querySelector('[data-route-position]');
                    const playButton=routeHost.querySelector('[data-route-play]');
                    let timer=null;
                    const stop=()=>{if(timer){clearInterval(timer);timer=null;}if(playButton)playButton.textContent='自動再生';};
                    const replayGlyphs={
                        pawn:'歩',lance:'香',knight:'桂',silver:'銀',gold:'金',bishop:'角',rook:'飛',king:'玉',
                        tokin:'と',promotedLance:'杏',promotedKnight:'圭',promotedSilver:'全',horse:'馬',dragon:'龍',
                        P:'歩',L:'香',N:'桂',S:'銀',G:'金',B:'角',R:'飛',K:'玉','+P':'と','+L':'杏','+N':'圭','+S':'全','+B':'馬','+R':'龍'
                    };
                    const draw=state=>{
                        if(!board)return;
                        board.innerHTML='';
                        const stateBoard=Array.isArray(state?.board)?state.board:[];
                        for(let row=0;row<9;row+=1){
                            for(let col=0;col<9;col+=1){
                                const piece=stateBoard?.[row]?.[col]||null;
                                const cell=document.createElement('div');
                                cell.className='lle-shogi-admin-cell';
                                if(piece){
                                    const type=String(piece.type||piece.kind||'');
                                    const side=piece.side||piece.owner||'black';
                                    cell.textContent=replayGlyphs[type]||type;
                                    const glyph=document.createElement('span');
                                    glyph.textContent=cell.textContent;
                                    glyph.style.display='inline-block';
                                    glyph.style.transform=side==='white'?'rotate(180deg)':'none';
                                    cell.textContent='';
                                    cell.appendChild(glyph);
                                    cell.title=`${side==='white'?'後手':'先手'} ${replayGlyphs[type]||type}`;
                                }
                                board.appendChild(cell);
                            }
                        }
                        if(position)position.textContent=state.index===0?'開始局面':`${state.index} / ${state.total}手　${state.move_text||''}`;
                        routeHost.querySelectorAll('[data-route-move-list] li').forEach((item,index)=>{item.style.background=state.index===index+1?'#fff1d6':'';});
                    };
                    replay.subscribe(draw);
                    routeHost.querySelector('[data-route-first]')?.addEventListener('click',()=>{stop();replay.first();});
                    routeHost.querySelector('[data-route-prev]')?.addEventListener('click',()=>{stop();replay.previous();});
                    routeHost.querySelector('[data-route-next]')?.addEventListener('click',()=>{stop();replay.next();});
                    routeHost.querySelector('[data-route-last]')?.addEventListener('click',()=>{stop();replay.last();});
                    playButton?.addEventListener('click',()=>{
                        if(timer){stop();return;}
                        if(!replay.canNext())replay.first();
                        playButton.textContent='停止';
                        timer=setInterval(()=>{if(!replay.canNext()){stop();return;}replay.next();},700);
                    });
                }
            };
            let shogiPreviewInstance=null;
            const destroyStudentPreview=()=>{
                try{shogiPreviewInstance?.destroy?.();}catch(_){/* プレビュー破棄失敗は画面操作を止めない */}
                shogiPreviewInstance=null;
                if(previewPlayer)previewPlayer.innerHTML='';
            };
            const mountStudentPreview=()=>{
                if(!previewPlayer)return;
                destroyStudentPreview();
                const validation=window.LLEShogiMate?.validateSolutionRoute?.(component.shogi_mate);
                if(validation && !validation.valid){
                    previewMessage.hidden=false;
                    previewMessage.innerHTML=`<strong>プレビューできません。</strong><div>${esc(validation.errors?.[0]||'正解手順を完成させてください。')}</div>`;
                    return;
                }
                previewMessage.hidden=true;
                if(!window.LLEShogiMate?.mountPlayer){
                    previewMessage.hidden=false;
                    previewMessage.textContent='将棋プレイヤーを読み込めませんでした。';
                    return;
                }
                shogiPreviewInstance=window.LLEShogiMate.mountPlayer(previewPlayer,{
                    ...component.shogi_mate,
                    preview_mode:true
                });
            };
            host.querySelector('[data-shogi-preview-reload]')?.addEventListener('click',mountStudentPreview);
            host.querySelectorAll('[data-shogi-tab]').forEach(button=>button.addEventListener('click',()=>{
                const mode=button.dataset.shogiTab;
                host.querySelectorAll('[data-shogi-tab]').forEach(item=>item.classList.toggle('is-active',item===button));
                settingsHost.hidden=mode!=='settings';
                boardHost.hidden=mode!=='board';
                routeEditHost.hidden=mode!=='route-edit';
                routeHost.hidden=mode!=='route';
                previewHost.hidden=mode!=='preview';
                if(mode==='board')boardEditor?.setValue?.(component.shogi_mate);
                if(mode==='route-edit')renderRouteEditor();
                if(mode==='route')renderSolutionRoute();
                if(mode==='preview')mountStudentPreview();
                else destroyStudentPreview();
            }));
        });
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
        if(c.type==='shogi_mate'){
            const payload=encodeURIComponent(JSON.stringify(c.shogi_mate||{}));
            return `<section class="lle-shogi-runtime-host" data-runtime-shogi-mate data-shogi-mate="${payload}"></section>`;
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
        root.querySelectorAll('[data-runtime-shogi-mate]').forEach(box=>{
            let config={};try{config=JSON.parse(decodeURIComponent(box.dataset.shogiMate||''));}catch(_){config={};}
            window.LLEShogiMate?.mountPlayer?.(box,config);
        });
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
        if(!session.title)throw new Error('タイトルを入力してください。');
        // 詰将棋は作成途中でも保存可能にします。
        // 正解手順・詰み成立などの完成チェックは、手順確認／生徒プレビュー側で行います。
        const shogiValidation=window.LLELearningBuilder?.validateShogiComponents?.(session.steps)||{valid:true,errors:[],warnings:[]};
        document.dispatchEvent(new CustomEvent('lle:shogi-save-validated',{detail:{...shogiValidation,allowIncomplete:true}}));
        saveButton.disabled=true;saveButton.textContent='保存中…';
        try{const backgroundCopyTargets=[...(session._backgroundCopyTargets||[])];const payload=JSON.parse(JSON.stringify(session,(k,v)=>(k.startsWith('_')||k==='show_result_mark'||k==='result_display_seconds')?undefined:v));const form=new FormData();form.append('_method','PUT');form.append('payload',JSON.stringify(payload));if(session._backgroundUpload){form.append('background_image_file',session._backgroundUpload);form.append('background_step_index',String(session._backgroundUploadStepIndex ?? selected ?? 0));}session.steps.forEach((step,i)=>Object.entries(step._uploads||{}).forEach(([slot,file])=>form.append(`files[${i}][${slot}]`,file)));const res=await fetch(saveUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf},body:form});let data=await res.json().catch(()=>({}));if(!res.ok)throw new Error(data.message||'保存に失敗しました。');if(Array.isArray(data.steps))data.steps.forEach((saved,i)=>{if(!session.steps[i])return;session.steps[i].id=saved.id;session.steps[i].settings=saved.settings||session.steps[i].settings;session.steps[i]._uploads={};});const savedIndex=session._backgroundUploadStepIndex ?? selected ?? 0;const savedBackground=data.steps?.[savedIndex]?.settings?.screen_background;if(savedBackground&&session.steps[savedIndex]){session.steps[savedIndex].settings.screen_background={...defaultScreenBackground(),...savedBackground};if(selected===savedIndex&&backgroundImage)backgroundImage.value=session.steps[savedIndex].settings.screen_background.image||'';}if(savedBackground&&backgroundCopyTargets.length){backgroundCopyTargets.forEach(index=>{if(session.steps[index])session.steps[index].settings.screen_background=cloneBackground(savedBackground);});const copiedPayload=JSON.parse(JSON.stringify(session,(k,v)=>(k.startsWith('_')||k==='show_result_mark'||k==='result_display_seconds')?undefined:v));const copiedForm=new FormData();copiedForm.append('_method','PUT');copiedForm.append('payload',JSON.stringify(copiedPayload));const copiedRes=await fetch(saveUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf},body:copiedForm});data=await copiedRes.json().catch(()=>({}));if(!copiedRes.ok)throw new Error(data.message||'背景設定のコピー保存に失敗しました。');if(Array.isArray(data.steps))data.steps.forEach((saved,i)=>{if(session.steps[i])session.steps[i].settings=saved.settings||session.steps[i].settings;});}if(session._backgroundBlobUrl)URL.revokeObjectURL(session._backgroundBlobUrl);session._backgroundBlobUrl=null;session._backgroundUpload=null;session._backgroundUploadStepIndex=null;session._backgroundCopyTargets=[];if(backgroundFile)backgroundFile.value='';refreshBackgroundUploadState();renderSteps();markSaved();}
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

