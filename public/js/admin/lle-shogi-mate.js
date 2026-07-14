(() => {
    'use strict';

    const STEP_KEY = 'shogi_mate';
    const VERSION = 1;
    const ROOT_SELECTOR = '[data-session-edit-page]';
    const PIECES = ['king', 'rook', 'bishop', 'gold', 'silver', 'knight', 'lance', 'pawn'];
    const HAND_PIECES = ['rook', 'bishop', 'gold', 'silver', 'knight', 'lance', 'pawn'];
    const STANDARD_PIECE_TOTALS = { rook: 2, bishop: 2, gold: 4, silver: 4, knight: 4, lance: 4, pawn: 18 };
    const LABELS = {
        king: '玉', rook: '飛', bishop: '角', gold: '金', silver: '銀', knight: '桂', lance: '香', pawn: '歩',
        dragon: '龍', horse: '馬', promotedSilver: '全', promotedKnight: '圭', promotedLance: '杏', tokin: 'と'
    };
    const PROMOTE_TO = { rook: 'dragon', bishop: 'horse', silver: 'promotedSilver', knight: 'promotedKnight', lance: 'promotedLance', pawn: 'tokin' };
    const UNPROMOTE = { dragon: 'rook', horse: 'bishop', promotedSilver: 'silver', promotedKnight: 'knight', promotedLance: 'lance', tokin: 'pawn' };
    const SIDES = { black: '先手', white: '後手' };
    const PROBLEM_SET_SCHEMA = 'lle-shogi-problem-set';
    const PROBLEM_SET_SCHEMA_VERSION = '1.0';
    const ABILITY_KEYS = ['judgment', 'logical_thinking', 'foresight', 'spatial_recognition', 'concentration', 'memory'];
    const ABILITY_LABELS = {
        judgment: '判断力', logical_thinking: '論理的思考力', foresight: '先読み力',
        spatial_recognition: '空間認識力', concentration: '集中力', memory: '記憶力'
    };
    let PIECE_SVG_SEQ = 0;

    const deepClone = value => JSON.parse(JSON.stringify(value));
    const uid = () => `shogi-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
    const inBoard = (r, c) => r >= 0 && r < 9 && c >= 0 && c < 9;
    const opponent = side => side === 'black' ? 'white' : 'black';
    const emptyHands = () => ({ black: Object.fromEntries(HAND_PIECES.map(p => [p, 0])), white: Object.fromEntries(HAND_PIECES.map(p => [p, 0])) });
    const emptyBoard = () => Array.from({ length: 9 }, () => Array(9).fill(null));


    const SFEN_TO_TYPE = {
        K: 'king', R: 'rook', B: 'bishop', G: 'gold', S: 'silver', N: 'knight', L: 'lance', P: 'pawn'
    };
    const TYPE_TO_SFEN = {
        king: 'K', rook: 'R', bishop: 'B', gold: 'G', silver: 'S', knight: 'N', lance: 'L', pawn: 'P',
        dragon: '+R', horse: '+B', promotedSilver: '+S', promotedKnight: '+N', promotedLance: '+L', tokin: '+P'
    };
    const SFEN_HAND_ORDER = ['rook', 'bishop', 'gold', 'silver', 'knight', 'lance', 'pawn'];

    function parseSfen(sfen) {
        if (typeof sfen !== 'string' || !sfen.trim()) throw new Error('SFENが空です。');
        const parts = sfen.trim().replace(/^sfen\s+/i, '').split(/\s+/);
        if (parts.length < 3) throw new Error('SFENの形式が正しくありません。');
        const [boardPart, turnPart, handPart] = parts;
        const ranks = boardPart.split('/');
        if (ranks.length !== 9) throw new Error('SFENの盤面は9段必要です。');
        const board = emptyBoard();
        ranks.forEach((rank, row) => {
            let col = 0;
            let promoted = false;
            for (const ch of rank) {
                if (/\d/.test(ch)) {
                    col += Number(ch);
                    promoted = false;
                    continue;
                }
                if (ch === '+') {
                    if (promoted) throw new Error('SFENの成駒表記が正しくありません。');
                    promoted = true;
                    continue;
                }
                const upper = ch.toUpperCase();
                const baseType = SFEN_TO_TYPE[upper];
                if (!baseType || col >= 9) throw new Error('SFENの駒表記が正しくありません。');
                const side = ch === upper ? 'black' : 'white';
                const type = promoted ? (PROMOTE_TO[baseType] || baseType) : baseType;
                board[row][col] = { type, side };
                col += 1;
                promoted = false;
            }
            if (promoted || col !== 9) throw new Error('SFENの各段は9マス必要です。');
        });
        if (!['b', 'w'].includes(turnPart)) throw new Error('SFENの手番表記が正しくありません。');
        const hands = emptyHands();
        if (handPart !== '-') {
            let countBuffer = '';
            for (const ch of handPart) {
                if (/\d/.test(ch)) {
                    countBuffer += ch;
                    continue;
                }
                const upper = ch.toUpperCase();
                const type = SFEN_TO_TYPE[upper];
                if (!type || type === 'king') throw new Error('SFENの持ち駒表記が正しくありません。');
                const side = ch === upper ? 'black' : 'white';
                const count = countBuffer ? Number(countBuffer) : 1;
                if (!Number.isInteger(count) || count < 1) throw new Error('SFENの持ち駒数が正しくありません。');
                hands[side][type] += count;
                countBuffer = '';
            }
            if (countBuffer) throw new Error('SFENの持ち駒表記が正しくありません。');
        }
        const moveNumber = Math.max(1, Number(parts[3] || 1));
        return { board, hands, turn: turnPart === 'b' ? 'black' : 'white', moveNumber };
    }

    function generateSfen(board, hands, turn = 'black', moveNumber = 1) {
        const ranks = [];
        for (let row = 0; row < 9; row += 1) {
            let rank = '';
            let empty = 0;
            for (let col = 0; col < 9; col += 1) {
                const piece = board?.[row]?.[col] || null;
                if (!piece) {
                    empty += 1;
                    continue;
                }
                if (empty) { rank += String(empty); empty = 0; }
                const token = TYPE_TO_SFEN[piece.type];
                if (!token) throw new Error(`SFENへ変換できない駒です: ${piece.type}`);
                const promoted = token.startsWith('+');
                const letter = promoted ? token.slice(1) : token;
                rank += promoted ? '+' : '';
                rank += piece.side === 'black' ? letter : letter.toLowerCase();
            }
            if (empty) rank += String(empty);
            ranks.push(rank);
        }
        let handText = '';
        ['black', 'white'].forEach(side => {
            SFEN_HAND_ORDER.forEach(type => {
                const count = Number(hands?.[side]?.[type] || 0);
                if (count < 1) return;
                const letter = TYPE_TO_SFEN[type];
                if (count > 1) handText += String(count);
                handText += side === 'black' ? letter : letter.toLowerCase();
            });
        });
        return `${ranks.join('/')} ${turn === 'white' ? 'w' : 'b'} ${handText || '-'} ${Math.max(1, Number(moveNumber || 1))}`;
    }

    function defaultPosition() {
        const board = emptyBoard();
        const back = ['lance', 'knight', 'silver', 'gold', 'king', 'gold', 'silver', 'knight', 'lance'];
        back.forEach((type, c) => {
            board[0][c] = { type, side: 'white' };
            board[8][8 - c] = { type, side: 'black' };
        });
        board[1][1] = { type: 'rook', side: 'white' };
        board[1][7] = { type: 'bishop', side: 'white' };
        board[7][1] = { type: 'bishop', side: 'black' };
        board[7][7] = { type: 'rook', side: 'black' };
        for (let c = 0; c < 9; c += 1) {
            board[2][c] = { type: 'pawn', side: 'white' };
            board[6][c] = { type: 'pawn', side: 'black' };
        }
        return board;
    }

    function defaultConfig() {
        return {
            version: VERSION,
            title: '詰将棋に挑戦',
            prompt: '相手の玉を詰ませましょう。',
            board: emptyBoard(),
            hands: emptyHands(),
            turn: 'black',
            mate_in: 1,
            solution_moves: [],
            solution_routes: [[]],
            hint: '',
            hint_levels: ['', '', ''],
            explanation: '',
            settings: {
                allow_retry: true,
                show_hint: true,
                show_answer: false,
                require_correct: true,
                continue_until_mate_in: false,
                flipped: false,
                show_coordinates: true
            }
        };
    }

    function normalizeConfig(raw) {
        const base = defaultConfig();
        if (!raw || typeof raw !== 'object') return base;
        const cfg = { ...base, ...deepClone(raw) };
        cfg.version = VERSION;
        let sfenPosition = null;
        if (typeof raw.sfen === 'string' && raw.sfen.trim()) {
            try { sfenPosition = parseSfen(raw.sfen); } catch (error) { console.warn('[LLEShogiMate] Invalid SFEN:', error); }
        }
        const hasExplicitBoard = Array.isArray(raw.board) && raw.board.length === 9;
        const hasExplicitHands = raw.hands && typeof raw.hands === 'object';
        const hasExplicitTurn = raw.turn === 'black' || raw.turn === 'white';
        // 編集画面では board / hands / turn が最新値です。
        // 保存済みの古い SFEN が残っていても、明示的な編集値を上書きしないようにします。
        cfg.board = hasExplicitBoard ? raw.board : (sfenPosition?.board || base.board);
        cfg.hands = { ...base.hands, ...(hasExplicitHands ? raw.hands : (sfenPosition?.hands || {})) };
        cfg.hands.black = { ...base.hands.black, ...(cfg.hands.black || {}) };
        cfg.hands.white = { ...base.hands.white, ...(cfg.hands.white || {}) };
        cfg.turn = hasExplicitTurn ? raw.turn : (sfenPosition?.turn || 'black');
        cfg.sfen = generateSfen(cfg.board, cfg.hands, cfg.turn, sfenPosition?.moveNumber || 1);
        const rawRoutes = Array.isArray(raw.solution_routes) ? raw.solution_routes.filter(Array.isArray) : [];
        const legacyMoves = Array.isArray(raw.solution_moves) ? raw.solution_moves : [];
        cfg.solution_routes = rawRoutes.length ? rawRoutes.map(route => deepClone(route)) : [deepClone(legacyMoves)];
        if (!cfg.solution_routes.length) cfg.solution_routes = [[]];
        cfg.solution_moves = deepClone(cfg.solution_routes[0] || []);
        const legacyHint = String(raw.hint ?? '');
        cfg.hint_levels = Array.isArray(raw.hint_levels)
            ? [0, 1, 2].map(index => String(raw.hint_levels[index] ?? ''))
            : [legacyHint, '', ''];
        cfg.hint = cfg.hint_levels.find(value => value.trim()) || legacyHint;
        cfg.settings = { ...base.settings, ...(raw.settings || {}) };
        cfg.scoring = {
            target_time_ms: Math.max(1000, Number(raw.scoring?.target_time_ms) || Math.max(15000, Number(cfg.mate_in || 1) * 30000)),
            retry_penalty: Math.max(0, Number(raw.scoring?.retry_penalty) || 12),
            hint_penalty: Math.max(0, Number(raw.scoring?.hint_penalty) || 18),
            undo_penalty: Math.max(0, Number(raw.scoring?.undo_penalty) || 5),
            restart_penalty: Math.max(0, Number(raw.scoring?.restart_penalty) || 8)
        };
        return cfg;
    }

    class ShogiEngine {
        constructor(config) { this.load(config); }
        load(config) {
            this.config = normalizeConfig(config);
            this.board = deepClone(this.config.board);
            this.hands = deepClone(this.config.hands);
            this.turn = this.config.turn;
            this.history = [];
            this.lastMove = null;
        }
        snapshot() { return { board: deepClone(this.board), hands: deepClone(this.hands), turn: this.turn, lastMove: deepClone(this.lastMove) }; }
        restore(state) { this.board = deepClone(state.board); this.hands = deepClone(state.hands); this.turn = state.turn; this.lastMove = deepClone(state.lastMove); }
        export() { return { board: deepClone(this.board), hands: deepClone(this.hands), turn: this.turn, sfen: this.toSfen() }; }
        toSfen(moveNumber = this.history.length + 1) { return generateSfen(this.board, this.hands, this.turn, moveNumber); }
        loadSfen(sfen) {
            const parsed = parseSfen(sfen);
            this.board = deepClone(parsed.board);
            this.hands = deepClone(parsed.hands);
            this.turn = parsed.turn;
            this.history = [];
            this.lastMove = null;
            return this.export();
        }
        pieceAt(r, c) { return inBoard(r, c) ? this.board[r][c] : null; }
        isPromoted(type) { return Object.prototype.hasOwnProperty.call(UNPROMOTE, type); }
        baseType(type) { return UNPROMOTE[type] || type; }
        canPromote(piece, fromRow, toRow) {
            if (!PROMOTE_TO[piece.type]) return false;
            const zone = piece.side === 'black' ? row => row <= 2 : row => row >= 6;
            return zone(fromRow) || zone(toRow);
        }
        mustPromote(piece, toRow) {
            const type = this.baseType(piece.type);
            if (piece.side === 'black') {
                return (['pawn', 'lance'].includes(type) && toRow === 0) || (type === 'knight' && toRow <= 1);
            }
            return (['pawn', 'lance'].includes(type) && toRow === 8) || (type === 'knight' && toRow >= 7);
        }
        vectors(type, side) {
            const f = side === 'black' ? -1 : 1;
            const gold = [[f,-1],[f,0],[f,1],[0,-1],[0,1],[-f,0]];
            const king = [[-1,-1],[-1,0],[-1,1],[0,-1],[0,1],[1,-1],[1,0],[1,1]];
            const map = {
                king, gold, pawn: [[f,0]], lance: [[f,0,'slide']], knight: [[2*f,-1],[2*f,1]], silver: [[f,-1],[f,0],[f,1],[-f,-1],[-f,1]],
                rook: [[-1,0,'slide'],[1,0,'slide'],[0,-1,'slide'],[0,1,'slide']],
                bishop: [[-1,-1,'slide'],[-1,1,'slide'],[1,-1,'slide'],[1,1,'slide']],
                dragon: [[-1,0,'slide'],[1,0,'slide'],[0,-1,'slide'],[0,1,'slide'],[-1,-1],[-1,1],[1,-1],[1,1]],
                horse: [[-1,-1,'slide'],[-1,1,'slide'],[1,-1,'slide'],[1,1,'slide'],[-1,0],[1,0],[0,-1],[0,1]],
                promotedSilver: gold, promotedKnight: gold, promotedLance: gold, tokin: gold
            };
            return map[type] || [];
        }
        pseudoMoves(r, c, attackOnly = false) {
            const piece = this.pieceAt(r, c);
            if (!piece) return [];
            const result = [];
            this.vectors(piece.type, piece.side).forEach(([dr, dc, mode]) => {
                let nr = r + dr, nc = c + dc;
                while (inBoard(nr, nc)) {
                    const target = this.pieceAt(nr, nc);
                    if (!target || target.side !== piece.side) result.push({ row: nr, col: nc });
                    if (target || mode !== 'slide') break;
                    nr += dr; nc += dc;
                }
            });
            return attackOnly ? result : result.filter(m => !this.wouldLeaveKingInCheck({ kind: 'move', from: { row: r, col: c }, to: m }));
        }
        findKing(side) {
            for (let r = 0; r < 9; r += 1) for (let c = 0; c < 9; c += 1) {
                const p = this.board[r][c];
                if (p && p.side === side && p.type === 'king') return { row: r, col: c };
            }
            return null;
        }
        isSquareAttacked(row, col, bySide) {
            for (let r = 0; r < 9; r += 1) for (let c = 0; c < 9; c += 1) {
                const p = this.board[r][c];
                if (p && p.side === bySide && this.pseudoMoves(r, c, true).some(m => m.row === row && m.col === col)) return true;
            }
            return false;
        }
        isInCheck(side) {
            const king = this.findKing(side);
            return king ? this.isSquareAttacked(king.row, king.col, opponent(side)) : false;
        }
        wouldLeaveKingInCheck(move) {
            const state = this.snapshot();
            this.applyUnchecked(move, false);
            const checked = this.isInCheck(move.side || (move.kind === 'move' ? state.board[move.from.row][move.from.col]?.side : this.turn));
            this.restore(state);
            return checked;
        }
        canDrop(type, side, row, col, options = {}) {
            if (this.pieceAt(row, col) || !this.hands[side]?.[type]) return false;
            if (type === 'pawn') {
                for (let r = 0; r < 9; r += 1) {
                    const p = this.board[r][col];
                    if (p && p.side === side && p.type === 'pawn') return false;
                }
            }
            if (side === 'black' && ((['pawn','lance'].includes(type) && row === 0) || (type === 'knight' && row <= 1))) return false;
            if (side === 'white' && ((['pawn','lance'].includes(type) && row === 8) || (type === 'knight' && row >= 7))) return false;

            const move = { kind: 'drop', piece: type, side, to: { row, col } };
            if (this.wouldLeaveKingInCheck(move)) return false;

            // 打ち歩詰めは禁止。再帰判定中は同じ検査を抑止する。
            if (type === 'pawn' && !options.skipPawnDropMate) {
                const state = this.snapshot();
                this.applyUnchecked(move, false);
                const defender = opponent(side);
                const pawnDropMate = this.isInCheck(defender) && this.isCheckmate(defender, { skipPawnDropMate: true });
                this.restore(state);
                if (pawnDropMate) return false;
            }
            return true;
        }
        dropMoves(type, side, options = {}) {
            const moves = [];
            for (let r = 0; r < 9; r += 1) for (let c = 0; c < 9; c += 1) {
                if (this.canDrop(type, side, r, c, options)) moves.push({ row: r, col: c });
            }
            return moves;
        }
        legalMoves(side = this.turn, options = {}) {
            const moves = [];
            for (let r = 0; r < 9; r += 1) for (let c = 0; c < 9; c += 1) {
                const piece = this.pieceAt(r, c);
                if (!piece || piece.side !== side) continue;
                this.pseudoMoves(r, c).forEach(to => {
                    const canPromote = this.canPromote(piece, r, to.row);
                    const mustPromote = this.mustPromote(piece, to.row);
                    if (!mustPromote) moves.push({ kind: 'move', side, piece: piece.type, from: { row: r, col: c }, to: { ...to }, promote: false, resultType: piece.type });
                    if (canPromote) moves.push({ kind: 'move', side, piece: piece.type, from: { row: r, col: c }, to: { ...to }, promote: true, resultType: PROMOTE_TO[piece.type] });
                });
            }
            HAND_PIECES.forEach(type => {
                if (!this.hands[side]?.[type]) return;
                this.dropMoves(type, side, options).forEach(to => moves.push({ kind: 'drop', side, piece: type, to: { ...to } }));
            });
            return moves;
        }
        hasLegalMove(side = this.turn, options = {}) { return this.legalMoves(side, options).length > 0; }
        isCheckmate(side = this.turn, options = {}) { return this.isInCheck(side) && !this.hasLegalMove(side, options); }
        applyUnchecked(move, pushHistory = true) {
            if (pushHistory) this.history.push(this.snapshot());
            if (move.kind === 'drop') {
                this.board[move.to.row][move.to.col] = { type: move.piece, side: move.side };
                this.hands[move.side][move.piece] -= 1;
            } else {
                const piece = this.board[move.from.row][move.from.col];
                const captured = this.board[move.to.row][move.to.col];
                this.board[move.from.row][move.from.col] = null;
                if (captured) {
                    const capturedBase = this.baseType(captured.type);
                    if (capturedBase !== 'king') this.hands[piece.side][capturedBase] = (this.hands[piece.side][capturedBase] || 0) + 1;
                }
                this.board[move.to.row][move.to.col] = { ...piece, type: move.promote ? PROMOTE_TO[piece.type] : piece.type };
            }
            this.lastMove = deepClone(move);
            this.turn = opponent(this.turn);
        }
        apply(move) {
            const side = move.kind === 'drop' ? move.side : this.pieceAt(move.from.row, move.from.col)?.side;
            if (side !== this.turn) return { ok: false, message: '手番が違います。' };
            if (move.kind === 'drop') {
                if (!this.canDrop(move.piece, side, move.to.row, move.to.col)) return { ok: false, message: 'そのマスには打てません。' };
            } else {
                const legal = this.pseudoMoves(move.from.row, move.from.col).some(m => m.row === move.to.row && m.col === move.to.col);
                if (!legal) return { ok: false, message: 'その駒はそこへ動かせません。' };
            }
            this.applyUnchecked(move, true);
            const check = this.isInCheck(this.turn);
            return { ok: true, check, mate: check && this.isCheckmate(this.turn) };
        }
        undo() { if (!this.history.length) return false; this.restore(this.history.pop()); return true; }
    }

    function moveKey(move) {
        if (!move) return '';
        return move.kind === 'drop'
            ? `d:${move.side}:${move.piece}:${move.to.row},${move.to.col}`
            : `m:${move.from.row},${move.from.col}:${move.to.row},${move.to.col}:${move.promote ? 1 : 0}`;
    }

    function moveText(move, index) {
        if (!move) return '';
        const side = move.side || (index % 2 === 0 ? 'black' : 'white');
        const prefix = side === 'black' ? '▲' : '△';
        const file = 9 - move.to.col;
        const rank = '一二三四五六七八九'[move.to.row];
        return `${prefix}${file}${rank}${LABELS[move.piece || move.resultType || 'pawn'] || ''}${move.kind === 'drop' ? '打' : (move.promote ? '成' : '')}`;
    }

    const PIECE_VISUALS = {
        king: { size: 'king', font: 49, labelY: 58 },
        rook: { size: 'major', font: 48, labelY: 58 },
        bishop: { size: 'major', font: 48, labelY: 58 },
        dragon: { size: 'major', font: 46, labelY: 58 },
        horse: { size: 'major', font: 46, labelY: 58 },
        gold: { size: 'general', font: 46, labelY: 58 },
        silver: { size: 'general', font: 46, labelY: 58 },
        promotedSilver: { size: 'general', font: 44, labelY: 58 },
        knight: { size: 'minor', font: 44, labelY: 58 },
        lance: { size: 'minor', font: 44, labelY: 58 },
        promotedKnight: { size: 'minor', font: 43, labelY: 58 },
        promotedLance: { size: 'minor', font: 43, labelY: 58 },
        pawn: { size: 'pawn', font: 43, labelY: 58 },
        tokin: { size: 'pawn', font: 43, labelY: 58 }
    };

    function pieceVisualHtml(piece) {
        if (!piece) return '';
        const promoted = Object.prototype.hasOwnProperty.call(UNPROMOTE, piece.type);
        const label = LABELS[piece.type] || '?';
        const visual = PIECE_VISUALS[piece.type] || PIECE_VISUALS.pawn;
        const svgId = `lle-shogi-piece-${++PIECE_SVG_SEQ}`;
        const labelClass = promoted ? ' lle-shogi-piece-label--promoted' : '';
        const labelY = visual.labelY + 3;

        return `<span class="lle-shogi-piece lle-shogi-piece--${visual.size} ${piece.side === 'white' ? 'is-white' : ''} ${promoted ? 'is-promoted' : ''}" data-piece-type="${piece.type}" aria-hidden="true">
            <svg class="lle-shogi-piece-svg" viewBox="0 0 100 106" preserveAspectRatio="xMidYMid meet" focusable="false">
                <defs>
                    <linearGradient id="${svgId}-wood" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#f7d47b"/>
                        <stop offset="34%" stop-color="#efbd58"/>
                        <stop offset="72%" stop-color="#e7aa3f"/>
                        <stop offset="100%" stop-color="#d8922c"/>
                    </linearGradient>
                    <linearGradient id="${svgId}-face" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#b87723" stop-opacity=".10"/>
                        <stop offset="18%" stop-color="#fff1b5" stop-opacity=".18"/>
                        <stop offset="50%" stop-color="#fff8cf" stop-opacity=".30"/>
                        <stop offset="82%" stop-color="#ffebaa" stop-opacity=".14"/>
                        <stop offset="100%" stop-color="#a8661e" stop-opacity=".12"/>
                    </linearGradient>
                    <linearGradient id="${svgId}-edge" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#d8952f"/>
                        <stop offset="100%" stop-color="#b9701d"/>
                    </linearGradient>
                    <filter id="${svgId}-shadow" x="-25%" y="-20%" width="150%" height="160%">
                        <feDropShadow dx="0" dy="2.1" stdDeviation="1.45" flood-color="#6f4218" flood-opacity=".24"/>
                    </filter>
                    <filter id="${svgId}-label" x="-20%" y="-20%" width="140%" height="145%">
                        <feDropShadow dx=".45" dy=".65" stdDeviation=".22" flood-color="#5a3518" flood-opacity=".36"/>
                    </filter>
                    <clipPath id="${svgId}-clip">
                        <polygon points="50,4 79,19 94,91 6,91 21,19"/>
                    </clipPath>
                </defs>

                <g filter="url(#${svgId}-shadow)">
                    <polygon class="lle-shogi-piece-thickness" fill="url(#${svgId}-edge)" points="6,91 11,99 89,99 94,91 90,96 10,96"/>
                    <polygon class="lle-shogi-piece-body" fill="url(#${svgId}-wood)" points="50,4 79,19 94,91 6,91 21,19"/>
                    <polygon class="lle-shogi-piece-face" fill="url(#${svgId}-face)" points="50,6.4 76.8,20.5 91,88.4 9,88.4 23.2,20.5"/>

                    <path class="lle-shogi-piece-toplight" d="M22.2 19.5 L50 6.6 L77.8 19.5"/>
                    <path class="lle-shogi-piece-leftbevel" d="M22.2 20.8 L9.7 88"/>
                    <path class="lle-shogi-piece-rightbevel" d="M77.8 20.8 L90.3 88"/>
                    <path class="lle-shogi-piece-bottomshade" d="M9.2 88.8 L90.8 88.8"/>

                    <g clip-path="url(#${svgId}-clip)" class="lle-shogi-piece-grain-group">
                        <path class="lle-shogi-piece-grain" d="M15 43 C35 40.8,65 45,85 42"/>
                        <path class="lle-shogi-piece-grain" d="M11 72 C35 69.7,65 74,89 71"/>
                    </g>

                    <text class="lle-shogi-piece-label-highlight${labelClass}" x="49.55" y="${labelY - 0.55}" dy=".32em" style="font-size:${visual.font}px" text-anchor="middle">${label}</text>
                    <text class="lle-shogi-piece-label${labelClass}" filter="url(#${svgId}-label)" x="50" y="${labelY}" dy=".32em" style="font-size:${visual.font}px" text-anchor="middle">${label}</text>
                </g>
            </svg>
        </span>`;
    }

    class ShogiBoardView {
        constructor(container, engine, options = {}) {
            this.container = container;
            this.engine = engine;
            this.options = options;
            this.selected = null;
            this.selectedHand = null;
            this.legal = [];
            this.render();
        }
        setEngine(engine) { this.engine = engine; this.clearSelection(); this.render(); }
        clearSelection() { this.selected = null; this.selectedHand = null; this.legal = []; }
        orientationCoords(displayR, displayC) {
            const flip = !!this.options.flipped;
            return flip ? { row: 8 - displayR, col: 8 - displayC } : { row: displayR, col: displayC };
        }
        displayCoords(row, col) {
            const flip = !!this.options.flipped;
            return flip ? { row: 8 - row, col: 8 - col } : { row, col };
        }
        pieceHtml(piece) { return pieceVisualHtml(piece); }
        renderHands(side) {
            const items = HAND_PIECES.map(type => {
                const count = Number(this.engine.hands[side]?.[type] || 0);
                const active = this.selectedHand?.side === side && this.selectedHand?.type === type;
                return `<button type="button" class="lle-shogi-hand-piece ${active ? 'is-selected' : ''}" data-hand-side="${side}" data-hand-piece="${type}" ${count < 1 ? 'disabled' : ''}>${this.pieceHtml({ type, side })}<b>${count}</b></button>`;
            }).join('');
            return `<div class="lle-shogi-hand ${side === 'white' ? 'is-white-hand' : ''}"><div class="lle-shogi-hand-head"><strong>${SIDES[side]}の持ち駒</strong>${this.engine.turn === side ? '<span>手番</span>' : ''}</div><div class="lle-shogi-hand-list">${items}</div></div>`;
        }
        render() {
            const coords = !!this.options.showCoordinates;
            let cells = '';
            for (let dr = 0; dr < 9; dr += 1) for (let dc = 0; dc < 9; dc += 1) {
                const { row, col } = this.orientationCoords(dr, dc);
                const piece = this.engine.pieceAt(row, col);
                const selected = this.selected?.row === row && this.selected?.col === col;
                const legal = this.legal.some(m => m.row === row && m.col === col);
                const from = this.engine.lastMove?.from?.row === row && this.engine.lastMove?.from?.col === col;
                const to = this.engine.lastMove?.to?.row === row && this.engine.lastMove?.to?.col === col;
                const kingCheck = piece?.type === 'king' && this.engine.isInCheck(piece.side);
                cells += `<button type="button" class="lle-shogi-cell ${selected ? 'is-selected' : ''} ${legal ? 'is-legal' : ''} ${from ? 'is-last-from' : ''} ${to ? 'is-last-to' : ''} ${kingCheck ? 'is-check' : ''}" data-row="${row}" data-col="${col}" aria-label="${9-col}筋${row+1}段 ${piece ? SIDES[piece.side] + LABELS[piece.type] : '空き'}">${this.pieceHtml(piece)}${legal ? '<i class="lle-shogi-move-dot"></i>' : ''}</button>`;
            }
            this.container.innerHTML = `<div class="lle-shogi-table"><div class="lle-shogi-side-panel">${this.renderHands('white')}</div><div class="lle-shogi-board-wrap">${coords ? '<div class="lle-shogi-file-labels">' + [...Array(9)].map((_,i)=>`<span>${this.options.flipped ? i+1 : 9-i}</span>`).join('') + '</div>' : ''}<div class="lle-shogi-board">${cells}</div>${coords ? '<div class="lle-shogi-rank-labels">' + [...'一二三四五六七八九'].map((x,i)=>`<span>${this.options.flipped ? '九八七六五四三二一'[i] : x}</span>`).join('') + '</div>' : ''}</div><div class="lle-shogi-side-panel">${this.renderHands('black')}</div></div>`;
            this.bind();
        }
        bind() {
            this.container.querySelectorAll('[data-row]').forEach(cell => cell.addEventListener('click', () => {
                if (this.options.isLocked?.()) return;
                this.clickCell(Number(cell.dataset.row), Number(cell.dataset.col));
            }));
            this.container.querySelectorAll('[data-hand-piece]').forEach(btn => btn.addEventListener('click', () => {
                if (this.options.isLocked?.()) return;
                const side = btn.dataset.handSide, type = btn.dataset.handPiece;
                if (side !== this.engine.turn && this.options.mode !== 'editor') return;
                this.selected = null; this.selectedHand = { side, type }; this.legal = this.engine.dropMoves(type, side); this.render();
            }));
        }
        clickCell(row, col) {
            if (this.selectedHand) {
                if (this.legal.some(m => m.row === row && m.col === col)) this.submit({ kind: 'drop', side: this.selectedHand.side, piece: this.selectedHand.type, to: { row, col } });
                else this.clearSelection();
                this.render(); return;
            }
            if (this.selected && this.legal.some(m => m.row === row && m.col === col)) {
                const piece = this.engine.pieceAt(this.selected.row, this.selected.col);
                const move = { kind: 'move', side: piece.side, piece: piece.type, from: { ...this.selected }, to: { row, col }, promote: false, resultType: piece.type };
                const can = this.engine.canPromote(piece, this.selected.row, row);
                const must = this.engine.mustPromote(piece, row);
                if (can && !must && this.options.askPromotion !== false) {
                    this.askPromotion(move); return;
                }
                move.promote = must;
                move.resultType = move.promote ? PROMOTE_TO[piece.type] : piece.type;
                this.submit(move); this.clearSelection(); this.render(); return;
            }
            const piece = this.engine.pieceAt(row, col);
            if (piece && (piece.side === this.engine.turn || this.options.mode === 'editor')) {
                this.selected = { row, col }; this.selectedHand = null; this.legal = this.engine.pseudoMoves(row, col); this.render(); return;
            }
            this.clearSelection(); this.render();
        }
        askPromotion(move) {
            const dialog = document.createElement('div');
            dialog.className = 'lle-shogi-promotion';
            dialog.innerHTML = `<div><strong>成りますか？</strong><p>成る・成らないを選択してください。</p><span><button type="button" data-promote="0">成らない</button><button type="button" data-promote="1">成る</button></span></div>`;
            this.container.appendChild(dialog);
            dialog.querySelectorAll('[data-promote]').forEach(btn => btn.addEventListener('click', () => {
                move.promote = btn.dataset.promote === '1';
                move.resultType = move.promote ? PROMOTE_TO[move.piece] : move.piece;
                dialog.remove(); this.submit(move); this.clearSelection(); this.render();
            }));
        }
        submit(move) {
            if (this.options.beforeMove && this.options.beforeMove(move) === false) return;
            const result = this.engine.apply(move);
            if (!result.ok) { this.options.onError?.(result.message); return; }
            this.options.onMove?.(deepClone(move), result);
        }
    }

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

    function validateSolutionRoute(rawConfig, routeIndex = null) {
        const cfg = normalizeConfig(rawConfig);
        const routes = Array.isArray(cfg.solution_routes) && cfg.solution_routes.length
            ? cfg.solution_routes
            : [Array.isArray(cfg.solution_moves) ? cfg.solution_moves : []];
        const validateOne = (moves, index) => {
            const engine = new ShogiEngine(cfg);
            const errors = [];
            if (!moves.length) errors.push(`正解ルート${index + 1}が登録されていません。`);
            moves.forEach((move, moveIndex) => {
                if (errors.length) return;
                const expectedSide = engine.turn;
                if (move.side && move.side !== expectedSide) {
                    errors.push(`正解ルート${index + 1}の${moveIndex + 1}手目の手番が一致しません。`);
                    return;
                }
                const result = engine.apply({ ...deepClone(move), side: expectedSide });
                if (!result.ok) errors.push(`正解ルート${index + 1}の${moveIndex + 1}手目が不正です：${result.message || '指し手を実行できません。'}`);
            });
            const isMate = errors.length === 0 && engine.isCheckmate(engine.turn);
            if (!errors.length && !isMate) errors.push(`正解ルート${index + 1}の最終局面が詰みになっていません。`);
            const expected = Math.max(1, Number(cfg.mate_in || 1));
            if (!errors.length && moves.length !== expected) errors.push(`正解ルート${index + 1}は想定${expected}手ですが、登録は${moves.length}手です。`);
            return { valid: errors.length === 0, is_mate: isMate, move_count: moves.length, expected_move_count: expected, final_sfen: engine.toSfen(), errors, route_index: index };
        };
        if (routeIndex !== null) return validateOne(routes[routeIndex] || [], routeIndex);
        const results = routes.map(validateOne);
        const errors = results.flatMap(result => result.errors);
        return {
            valid: results.length > 0 && results.every(result => result.valid),
            is_mate: results.length > 0 && results.every(result => result.is_mate),
            move_count: results[0]?.move_count || 0,
            expected_move_count: Math.max(1, Number(cfg.mate_in || 1)),
            final_sfen: results[0]?.final_sfen || new ShogiEngine(cfg).toSfen(),
            route_count: routes.length,
            route_results: results,
            errors
        };
    }

    class ShogiComponentEditor {
        constructor(root, config, onChange) {
            this.root = root;
            this.config = normalizeConfig(config);
            this.onChange = typeof onChange === 'function' ? onChange : () => {};
            this.selectedPalette = { type: 'pawn', side: 'black' };
            this.activeRouteIndex = 0;
            this.recordPreviewStep = null;
            this.render();
        }
        commit(mutator, rerender = false) {
            mutator(this.config);
            this.config = normalizeConfig(this.config);
            this.onChange(deepClone(this.config));
            if (rerender) this.render();
        }
        handicapPosition(kind) {
            const board = defaultPosition();
            const remove = positions => positions.forEach(([row, col]) => { board[row][col] = null; });
            if (kind === 'two') remove([[1, 1], [1, 7]]);
            if (kind === 'six') remove([[0, 0], [0, 1], [0, 7], [0, 8], [1, 1], [1, 7]]);
            return board;
        }
        html() {
            const cfg=this.config, checked=v=>v?'checked':'';
            return `<div class="lle-shogi-editor">
                <section class="lle-shogi-editor-section"><header><span>01</span><div><strong>問題設定</strong><p>生徒に表示する内容と完了条件</p></div></header><div class="lle-shogi-form-grid">
                    <label class="wide">問題タイトル<input data-shogi-field="title" value="${escapeHtml(cfg.title)}" maxlength="255"></label>
                    <label class="wide">問題文<textarea data-shogi-field="prompt" rows="2">${escapeHtml(cfg.prompt)}</textarea></label>
                    <label>開始手番<select data-shogi-field="turn"><option value="black" ${cfg.turn==='black'?'selected':''}>先手</option><option value="white" ${cfg.turn==='white'?'selected':''}>後手</option></select></label>
                    <label>想定手数<input type="number" min="1" max="99" step="2" data-shogi-field="mate_in" value="${cfg.mate_in}"></label>
                </div><div class="lle-shogi-check-grid">
                    <label><input type="checkbox" data-shogi-setting="allow_retry" ${checked(cfg.settings.allow_retry)}><span>やり直しを許可</span></label>
                    <label><input type="checkbox" data-shogi-setting="show_hint" ${checked(cfg.settings.show_hint)}><span>ヒントを表示</span></label>
                    <label><input type="checkbox" data-shogi-setting="show_answer" ${checked(cfg.settings.show_answer)}><span>解答表示を許可</span></label>
                    <label><input type="checkbox" data-shogi-setting="require_correct" ${checked(cfg.settings.require_correct)}><span>正解まで次へ進めない</span></label>
                    <label><input type="checkbox" data-shogi-setting="show_coordinates" ${checked(cfg.settings.show_coordinates)}><span>座標を表示</span></label>
                </div></section>
                <section class="lle-shogi-editor-section"><header><span>02</span><div><strong>初期局面</strong><p>駒を選び、盤面へ配置します</p></div></header>
                    <div class="lle-shogi-position-tools"><div data-shogi-palette></div><div class="lle-shogi-position-actions lle-shogi-quick-positions"><button type="button" data-shogi-fill-white-hands>後手の持ち駒を一括設定</button><button type="button" data-shogi-clear-board>全消去</button></div></div>
                    <div data-shogi-position-board></div><div class="lle-shogi-hand-editor" data-shogi-hand-editor></div>
                </section>
                <section class="lle-shogi-editor-section"><header><span>03</span><div><strong>正解手順</strong><p>複数の正解ルートを登録できます</p></div></header>
                    <div class="lle-shogi-record-route-toolbar"><div class="lle-shogi-record-actions" data-shogi-route-tabs></div><div class="lle-shogi-record-actions"><button type="button" data-shogi-route-add>＋正解ルート追加</button><button type="button" data-shogi-route-delete>このルートを削除</button></div></div>
                    <div class="lle-shogi-record-status"><div class="lle-shogi-current-move"><span>現在の手順</span><strong data-shogi-record-turn>1手目・${SIDES[cfg.turn]}</strong></div><div class="lle-shogi-record-actions"><button type="button" data-shogi-record-undo>一手前へ</button><button type="button" data-shogi-replay>自動再生</button></div></div>
                    <div data-shogi-record-board></div><ol class="lle-shogi-move-list" data-shogi-move-list></ol>
                    <div class="lle-shogi-route-validation" data-shogi-route-validation></div>
                </section>
                <section class="lle-shogi-editor-section"><header><span>04</span><div><strong>ヒント・解説</strong><p>考える助けと正解後の振り返り</p></div></header><div class="lle-shogi-form-grid">
                    <label class="wide">ヒント①（軽い気づき）<textarea data-shogi-hint-level="0" rows="2">${escapeHtml(cfg.hint_levels[0]||'')}</textarea></label>
                    <label class="wide">ヒント②（考え方の方向）<textarea data-shogi-hint-level="1" rows="2">${escapeHtml(cfg.hint_levels[1]||'')}</textarea></label>
                    <label class="wide">ヒント③（ほぼ答え）<textarea data-shogi-hint-level="2" rows="2">${escapeHtml(cfg.hint_levels[2]||'')}</textarea></label>
                    <label class="wide">正解後の解説<textarea data-shogi-field="explanation" rows="4">${escapeHtml(cfg.explanation)}</textarea></label>
                </div></section>
            </div>`;
        }
        render() {
            this.root.innerHTML=this.html();
            this.bind();
            this.renderPositionEditor();
            this.renderRecordBoard();
        }
        bind() {
            this.root.querySelectorAll('[data-shogi-field]').forEach(el=>el.addEventListener('input',()=>{
                const key=el.dataset.shogiField;
                this.commit(c=>{c[key]=key==='mate_in'?Math.max(1,Number(el.value||1)):el.value;if(key==='turn'){c.solution_routes=[[]];c.solution_moves=[];this.activeRouteIndex=0;}},key==='turn');
            }));
            this.root.querySelectorAll('[data-shogi-hint-level]').forEach(el=>el.addEventListener('input',()=>{
                const index=Math.max(0,Math.min(2,Number(el.dataset.shogiHintLevel)||0));
                this.commit(c=>{
                    c.hint_levels=Array.isArray(c.hint_levels)?c.hint_levels.slice(0,3):['','',''];
                    while(c.hint_levels.length<3)c.hint_levels.push('');
                    c.hint_levels[index]=el.value;
                    c.hint=c.hint_levels.find(value=>String(value||'').trim())||'';
                },false);
            }));
            this.root.querySelectorAll('[data-shogi-setting]').forEach(el=>el.addEventListener('change',()=>this.commit(c=>c.settings[el.dataset.shogiSetting]=el.checked,true)));
            this.root.querySelector('[data-shogi-fill-white-hands]')?.addEventListener('click',()=>this.fillWhiteHandsFromRemaining());
            this.root.querySelector('[data-shogi-clear-board]')?.addEventListener('click',()=>this.commit(c=>{c.board=emptyBoard();c.hands=emptyHands();c.solution_routes=[[]];c.solution_moves=[];this.activeRouteIndex=0;},true));
            this.root.querySelector('[data-shogi-record-reset]')?.addEventListener('click',()=>this.renderRecordBoard());
            this.root.querySelector('[data-shogi-route-add]')?.addEventListener('click',()=>{this.commit(c=>{c.solution_routes.push([]);this.activeRouteIndex=c.solution_routes.length-1;c.solution_moves=[];},true);});
            this.root.querySelector('[data-shogi-route-delete]')?.addEventListener('click',()=>{this.commit(c=>{if(c.solution_routes.length<=1){c.solution_routes=[[]];this.activeRouteIndex=0;}else{c.solution_routes.splice(this.activeRouteIndex,1);this.activeRouteIndex=Math.max(0,Math.min(this.activeRouteIndex,c.solution_routes.length-1));}c.solution_moves=deepClone(c.solution_routes[this.activeRouteIndex]||[]);},true);});
            this.root.querySelector('[data-shogi-record-undo]')?.addEventListener('click',()=>this.commit(c=>{const route=c.solution_routes[this.activeRouteIndex]||[];route.pop();c.solution_moves=deepClone(route);},true));
            this.root.querySelector('[data-shogi-record-clear]')?.addEventListener('click',()=>{
                const route=(this.config.solution_routes||[])[this.activeRouteIndex]||[];
                this.recordPreviewStep=route.length?1:0;
                this.renderRecordBoard();
            });
            this.root.querySelector('[data-shogi-replay]')?.addEventListener('click',()=>this.replay());
        }
        fillWhiteHandsFromRemaining() {
            const used = Object.fromEntries(HAND_PIECES.map(type => [type, 0]));
            this.config.board.forEach(row => row.forEach(piece => {
                if (!piece) return;
                const baseType = UNPROMOTE[piece.type] || piece.type;
                if (Object.prototype.hasOwnProperty.call(used, baseType)) used[baseType] += 1;
            }));
            HAND_PIECES.forEach(type => {
                used[type] += Math.max(0, Number(this.config.hands.black?.[type] || 0));
            });
            const over = HAND_PIECES.filter(type => used[type] > STANDARD_PIECE_TOTALS[type]);
            if (over.length) {
                window.alert(`盤面と先手の持ち駒で使用数が上限を超えています。\n${over.map(type => `${LABELS[type]}：${used[type]}枚（上限${STANDARD_PIECE_TOTALS[type]}枚）`).join('\n')}`);
                return;
            }
            this.commit(config => {
                HAND_PIECES.forEach(type => {
                    config.hands.white[type] = STANDARD_PIECE_TOTALS[type] - used[type];
                });
                config.solution_routes = [[]];
                config.solution_moves = [];
                this.activeRouteIndex = 0;
            }, true);
        }
        renderPositionEditor() {
            const palette=this.root.querySelector('[data-shogi-palette]'),boardRoot=this.root.querySelector('[data-shogi-position-board]'),handRoot=this.root.querySelector('[data-shogi-hand-editor]');
            if(!palette||!boardRoot||!handRoot)return;
            const selected=this.selectedPalette;
            const renderPalette=()=>{
                palette.innerHTML=`<div class="lle-shogi-piece-palette"><div class="lle-shogi-palette-side"><button type="button" data-pal-side="black" class="${selected.side==='black'?'is-active':''}">先手</button><button type="button" data-pal-side="white" class="${selected.side==='white'?'is-active':''}">後手</button></div><div class="lle-shogi-palette-pieces">${PIECES.map(type=>`<button type="button" data-pal-piece="${type}" class="${selected.type===type?'is-active':''}">${pieceVisualHtml({type,side:selected.side})}</button>`).join('')}<button type="button" data-pal-piece="erase" class="${selected.type==='erase'?'is-active':''}">消去</button></div></div>`;
                palette.querySelectorAll('[data-pal-side]').forEach(b=>b.onclick=()=>{selected.side=b.dataset.palSide;renderPalette();});
                palette.querySelectorAll('[data-pal-piece]').forEach(b=>b.onclick=()=>{selected.type=b.dataset.palPiece;renderPalette();});
            };
            const engine=new ShogiEngine(this.config);
            new ShogiBoardView(boardRoot,engine,{mode:'editor',flipped:this.config.settings.flipped,showCoordinates:this.config.settings.show_coordinates,beforeMove:()=>false});
            boardRoot.querySelectorAll('[data-row]').forEach(cell=>{
                cell.onclick=e=>{
                    e.preventDefault();
                    e.stopPropagation();
                    const r=Number(cell.dataset.row),c=Number(cell.dataset.col);
                    this.commit(cfg=>{
                        const current=cfg.board[r][c];
                        if(selected.type==='erase'){
                            cfg.board[r][c]=null;
                        }else{
                            // 選択中の向きを起点に、選択側 → 反対側 → なし の順で循環します。
                            // 玉は詰ませる側（先手）へ配置しない既存仕様を維持します。
                            const firstSide = selected.type === 'king' ? 'white' : selected.side;
                            const secondSide = opponent(firstSide);
                            if(!current || current.type !== selected.type){
                                cfg.board[r][c]={type:selected.type,side:firstSide};
                            }else if(current.side === firstSide){
                                if(selected.type === 'king') cfg.board[r][c]=null;
                                else cfg.board[r][c]={type:selected.type,side:secondSide};
                            }else{
                                cfg.board[r][c]=null;
                            }
                        }
                        cfg.solution_routes=[[]];cfg.solution_moves=[];this.activeRouteIndex=0;
                    },true);
                };
                cell.oncontextmenu=e=>{e.preventDefault();const r=Number(cell.dataset.row),c=Number(cell.dataset.col);this.commit(cfg=>{cfg.board[r][c]=null;cfg.solution_routes=[[]];cfg.solution_moves=[];this.activeRouteIndex=0;},true);};
            });
            handRoot.innerHTML=['white','black'].map(side=>`<section><strong>${SIDES[side]}の持ち駒</strong><div>${HAND_PIECES.map(type=>`<label><span>${LABELS[type]}</span><span class="lle-shogi-stepper"><button type="button" data-hand-minus="${side}:${type}" aria-label="${LABELS[type]}を減らす">−</button><b>${this.config.hands[side][type]||0}</b><button type="button" data-hand-plus="${side}:${type}" aria-label="${LABELS[type]}を増やす">＋</button></span></label>`).join('')}</div></section>`).join('');
            handRoot.querySelectorAll('[data-hand-plus],[data-hand-minus]').forEach(btn=>btn.onclick=()=>{const [side,type]=(btn.dataset.handPlus||btn.dataset.handMinus).split(':');this.commit(c=>{c.hands[side][type]=Math.max(0,(c.hands[side][type]||0)+(btn.dataset.handPlus?1:-1));c.solution_routes=[[]];c.solution_moves=[];this.activeRouteIndex=0;},true);});
            renderPalette();
        }
        renderRecordBoard() {
            const root=this.root.querySelector('[data-shogi-record-board]');if(!root)return;
            const routes=Array.isArray(this.config.solution_routes)&&this.config.solution_routes.length?this.config.solution_routes:[[]];
            this.activeRouteIndex=Math.max(0,Math.min(this.activeRouteIndex,routes.length-1));
            const activeRoute=routes[this.activeRouteIndex]||[];
            this.config.solution_moves=deepClone(activeRoute);
            const tabs=this.root.querySelector('[data-shogi-route-tabs]');
            if(tabs){
                tabs.innerHTML=routes.map((route,i)=>`<button type="button" data-shogi-route-tab="${i}" class="${i===this.activeRouteIndex?'is-active':''}">正解ルート${i+1}${route.length?`（${route.length}手）`:''}</button>`).join('');
                tabs.querySelectorAll('[data-shogi-route-tab]').forEach(button=>button.addEventListener('click',()=>{this.activeRouteIndex=Number(button.dataset.shogiRouteTab)||0;this.recordPreviewStep=null;this.render();}));
            }
            const previewStep=this.recordPreviewStep===null?activeRoute.length:Math.max(0,Math.min(activeRoute.length,Number(this.recordPreviewStep)||0));
            const displayedRoute=activeRoute.slice(0,previewStep);
            const engine=new ShogiEngine(this.config);displayedRoute.forEach(m=>engine.applyUnchecked(m,false));
            const mateIn=Math.max(1,Number(this.config.mate_in||1));
            const routeComplete=activeRoute.length>=mateIn;
            new ShogiBoardView(root,engine,{flipped:this.config.settings.flipped,showCoordinates:this.config.settings.show_coordinates,onMove:move=>{
                if(routeComplete||previewStep<activeRoute.length)return;
                move.side=opponent(engine.turn);
                this.commit(c=>{c.solution_routes[this.activeRouteIndex].push(move);c.solution_moves=deepClone(c.solution_routes[0]||[]);},true);
            }});
            const turn=this.root.querySelector('[data-shogi-record-turn]');
            if(turn){
                const displayedMove=Math.min(previewStep+1,mateIn);
                const displayedSide=previewStep>=mateIn?SIDES[opponent(engine.turn)]:SIDES[engine.turn];
                turn.textContent=`${displayedMove}手目・${displayedSide}`;
            }
            const list=this.root.querySelector('[data-shogi-move-list]');if(list)list.innerHTML=activeRoute.length?activeRoute.map((m,i)=>`<li><span>${i+1}</span><strong>${moveText(m,i)}</strong></li>`).join(''):'<li class="is-empty">盤面を操作すると、この正解ルートへ指し手が登録されます。</li>';
            const validationRoot=this.root.querySelector('[data-shogi-route-validation]');
            if(validationRoot){
                const validation=validateSolutionRoute(this.config,this.activeRouteIndex);
                validationRoot.className=`lle-shogi-route-validation ${validation.valid?'is-valid':'is-invalid'}`;
                validationRoot.innerHTML=validation.valid
                    ? `<span class="lle-shogi-mate-success-tag">詰み成立：正解ルート${this.activeRouteIndex+1}を使用できます。</span>`
                    : `<strong>手順を確認してください</strong><span>${escapeHtml(validation.errors[0]||'正解手順が完成していません。')}</span>`;
            }
        }

        replay() {
            const root=this.root.querySelector('[data-shogi-record-board]');if(!root)return;
            const engine=new ShogiEngine(this.config),view=new ShogiBoardView(root,engine,{flipped:this.config.settings.flipped,showCoordinates:this.config.settings.show_coordinates});
            const route=(this.config.solution_routes||[])[this.activeRouteIndex]||[];
            this.recordPreviewStep=null;
            playMoves(engine,view,route,()=>this.renderRecordBoard());
        }
    }


    class ShogiPuzzleSession {
        constructor(config) {
            this.config = normalizeConfig(config);
            this.startedAt = Date.now();
            this.completedAt = null;
            this.hintCount = 0;
            this.retryCount = 0;
            this.undoCount = 0;
            this.restartCount = 0;
            this.moves = [];
            this.completed = false;
            this.answerViewed = false;
        }
        elapsedMs() {
            return Math.max(0, (this.completedAt || Date.now()) - this.startedAt);
        }
        recordMove(move, correct = true) {
            this.moves.push({
                ...deepClone(move),
                correct: Boolean(correct),
                elapsed_ms: this.elapsedMs()
            });
            if (!correct) this.retryCount += 1;
        }
        useHint() { this.hintCount += 1; }
        undo() { this.undoCount += 1; }
        restart() {
            this.restartCount += 1;
            this.startedAt = Date.now();
            this.completedAt = null;
            this.moves = [];
            this.completed = false;
            this.answerViewed = false;
        }
        markAnswerViewed() { this.answerViewed = true; }
        removeLastMoves(count = 1) {
            const amount = Math.max(0, Number(count) || 0);
            if (amount > 0) this.moves.splice(Math.max(0, this.moves.length - amount), amount);
        }
        complete() {
            if (!this.completed) {
                this.completed = true;
                this.completedAt = Date.now();
            }
            return this.result();
        }
        evaluation() {
            const elapsed = this.elapsedMs();
            const scoring = this.config.scoring;
            const overtimeRatio = Math.max(0, elapsed - scoring.target_time_ms) / scoring.target_time_ms;
            const timePenalty = Math.min(25, Math.round(overtimeRatio * 15));
            const penalties =
                (this.retryCount * scoring.retry_penalty) +
                (this.hintCount * scoring.hint_penalty) +
                (this.undoCount * scoring.undo_penalty) +
                (this.restartCount * scoring.restart_penalty) +
                timePenalty;
            const score = this.completed ? Math.max(0, Math.min(100, 100 - penalties)) : 0;
            const stars = score >= 90 ? 3 : score >= 70 ? 2 : score >= 1 ? 1 : 0;
            const grade = score >= 95 ? 'S' : score >= 85 ? 'A' : score >= 70 ? 'B' : score >= 50 ? 'C' : 'D';
            return {
                score,
                stars,
                grade,
                target_time_ms: scoring.target_time_ms,
                time_penalty: timePenalty,
                penalty_total: penalties,
                perfect: this.completed && penalties === 0
            };
        }
        result() {
            const context = {
                problem_id: this.config.problem_id ?? this.config.id ?? null,
                problem_set_id: this.config.problem_set_id ?? this.config.set_id ?? null,
                learning_step_content_id: this.config.learning_step_content_id ?? this.config.content_id ?? null,
                learning_session_id: this.config.learning_session_id ?? this.config.session_id ?? null,
                routine_content_id: this.config.routine_content_id ?? null,
                student_routine_item_id: this.config.student_routine_item_id ?? null
            };
            return {
                component_type: STEP_KEY,
                version: VERSION,
                correct: this.completed,
                independent_correct: this.completed && !this.answerViewed,
                assisted: this.answerViewed || this.hintCount > 0,
                answer_viewed: this.answerViewed,
                elapsed_ms: this.elapsedMs(),
                solution_move_count: this.config.solution_moves.length,
                player_move_count: this.moves.filter(move => move.side === this.config.turn).length,
                hint_count: this.hintCount,
                retry_count: this.retryCount,
                undo_count: this.undoCount,
                restart_count: this.restartCount,
                moves: deepClone(this.moves),
                evaluation: this.evaluation(),
                context,
                started_at: new Date(this.startedAt).toISOString(),
                completed_at: this.completedAt ? new Date(this.completedAt).toISOString() : null
            };
        }
    }

    function playMoves(engine,view,moves,done){let i=0;view.render();const next=()=>{if(i>=moves.length){done?.();return;}engine.apply(moves[i]);i+=1;view.clearSelection();view.render();setTimeout(next,420);};next();}

    function mountPlayer(root, rawConfig) {
        const cfg=normalizeConfig(rawConfig);
        const solutionRoutes=(Array.isArray(cfg.solution_routes)&&cfg.solution_routes.length?cfg.solution_routes:[cfg.solution_moves]).filter(route=>Array.isArray(route)&&route.length);
        const replayRoute=solutionRoutes[0]||[];
        root.innerHTML=`<article class="lle-shogi-player"><header><span>詰将棋・${cfg.mate_in}手詰</span><h3>${escapeHtml(cfg.title)}</h3><p>${escapeHtml(cfg.prompt)}</p></header><div class="lle-shogi-player-status" data-player-status>考えて指してみましょう。</div><div data-player-board></div><div class="lle-shogi-player-actions"><button type="button" data-player-undo>一手戻す</button><button type="button" data-player-restart>最初から</button>${cfg.settings.show_hint?'<button type="button" data-player-hint>ヒント</button>':''}${cfg.settings.show_answer?'<button type="button" data-player-answer>解答を見る</button>':''}</div><div class="lle-shogi-player-message" data-player-message hidden></div><section class="lle-shogi-answer-replay" data-player-replay hidden><div class="lle-shogi-answer-replay-head"><strong>正解手順</strong><span data-player-replay-position>開始局面</span></div><div class="lle-shogi-answer-replay-actions"><button type="button" data-player-replay-first>最初</button><button type="button" data-player-replay-prev>前の手</button><button type="button" data-player-replay-next>次の手</button><button type="button" data-player-replay-last>最後</button><button type="button" data-player-replay-auto>自動再生</button></div><ol data-player-replay-list></ol><p data-player-replay-explanation></p></section><section class="lle-shogi-mistake-panel" data-player-mistake-panel hidden><strong>もう一度考えてみよう</strong><p data-player-mistake-text>この手では正解手順に進みません。</p><div class="lle-shogi-mistake-actions"><button type="button" data-player-mistake-continue>同じ局面で考える</button><button type="button" data-player-mistake-restart>最初から</button><button type="button" data-player-mistake-hint>ヒントを見る</button></div></section><section class="lle-shogi-result" data-player-result hidden><div class="lle-shogi-result-title"><strong data-player-result-heading>クリア！</strong><span data-player-result-perfect hidden>PERFECT</span></div><div class="lle-shogi-result-score"><span data-player-result-stars>☆☆☆</span><b data-player-result-points>0点</b><em data-player-result-grade>D</em></div><dl><div><dt>解答時間</dt><dd data-player-result-time>0秒</dd></div><div><dt>手数</dt><dd data-player-result-moves>0手</dd></div><div><dt>ヒント</dt><dd data-player-result-hints>0回</dd></div><div><dt>一手戻し</dt><dd data-player-result-undos>0回</dd></div></dl><div class="lle-shogi-result-rewards" data-player-result-rewards hidden></div><div class="lle-shogi-result-actions"><button type="button" data-player-result-replay>正解手順を見る</button><button type="button" data-player-result-retry>再挑戦</button><button type="button" data-player-result-next disabled>次の問題へ</button></div></section></article>`;
        let index=0;
        let hintLevel=0;
        let replayIndex=0;
        let replayTimer=null;
        let inputLocked=false;
        let completed=false;
        let deviatedFromSolution=false;
        let activeRoutes=solutionRoutes.map((route,routeIndex)=>({route,routeIndex}));
        let completedRoute=replayRoute;
        let session=new ShogiPuzzleSession(cfg);
        const engine=new ShogiEngine(cfg),board=root.querySelector('[data-player-board]'),status=root.querySelector('[data-player-status]'),message=root.querySelector('[data-player-message]'),replay=root.querySelector('[data-player-replay]'),mistakePanel=root.querySelector('[data-player-mistake-panel]'),resultPanel=root.querySelector('[data-player-result]');
        const playControls=[...root.querySelectorAll('[data-player-undo],[data-player-restart],[data-player-hint],[data-player-answer]')];
        const setPlayControlsDisabled=disabled=>playControls.forEach(control=>{control.disabled=Boolean(disabled);control.setAttribute('aria-disabled',disabled?'true':'false');});
        const announce=(text,kind='info')=>{message.hidden=false;message.className=`lle-shogi-player-message is-${kind}`;message.innerHTML=text;};
        const emit=(name,detail={})=>root.dispatchEvent(new CustomEvent(name,{bubbles:true,detail:{type:STEP_KEY,...detail}}));
        const stopReplay=()=>{if(replayTimer){clearInterval(replayTimer);replayTimer=null;}const auto=root.querySelector('[data-player-replay-auto]');if(auto)auto.textContent='自動再生';};
        const renderReplayList=()=>{
            const list=root.querySelector('[data-player-replay-list]');
            if(!list)return;
            list.innerHTML=replayRoute.map((move,i)=>`<li class="${i===replayIndex-1?'is-current':''}"><span>${i+1}手目</span> ${escapeHtml(moveText(move))}${i%2===1?' <small>固定応手</small>':''}</li>`).join('');
        };
        const setReplayStep=step=>{
            stopReplay();
            replayIndex=Math.max(0,Math.min(replayRoute.length,Number(step)||0));
            engine.load(cfg);
            for(let i=0;i<replayIndex;i++){const result=engine.apply(replayRoute[i]);if(!result.ok)break;}
            view?.clearSelection();view?.render();
            const position=root.querySelector('[data-player-replay-position]');
            if(position)position.textContent=replayIndex===0?'開始局面':`${replayIndex} / ${replayRoute.length}手　${moveText(replayRoute[replayIndex-1])}`;
            renderReplayList();
            emit('lle:shogi-replay-step',{step:replayIndex,total:replayRoute.length});
        };
        const openReplay=(startAtEnd=false)=>{
            if(!replay)return;
            replay.hidden=false;
            const explanation=root.querySelector('[data-player-replay-explanation]');
            if(explanation)explanation.textContent=cfg.explanation||'正解手順を一手ずつ確認しましょう。';
            setReplayStep(startAtEnd?replayRoute.length:0);
        };
        let view;
        const reset=()=>{
            index=0;
            hintLevel=0;
            inputLocked=false;
            completed=false;
            deviatedFromSolution=false;
            activeRoutes=solutionRoutes.map((route,routeIndex)=>({route,routeIndex}));
            completedRoute=replayRoute;
            session.restart();
            engine.load(cfg);
            view.clearSelection();
            view.render();
            status.textContent='考えて指してみましょう。';
            message.hidden=true;
            stopReplay();
            if(replay)replay.hidden=true;
            if(mistakePanel)mistakePanel.hidden=true;
            if(resultPanel)resultPanel.hidden=true;
            const nextButton=root.querySelector('[data-player-result-next]');if(nextButton)nextButton.disabled=true;
            const hintButton=root.querySelector('[data-player-hint]');if(hintButton)hintButton.textContent='ヒント';
            root.classList.remove('is-shogi-complete','is-shogi-wrong');
            delete root.dataset.shogiCompleted;
            delete root.dataset.shogiIndependentCorrect;
            setPlayControlsDisabled(false);
            emit('lle:shogi-restart',{result:session.result()});
        };
        const complete=()=>{
            if(completed) return;
            inputLocked=true;
            if(!engine.isCheckmate(engine.turn)){
                inputLocked=false;
                announce('<strong>手順設定を確認してください。</strong><p>登録された最終局面が詰みになっていません。</p>','warning');
                emit('lle:shogi-route-invalid',{validation:validateSolutionRoute(cfg),result:session.result()});
                return;
            }
            completed=true;
            setPlayControlsDisabled(true);
            // 正解時は選択状態・合法手候補を完全に解除し、詰み上がり局面だけを表示します。
            view?.clearSelection();
            view?.render();
            // 最終手のクリック処理後に再描画が走るケースにも対応します。
            setTimeout(() => {
                if (!completed) return;
                view?.clearSelection();
                view?.render();
            }, 0);
            status.textContent='詰みです。正解！';
            const result=session.complete();
            const evaluation=result.evaluation;
            const stars='★'.repeat(evaluation.stars)+'☆'.repeat(3-evaluation.stars);
            announce(`<strong>正解です！</strong><p>${escapeHtml(cfg.explanation||'見事に詰ませました。')}</p><p>評価：${stars}　${evaluation.score}点（${evaluation.grade}）</p>`,'success');
            root.classList.add('is-shogi-complete');
            if(mistakePanel)mistakePanel.hidden=true;
            if(resultPanel){
                resultPanel.hidden=false;
                const seconds=Math.max(1,Math.ceil(result.elapsed_ms/1000));
                root.querySelector('[data-player-result-stars]').textContent=stars;
                root.querySelector('[data-player-result-points]').textContent=`${evaluation.score}点`;
                root.querySelector('[data-player-result-grade]').textContent=evaluation.grade;
                root.querySelector('[data-player-result-time]').textContent=`${seconds}秒`;
                root.querySelector('[data-player-result-moves]').textContent=`${completedRoute.length||index}手`;
                root.querySelector('[data-player-result-hints]').textContent=`${result.hint_count}回`;
                root.querySelector('[data-player-result-undos]').textContent=`${result.undo_count}回`;
                const perfect=root.querySelector('[data-player-result-perfect]');if(perfect)perfect.hidden=!evaluation.perfect;
                const rewards=root.querySelector('[data-player-result-rewards]');
                const points=Math.max(0,Number(cfg.points||cfg.reward_points||0));
                const badges=Array.isArray(cfg.badge_candidates)?cfg.badge_candidates:[];
                if(rewards&&(points||badges.length)){rewards.hidden=false;rewards.innerHTML=`${points?`<span>+${points}ポイント</span>`:''}${badges.map(value=>`<span>${escapeHtml(value)}</span>`).join('')}`;}
            }
            root.dataset.shogiCompleted='1';
            root.dataset.shogiIndependentCorrect=result.independent_correct?'1':'0';
            emit('lle:component-complete',{result,context:result.context});
            emit('lle:shogi-result-shown',{result});
            setTimeout(()=>{const next=root.querySelector('[data-player-result-next]');if(next)next.disabled=false;},0);
        };
        const executeFixedReply=()=>{
            const replyCandidates=activeRoutes.filter(item=>item.route[index]);
            if(!replyCandidates.length){complete();return;}
            const reply=replyCandidates[0].route[index];
            activeRoutes=replyCandidates.filter(item=>moveKey(item.route[index])===moveKey(reply));
            completedRoute=activeRoutes[0]?.route||completedRoute;
            if(!reply||reply.side!==engine.turn){
                inputLocked=false;
                announce('<strong>手順設定を確認してください。</strong><p>固定応手の手番が現在の局面と一致しません。</p>','warning');
                emit('lle:shogi-route-invalid',{validation:validateSolutionRoute(cfg),result:session.result()});
                return;
            }
            inputLocked=true;
            status.textContent='固定応手を実行しています…';
            setTimeout(()=>{
                const result=engine.apply(reply);
                if(!result.ok){
                    inputLocked=false;
                    announce(`<strong>手順設定を確認してください。</strong><p>${escapeHtml(result.message||'固定応手を実行できません。')}</p>`,'warning');
                    emit('lle:shogi-route-invalid',{validation:validateSolutionRoute(cfg),result:session.result()});
                    return;
                }
                session.recordMove(reply,true);
                index+=1;
                view.clearSelection();
                view.render();
                status.textContent=`${index} / ${completedRoute.length}手 正解`;
                inputLocked=false;
                if(activeRoutes.some(item=>index>=item.route.length)){completedRoute=(activeRoutes.find(item=>index>=item.route.length)||activeRoutes[0]).route;complete();return;}
            },350);
        };
        view=new ShogiBoardView(board,engine,{
            flipped:cfg.settings.flipped,
            showCoordinates:cfg.settings.show_coordinates,
            isLocked:()=>inputLocked||completed,
            beforeMove:move=>{
                const matchingRoutes=activeRoutes.filter(item=>item.route[index]&&moveKey(move)===moveKey(item.route[index]));
                if(!activeRoutes.length){announce('この問題には正解手順がまだ登録されていません。','warning');return false;}
                if(deviatedFromSolution){
                    session.recordMove(move,false);
                    return true;
                }
                if(!matchingRoutes.length){
                    session.recordMove(move,false);
                    emit('lle:shogi-mistake',{move:deepClone(move),result:session.result()});
                    root.classList.add('is-shogi-wrong');
                    setTimeout(()=>root.classList.remove('is-shogi-wrong'),500);
                    if(cfg.settings.continue_until_mate_in){
                        deviatedFromSolution=true;
                        if(mistakePanel)mistakePanel.hidden=true;
                        announce('<strong>正解手順とは異なります。</strong><p>想定手数まではそのまま指し続けられます。</p>','error');
                        return true;
                    }
                    announce(cfg.settings.allow_retry?'惜しい！ 別の手を考えてみましょう。':'正解手順と異なる指し手です。最初から確認しましょう。','error');
                    if(mistakePanel){
                        mistakePanel.hidden=false;
                        const text=mistakePanel.querySelector('[data-player-mistake-text]');
                        if(text)text.textContent=`${session.retryCount}回目の挑戦です。盤面をよく見て、別の手を考えてみましょう。`;
                    }
                    return false;
                }
                activeRoutes=matchingRoutes;
                completedRoute=activeRoutes[0]?.route||completedRoute;
                session.recordMove(move,true);
                return true;
            },
            onMove:()=>{
                index+=1;
                if(deviatedFromSolution){
                    const limit=Math.max(1,Number(cfg.mate_in||1));
                    status.textContent=`${Math.min(index,limit)} / ${limit}手`;
                    if(index>=limit){
                        inputLocked=true;
                        setPlayControlsDisabled(true);
                        announce('<strong>不正解です。</strong><p>想定手数まで指しましたが、正解手順とは異なりました。</p>','error');
                        emit('lle-shogi:incorrect',{result:session.result(),move_count:index,expected_move_count:limit});
                    }
                    return;
                }
                completedRoute=activeRoutes[0]?.route||completedRoute;
                status.textContent=`${index} / ${completedRoute.length}手 正解`;
                if(activeRoutes.some(item=>index>=item.route.length)){completedRoute=(activeRoutes.find(item=>index>=item.route.length)||activeRoutes[0]).route;complete();return;}
                executeFixedReply();
            },
            onError:text=>announce(escapeHtml(text),'error')
        });
        root.querySelector('[data-player-undo]')?.addEventListener('click',()=>{
            if(inputLocked||completed||index<1) return;
            const undoCount=index%2===0?2:1;
            let undone=0;
            while(undone<undoCount&&engine.undo()) undone+=1;
            if(!undone) return;
            session.undo();
            session.removeLastMoves(undone);
            index=Math.max(0,index-undone);
            const played=session.moves.map(item=>item.move);
            activeRoutes=solutionRoutes.map((route,routeIndex)=>({route,routeIndex})).filter(item=>played.every((playedMove,i)=>item.route[i]&&moveKey(item.route[i])===moveKey(playedMove)));
            if(!activeRoutes.length)activeRoutes=solutionRoutes.map((route,routeIndex)=>({route,routeIndex}));
            completedRoute=activeRoutes[0]?.route||replayRoute;
            view.clearSelection();
            view.render();
            message.hidden=true;
            if(mistakePanel)mistakePanel.hidden=true;
            status.textContent=index?`${index} / ${completedRoute.length}手 正解`:'考えて指してみましょう。';
            emit('lle:shogi-undo',{undone_moves:undone,result:session.result()});
        });
        root.querySelector('[data-player-restart]')?.addEventListener('click',reset);
        root.querySelector('[data-player-hint]')?.addEventListener('click',event=>{
            if(inputLocked||completed) return;
            const hints=(Array.isArray(cfg.hint_levels)?cfg.hint_levels:[]).map(value=>String(value||'').trim()).filter(Boolean);
            const fallback=String(cfg.hint||'玉の逃げ道をよく見てみましょう。').trim();
            const available=hints.length?hints:[fallback];
            if(hintLevel>=available.length) hintLevel=0;
            const shownIndex=Math.min(hintLevel,available.length-1);
            const shown=available[shownIndex];
            hintLevel=Math.min(hintLevel+1,available.length);
            session.useHint();
            announce(`<strong>ヒント${available.length>1?' '+(shownIndex+1)+' / '+available.length:''}</strong><p>${escapeHtml(shown)}</p>${hintLevel<available.length?'<p style="font-size:12px;margin-top:8px">もう一度押すと次のヒントを表示します。</p>':''}`,'hint');
            if(event?.currentTarget){
                event.currentTarget.textContent=hintLevel<available.length?`次のヒント（${hintLevel+1}/${available.length}）`:'ヒントをもう一度見る';
            }
            emit('lle:shogi-hint',{hint_level:shownIndex+1,hint_total:available.length,result:session.result()});
        });
        root.querySelector('[data-player-answer]')?.addEventListener('click',()=>{
            if(inputLocked||completed) return;
            session.markAnswerViewed();
            session.useHint();
            openReplay(false);
            announce('<strong>解答を表示しました。</strong><p>「次の手」または「自動再生」で正解手順を確認できます。</p>','hint');
            emit('lle:shogi-answer',{result:session.result()});
        });
        root.querySelector('[data-player-mistake-continue]')?.addEventListener('click',()=>{if(mistakePanel)mistakePanel.hidden=true;message.hidden=true;view.clearSelection();view.render();});
        root.querySelector('[data-player-mistake-restart]')?.addEventListener('click',reset);
        root.querySelector('[data-player-mistake-hint]')?.addEventListener('click',()=>{root.querySelector('[data-player-hint]')?.click();if(mistakePanel)mistakePanel.hidden=true;});
        root.querySelector('[data-player-result-replay]')?.addEventListener('click',()=>openReplay(false));
        root.querySelector('[data-player-result-retry]')?.addEventListener('click',()=>{reset();emit('lle:shogi-result-retry',{result:session.result()});});
        root.querySelector('[data-player-result-next]')?.addEventListener('click',()=>emit('lle:shogi-next-problem',{result:session.result()}));
        root.querySelector('[data-player-replay-first]')?.addEventListener('click',()=>setReplayStep(0));
        root.querySelector('[data-player-replay-prev]')?.addEventListener('click',()=>setReplayStep(replayIndex-1));
        root.querySelector('[data-player-replay-next]')?.addEventListener('click',()=>setReplayStep(replayIndex+1));
        root.querySelector('[data-player-replay-last]')?.addEventListener('click',()=>setReplayStep(replayRoute.length));
        root.querySelector('[data-player-replay-auto]')?.addEventListener('click',event=>{
            if(replayTimer){stopReplay();return;}
            if(replayIndex>=replayRoute.length)setReplayStep(0);
            event.currentTarget.textContent='停止';
            replayTimer=setInterval(()=>{
                if(replayIndex>=replayRoute.length){stopReplay();return;}
                replayIndex+=1;
                engine.load(cfg);
                for(let i=0;i<replayIndex;i++){const result=engine.apply(replayRoute[i]);if(!result.ok){stopReplay();break;}}
                view.clearSelection();view.render();
                const position=root.querySelector('[data-player-replay-position]');
                if(position)position.textContent=`${replayIndex} / ${replayRoute.length}手　${moveText(replayRoute[replayIndex-1])}`;
                renderReplayList();
                emit('lle:shogi-replay-step',{step:replayIndex,total:replayRoute.length,auto:true});
                if(replayIndex>=replayRoute.length)stopReplay();
            },700);
        });
        return {
            reset,
            getResult:()=>session.result(),
            getEngine:()=>engine,
            destroy:()=>{ stopReplay(); root.innerHTML=''; }
        };
    }


    function normalizeAbilityTags(raw) {
        const source = raw && typeof raw === 'object' && !Array.isArray(raw) ? raw : {};
        return Object.fromEntries(ABILITY_KEYS.map(key => {
            const value = Number(source[key] ?? 0);
            return [key, Math.max(0, Math.min(5, Number.isFinite(value) ? value : 0))];
        }));
    }

    function normalizeProblemMetadata(problem, source, index) {
        const recommendedGrades = Array.isArray(problem.recommended_grades)
            ? problem.recommended_grades.map(String).filter(Boolean)
            : (problem.recommended_grade ? [String(problem.recommended_grade)] : []);
        const timeLimit = Number(problem.time_limit_seconds ?? problem.time_limit ?? 0);
        const points = Number(problem.reward_points ?? problem.points ?? 0);
        return {
            id: String(problem.id ?? `problem-${index + 1}`),
            order: Number.isFinite(Number(problem.order)) ? Number(problem.order) : index + 1,
            difficulty: String(problem.difficulty ?? source.difficulty ?? ''),
            category: String(problem.category ?? source.category ?? '詰将棋'),
            tags: Array.isArray(problem.tags) ? [...new Set(problem.tags.map(String).filter(Boolean))] : [],
            recommended_grades: recommendedGrades,
            time_limit_seconds: Math.max(0, Number.isFinite(timeLimit) ? timeLimit : 0),
            reward_points: Math.max(0, Number.isFinite(points) ? Math.round(points) : 0),
            is_published: problem.is_published !== false && problem.published !== false,
            learning_point: String(problem.learning_point ?? ''),
            recommended_followup: String(problem.recommended_followup ?? ''),
            abilities: normalizeAbilityTags(problem.abilities ?? problem.ability_tags),
            content_version: Math.max(1, Math.round(Number(problem.content_version ?? problem.version ?? 1) || 1))
        };
    }

    function validateProblemSet(rawSet, options = {}) {
        const strict = Boolean(options.strict);
        const errors = [];
        const warnings = [];
        const source = Array.isArray(rawSet) ? { problems: rawSet } : (rawSet || {});
        const problems = Array.isArray(source.problems) ? source.problems : [];
        if (!problems.length) errors.push({ code: 'problems.empty', path: 'problems', message: '問題セットに問題が登録されていません。' });
        const ids = new Set();
        problems.forEach((problem, index) => {
            const path = `problems.${index}`;
            if (!problem || typeof problem !== 'object') {
                errors.push({ code: 'problem.invalid', path, message: `${index + 1}問目の形式が正しくありません。` });
                return;
            }
            const id = String(problem.id ?? `problem-${index + 1}`);
            if (ids.has(id)) errors.push({ code: 'problem.id.duplicate', path: `${path}.id`, message: `問題ID「${id}」が重複しています。` });
            ids.add(id);
            if (!String(problem.title ?? '').trim()) errors.push({ code: 'problem.title.required', path: `${path}.title`, message: `問題「${id}」のタイトルが未設定です。` });
            if (!(Array.isArray(problem.solution_routes) && problem.solution_routes.some(route => Array.isArray(route) && route.length)) && (!Array.isArray(problem.solution_moves) || !problem.solution_moves.length)) errors.push({ code: 'problem.solution.required', path: `${path}.solution_routes`, message: `問題「${id}」に正解手順がありません。` });
            if (typeof problem.sfen === 'string' && problem.sfen.trim()) {
                try { parseSfen(problem.sfen); } catch (error) { errors.push({ code: 'problem.sfen.invalid', path: `${path}.sfen`, message: `問題「${id}」のSFENが不正です：${error.message}` }); }
            } else if (!(Array.isArray(problem.board) && problem.board.length === 9)) {
                warnings.push({ code: 'problem.position.default', path, message: `問題「${id}」に局面がなく、標準局面が使用されます。` });
            }
            if (!String(problem.hint ?? '').trim()) warnings.push({ code: 'problem.hint.missing', path: `${path}.hint`, message: `問題「${id}」のヒントが未設定です。` });
            if (!String(problem.explanation ?? '').trim()) warnings.push({ code: 'problem.explanation.missing', path: `${path}.explanation`, message: `問題「${id}」の解説が未設定です。` });
            const abilities = normalizeAbilityTags(problem.abilities ?? problem.ability_tags);
            if (!Object.values(abilities).some(value => value > 0)) warnings.push({ code: 'problem.abilities.missing', path: `${path}.abilities`, message: `問題「${id}」の能力タグが未設定です。` });
        });
        if (strict && warnings.length) errors.push(...warnings.map(item => ({ ...item, code: `${item.code}.strict` })));
        return { valid: errors.length === 0, errors, warnings, schema: String(source.schema ?? PROBLEM_SET_SCHEMA), schema_version: String(source.schema_version ?? source.schemaVersion ?? PROBLEM_SET_SCHEMA_VERSION) };
    }

    function normalizeProblemSet(raw, options = {}) {
        const source = Array.isArray(raw) ? { problems: raw } : (raw || {});
        const validation = validateProblemSet(source, options);
        if (!validation.valid) throw new Error(validation.errors.map(item => item.message).join('\n'));
        const problems = source.problems;
        const normalized = problems.map((problem, index) => {
            const cfg = normalizeConfig(problem);
            const metadata = normalizeProblemMetadata(problem, source, index);
            return { ...cfg, ...metadata };
        }).sort((a, b) => a.order - b.order);
        return {
            schema: String(source.schema ?? PROBLEM_SET_SCHEMA),
            schema_version: String(source.schema_version ?? source.schemaVersion ?? PROBLEM_SET_SCHEMA_VERSION),
            version: Math.max(1, Math.round(Number(source.version ?? 1) || 1)),
            id: String(source.id ?? 'shogi-problem-set'),
            title: String(source.title ?? '詰将棋問題セット'),
            description: String(source.description ?? ''),
            category: String(source.category ?? '詰将棋'),
            difficulty: String(source.difficulty ?? ''),
            shuffle: Boolean(source.shuffle),
            is_published: source.is_published !== false && source.published !== false,
            validation: deepClone(validation),
            problems: options.includeUnpublished ? normalized : normalized.filter(problem => problem.is_published)
        };
    }

    async function loadProblemSet(source, options = {}) {
        if (typeof source !== 'string') return normalizeProblemSet(source);
        const response = await fetch(source, {
            method: 'GET',
            credentials: options.credentials || 'same-origin',
            headers: { Accept: 'application/json', ...(options.headers || {}) }
        });
        if (!response.ok) throw new Error(`問題データの読込に失敗しました。（HTTP ${response.status}）`);
        return normalizeProblemSet(await response.json());
    }

    function shuffledIndexes(length) {
        const indexes = Array.from({ length }, (_, index) => index);
        for (let i = indexes.length - 1; i > 0; i -= 1) {
            const j = Math.floor(Math.random() * (i + 1));
            [indexes[i], indexes[j]] = [indexes[j], indexes[i]];
        }
        return indexes;
    }

    function summarizeProblemResults(results, total) {
        const list = Array.isArray(results) ? results : [];
        const completed = list.filter(result => result?.correct);
        const scoreTotal = completed.reduce((sum, result) => sum + Number(result.evaluation?.score || 0), 0);
        const elapsedTotal = completed.reduce((sum, result) => sum + Number(result.elapsed_ms || 0), 0);
        return {
            total: Number(total) || list.length,
            completed: completed.length,
            completion_rate: total ? Math.round((completed.length / total) * 100) : 0,
            average_score: completed.length ? Math.round(scoreTotal / completed.length) : 0,
            total_elapsed_ms: elapsedTotal,
            hints: completed.reduce((sum, result) => sum + Number(result.hint_count || 0), 0),
            retries: completed.reduce((sum, result) => sum + Number(result.retry_count || 0), 0),
            perfect_count: completed.filter(result => result.evaluation?.perfect).length
        };
    }


    function createProblemSetStorage(setId, options = {}) {
        const enabled = options.persist !== false && typeof window !== 'undefined' && Boolean(window.localStorage);
        const key = String(options.storageKey || `lle:shogi:problem-set:${setId}:v1`);
        const empty = () => ({ version: 1, set_id: String(setId), cursor: 0, order: [], results: {}, achievements: [], updated_at: null });
        const read = () => {
            if (!enabled) return empty();
            try {
                const value = JSON.parse(window.localStorage.getItem(key) || 'null');
                if (!value || value.version !== 1 || String(value.set_id) !== String(setId)) return empty();
                return { ...empty(), ...value, results: value.results && typeof value.results === 'object' ? value.results : {}, achievements: Array.isArray(value.achievements) ? value.achievements.map(String) : [] };
            } catch (_) { return empty(); }
        };
        const write = value => {
            if (!enabled) return;
            try {
                window.localStorage.setItem(key, JSON.stringify({ ...value, version: 1, set_id: String(setId), updated_at: new Date().toISOString() }));
            } catch (_) {}
        };
        const clear = () => {
            if (!enabled) return;
            try { window.localStorage.removeItem(key); } catch (_) {}
        };
        return { enabled, key, read, write, clear };
    }

    function chooseBetterProblemResult(current, incoming) {
        if (!current) return incoming;
        const currentScore = Number(current.evaluation?.score || 0);
        const incomingScore = Number(incoming.evaluation?.score || 0);
        if (incomingScore !== currentScore) return incomingScore > currentScore ? incoming : current;
        const currentElapsed = Number(current.elapsed_ms || Number.MAX_SAFE_INTEGER);
        const incomingElapsed = Number(incoming.elapsed_ms || Number.MAX_SAFE_INTEGER);
        return incomingElapsed < currentElapsed ? incoming : current;
    }

    function mountProblemSet(root, rawSet, options = {}) {
        const set = normalizeProblemSet(rawSet);
        const storage = createProblemSetStorage(set.id, options);
        const saved = storage.read();
        const validSavedOrder = Array.isArray(saved.order) && saved.order.length === set.problems.length && saved.order.every(index => Number.isInteger(index) && index >= 0 && index < set.problems.length);
        const order = validSavedOrder ? saved.order.slice() : ((options.shuffle ?? set.shuffle) ? shuffledIndexes(set.problems.length) : set.problems.map((_, index) => index));
        const requestedStart = options.startIndex !== undefined ? Number(options.startIndex) : Number(saved.cursor);
        let cursor = Math.min(Math.max(Number.isFinite(requestedStart) ? requestedStart : 0, 0), order.length - 1);
        let player = null;
        const results = new Map();
        Object.entries(saved.results || {}).forEach(([problemId, result]) => {
            if (set.problems.some(problem => problem.id === problemId) && result?.correct) results.set(problemId, result);
        });
        let achievements = Array.isArray(saved.achievements) ? saved.achievements.slice() : [];
        root.innerHTML = `<section class="lle-shogi-problem-set"><header class="lle-shogi-problem-set-header"><div><span data-set-progress></span><h2>${escapeHtml(set.title)}</h2>${set.description ? `<p>${escapeHtml(set.description)}</p>` : ''}<small data-set-saved-status></small></div><div class="lle-shogi-problem-set-nav"><button type="button" data-set-prev>前の問題</button><button type="button" data-set-random>ランダム</button><button type="button" data-set-next>次の問題</button><button type="button" data-set-reset-progress>進捗をリセット</button></div></header><div data-set-player></div></section>`;
        const playerRoot = root.querySelector('[data-set-player]');
        const progress = root.querySelector('[data-set-progress]');
        const savedStatus = root.querySelector('[data-set-saved-status]');
        const prev = root.querySelector('[data-set-prev]');
        const next = root.querySelector('[data-set-next]');
        const emit = (name, detail = {}) => root.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: { type: STEP_KEY, set_id: set.id, ...detail } }));
        const currentProblem = () => set.problems[order[cursor]];
        const persist = () => {
            storage.write({ cursor, order, results: Object.fromEntries(results), achievements });
            if (savedStatus) savedStatus.textContent = storage.enabled ? `進捗保存済み（${results.size}/${order.length}問）` : '';
        };
        const render = () => {
            player?.destroy?.();
            const problem = currentProblem();
            const completedMark = results.has(problem.id) ? '　✓クリア済み' : '';
            progress.textContent = `${cursor + 1} / ${order.length}　${problem.difficulty || problem.category}${completedMark}`;
            prev.disabled = cursor <= 0;
            next.disabled = cursor >= order.length - 1;
            player = mountPlayer(playerRoot, problem);
            persist();
            emit('lle:shogi-problem-change', { problem_id: problem.id, index: cursor, total: order.length, restored: results.has(problem.id) });
        };
        const go = index => {
            const bounded = Math.min(Math.max(index, 0), order.length - 1);
            if (bounded === cursor) return;
            cursor = bounded;
            render();
        };
        playerRoot.addEventListener('lle:component-complete', event => {
            const problem = currentProblem();
            const result = { ...deepClone(event.detail?.result || {}), problem_id: problem.id, set_id: set.id };
            results.set(problem.id, chooseBetterProblemResult(results.get(problem.id), result));
            const achievementResult = evaluateAchievements(set, Array.from(results.values()), achievements);
            achievements = achievementResult.unlocked;
            persist();
            emit('lle:shogi-problem-complete', { problem_id: problem.id, result, best_result: deepClone(results.get(problem.id)), completed_count: results.size, total: order.length, analytics: achievementResult.analytics });
            achievementResult.newly_unlocked.forEach(achievement => emit('lle:shogi-achievement-unlocked', { achievement, achievements: deepClone(achievements) }));
            if (results.size === order.length) {
                const values = Array.from(results.values());
                emit('lle:shogi-set-complete', { results: values, summary: summarizeProblemResults(values, order.length), total: order.length });
            }
        });
        prev.addEventListener('click', () => go(cursor - 1));
        next.addEventListener('click', () => go(cursor + 1));
        root.querySelector('[data-set-random]').addEventListener('click', () => {
            if (order.length < 2) return;
            let nextCursor = cursor;
            while (nextCursor === cursor) nextCursor = Math.floor(Math.random() * order.length);
            go(nextCursor);
        });
        root.querySelector('[data-set-reset-progress]').addEventListener('click', () => {
            if (!window.confirm('この問題セットの保存済み進捗をリセットしますか？')) return;
            results.clear();
            achievements = [];
            cursor = 0;
            storage.clear();
            render();
            emit('lle:shogi-progress-reset', { total: order.length });
        });
        render();
        return {
            next: () => go(cursor + 1),
            previous: () => go(cursor - 1),
            goTo: index => go(Number(index) || 0),
            getCurrentProblem: () => deepClone(currentProblem()),
            getResults: () => Array.from(results.values()).map(deepClone),
            getSummary: () => deepClone(summarizeProblemResults(Array.from(results.values()), order.length)),
            getAnalytics: () => deepClone(buildProblemSetAnalytics(set, Array.from(results.values()))),
            getAchievements: () => deepClone(achievements),
            getProgress: () => ({ current: cursor + 1, total: order.length, completed: results.size, persisted: storage.enabled, storage_key: storage.key }),
            clearProgress: () => { results.clear(); cursor = 0; storage.clear(); render(); },
            destroy: () => { player?.destroy?.(); root.innerHTML = ''; }
        };
    }


    function buildProblemSetAnalytics(set, results) {
        const resultMap = new Map((Array.isArray(results) ? results : []).map(result => [String(result.problem_id || ''), result]));
        const group = (key, label) => {
            const buckets = new Map();
            set.problems.forEach(problem => {
                const values = key === 'tags' ? (problem.tags.length ? problem.tags : ['未設定']) : [String(problem[key] || '未設定')];
                values.forEach(value => {
                    if (!buckets.has(value)) buckets.set(value, { key: value, label: String(value), total: 0, completed: 0, score_total: 0, elapsed_total_ms: 0, hints: 0, retries: 0 });
                    const bucket = buckets.get(value);
                    bucket.total += 1;
                    const result = resultMap.get(problem.id);
                    if (!result?.correct) return;
                    bucket.completed += 1;
                    bucket.score_total += Number(result.evaluation?.score || 0);
                    bucket.elapsed_total_ms += Number(result.elapsed_ms || 0);
                    bucket.hints += Number(result.hint_count || 0);
                    bucket.retries += Number(result.retry_count || 0);
                });
            });
            return Array.from(buckets.values()).map(bucket => ({
                ...bucket,
                group: label,
                completion_rate: bucket.total ? Math.round((bucket.completed / bucket.total) * 100) : 0,
                average_score: bucket.completed ? Math.round(bucket.score_total / bucket.completed) : 0,
                average_elapsed_ms: bucket.completed ? Math.round(bucket.elapsed_total_ms / bucket.completed) : 0
            }));
        };
        return {
            overall: summarizeProblemResults(Array.from(resultMap.values()), set.problems.length),
            by_difficulty: group('difficulty', 'difficulty'),
            by_category: group('category', 'category'),
            by_tag: group('tags', 'tag')
        };
    }

    const SHOGI_ACHIEVEMENTS = [
        { id: 'first-clear', title: 'はじめの一歩', description: '初めて問題をクリア', test: context => context.summary.completed >= 1 },
        { id: 'three-clears', title: '三問突破', description: '3問以上をクリア', test: context => context.summary.completed >= 3 },
        { id: 'ten-clears', title: '十問突破', description: '10問以上をクリア', test: context => context.summary.completed >= 10 },
        { id: 'perfect-one', title: '完全勝利', description: 'ノーミス・ノーヒントで1問クリア', test: context => context.summary.perfect_count >= 1 },
        { id: 'perfect-five', title: '読みの達人', description: 'パーフェクトを5問達成', test: context => context.summary.perfect_count >= 5 },
        { id: 'score-90', title: '高得点', description: '平均90点以上を達成', test: context => context.summary.completed >= 3 && context.summary.average_score >= 90 },
        { id: 'all-clear', title: '全問制覇', description: '問題セットを全問クリア', test: context => context.summary.total > 0 && context.summary.completed === context.summary.total }
    ];

    function evaluateAchievements(set, results, previouslyUnlocked = []) {
        const unlocked = new Set(Array.isArray(previouslyUnlocked) ? previouslyUnlocked.map(String) : []);
        const analytics = buildProblemSetAnalytics(set, results);
        const context = { set, results, summary: analytics.overall, analytics };
        const newlyUnlocked = [];
        SHOGI_ACHIEVEMENTS.forEach(achievement => {
            if (unlocked.has(achievement.id) || !achievement.test(context)) return;
            unlocked.add(achievement.id);
            newlyUnlocked.push({ id: achievement.id, title: achievement.title, description: achievement.description, unlocked_at: new Date().toISOString() });
        });
        return { unlocked: Array.from(unlocked), newly_unlocked: newlyUnlocked, analytics };
    }


    function createLearningLogSync(options = {}) {
        const endpoint = String(options.endpoint || '').trim();
        const setId = String(options.setId || options.set_id || 'default');
        const studentId = options.studentId ?? options.student_id ?? null;
        const storageKey = String(options.storageKey || `lle:shogi-sync:${setId}:${studentId ?? 'guest'}`);
        const maxRetries = Math.max(0, Number(options.maxRetries ?? 3));
        const retryDelayMs = Math.max(250, Number(options.retryDelayMs ?? 1500));
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', ...(options.headers || {}) };
        const csrf = options.csrfToken || (typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.content : null);
        if (csrf && !headers['X-CSRF-TOKEN']) headers['X-CSRF-TOKEN'] = csrf;
        let destroyed = false;
        let syncing = false;
        let listeners = [];

        const readQueue = () => {
            if (typeof window === 'undefined' || !window.localStorage) return [];
            try {
                const value = JSON.parse(window.localStorage.getItem(storageKey) || '[]');
                return Array.isArray(value) ? value : [];
            } catch (_) { return []; }
        };
        const writeQueue = queue => {
            if (typeof window === 'undefined' || !window.localStorage) return;
            try { window.localStorage.setItem(storageKey, JSON.stringify(queue)); } catch (_) {}
        };
        const emitSync = (name, detail) => {
            if (typeof window === 'undefined') return;
            window.dispatchEvent(new CustomEvent(name, { detail }));
        };
        const makeItem = (type, payload) => ({
            id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            type,
            payload: { set_id: setId, student_id: studentId, ...deepClone(payload || {}) },
            attempts: 0,
            created_at: new Date().toISOString()
        });
        const enqueue = (type, payload) => {
            const queue = readQueue();
            const item = makeItem(type, payload);
            queue.push(item);
            writeQueue(queue);
            emitSync('lle:shogi-sync-queued', { item: deepClone(item), pending: queue.length });
            if (options.autoSync !== false) void flush();
            return deepClone(item);
        };
        const send = async item => {
            if (!endpoint) throw new Error('学習ログ送信先が設定されていません。');
            const response = await fetch(endpoint, {
                method: options.method || 'POST',
                credentials: options.credentials || 'same-origin',
                headers,
                body: JSON.stringify({ event_type: item.type, event_id: item.id, ...item.payload })
            });
            if (!response.ok) throw new Error(`学習ログ送信に失敗しました（HTTP ${response.status}）。`);
            const contentType = response.headers.get('content-type') || '';
            return contentType.includes('application/json') ? response.json() : { ok: true };
        };
        async function flush() {
            if (destroyed || syncing || !endpoint || typeof fetch !== 'function') return { sent: 0, pending: readQueue().length };
            syncing = true;
            let queue = readQueue();
            let sent = 0;
            try {
                while (queue.length && !destroyed) {
                    const item = queue[0];
                    try {
                        const response = await send(item);
                        queue.shift();
                        sent += 1;
                        writeQueue(queue);
                        emitSync('lle:shogi-sync-success', { item: deepClone(item), response, pending: queue.length });
                    } catch (error) {
                        item.attempts = Number(item.attempts || 0) + 1;
                        item.last_error = String(error?.message || error);
                        item.last_attempt_at = new Date().toISOString();
                        if (item.attempts > maxRetries) {
                            queue.shift();
                            writeQueue(queue);
                            emitSync('lle:shogi-sync-failed', { item: deepClone(item), error: item.last_error, pending: queue.length });
                            continue;
                        }
                        writeQueue(queue);
                        emitSync('lle:shogi-sync-retry', { item: deepClone(item), pending: queue.length });
                        await new Promise(resolve => setTimeout(resolve, retryDelayMs * item.attempts));
                    }
                }
            } finally {
                syncing = false;
            }
            return { sent, pending: readQueue().length };
        }
        const bind = (eventName, type, mapper = detail => detail) => {
            if (typeof window === 'undefined') return;
            const handler = event => enqueue(type, mapper(event.detail || {}));
            window.addEventListener(eventName, handler);
            listeners.push([eventName, handler]);
        };
        if (options.bindEvents !== false) {
            bind('lle:shogi-problem-complete', 'problem_complete');
            bind('lle:shogi-set-complete', 'set_complete');
            bind('lle:shogi-achievement-unlocked', 'achievement_unlocked');
            bind('lle:shogi-progress-reset', 'progress_reset');
            if (typeof window !== 'undefined') {
                const onlineHandler = () => void flush();
                window.addEventListener('online', onlineHandler);
                listeners.push(['online', onlineHandler]);
            }
        }
        return {
            enqueue,
            flush,
            getQueue: () => deepClone(readQueue()),
            getPendingCount: () => readQueue().length,
            clearQueue: () => { writeQueue([]); emitSync('lle:shogi-sync-cleared', { pending: 0 }); },
            isSyncing: () => syncing,
            destroy: () => {
                destroyed = true;
                if (typeof window !== 'undefined') listeners.forEach(([name, handler]) => window.removeEventListener(name, handler));
                listeners = [];
            }
        };
    }


    function formatJapaneseMove(move, index = 0) {
        if (!move) return '';
        const files = ['９','８','７','６','５','４','３','２','１'];
        const ranks = ['一','二','三','四','五','六','七','八','九'];
        const destination = move.to ? `${files[move.to.col] || ''}${ranks[move.to.row] || ''}` : '';
        const piece = LABELS[move.resultType || move.piece] || LABELS[move.piece] || move.piece || '';
        const prefix = index % 2 === 0 ? '☗' : '☖';
        const suffix = move.kind === 'drop' ? '打' : move.promote ? '成' : '';
        return `${prefix}${destination}${piece}${suffix}`;
    }

    function createReplaySession(rawConfig, rawMoves = [], options = {}) {
        const config = normalizeConfig(rawConfig);
        const moves = Array.isArray(rawMoves) ? rawMoves.map(deepClone) : [];
        const engine = new ShogiEngine(config);
        const states = [engine.snapshot()];
        const appliedMoves = [];
        const errors = [];

        moves.forEach((move, index) => {
            const result = engine.apply(deepClone(move));
            if (!result.ok) {
                errors.push({ index, move: deepClone(move), message: result.message || '指し手を再生できません。' });
                return;
            }
            appliedMoves.push(deepClone(move));
            states.push(engine.snapshot());
        });

        let cursor = 0;
        const listeners = new Set();
        const notify = () => {
            const payload = api.getState();
            listeners.forEach(listener => {
                try { listener(payload); } catch (_) {}
            });
            if (typeof options.onChange === 'function') options.onChange(payload);
        };
        const goTo = value => {
            const next = Math.max(0, Math.min(Number(value) || 0, states.length - 1));
            cursor = next;
            notify();
            return api.getState();
        };
        const api = {
            first: () => goTo(0),
            last: () => goTo(states.length - 1),
            next: () => goTo(cursor + 1),
            previous: () => goTo(cursor - 1),
            goTo,
            canNext: () => cursor < states.length - 1,
            canPrevious: () => cursor > 0,
            getIndex: () => cursor,
            getMoveCount: () => appliedMoves.length,
            getCurrentMove: () => cursor > 0 ? deepClone(appliedMoves[cursor - 1]) : null,
            getMoves: () => appliedMoves.map(deepClone),
            getErrors: () => errors.map(deepClone),
            getState: () => {
                const state = states[cursor];
                return {
                    index: cursor,
                    total: appliedMoves.length,
                    board: deepClone(state.board),
                    hands: deepClone(state.hands),
                    turn: state.turn,
                    last_move: deepClone(state.lastMove),
                    sfen: generateSfen(state.board, state.hands, state.turn, cursor + 1),
                    move: cursor > 0 ? deepClone(appliedMoves[cursor - 1]) : null,
                    move_text: cursor > 0 ? formatJapaneseMove(appliedMoves[cursor - 1], cursor - 1) : '開始局面'
                };
            },
            getMoveList: () => appliedMoves.map((move, index) => ({
                number: index + 1,
                text: formatJapaneseMove(move, index),
                move: deepClone(move),
                current: cursor === index + 1
            })),
            subscribe: listener => {
                if (typeof listener !== 'function') return () => {};
                listeners.add(listener);
                listener(api.getState());
                return () => listeners.delete(listener);
            },
            destroy: () => listeners.clear()
        };
        return api;
    }

    function exportReplayRecord(rawConfig, rawMoves = [], metadata = {}) {
        const replay = createReplaySession(rawConfig, rawMoves);
        const finalState = replay.last();
        const record = {
            format: 'lle-shogi-record',
            version: 1,
            title: String(metadata.title || rawConfig?.title || ''),
            problem_id: metadata.problem_id ?? rawConfig?.id ?? null,
            started_sfen: generateSfen(normalizeConfig(rawConfig).board, normalizeConfig(rawConfig).hands, normalizeConfig(rawConfig).turn, 1),
            final_sfen: finalState.sfen,
            move_count: replay.getMoveCount(),
            moves: replay.getMoveList().map(item => ({ number: item.number, text: item.text, move: item.move })),
            errors: replay.getErrors(),
            exported_at: new Date().toISOString()
        };
        replay.destroy();
        return record;
    }


    function createProblemRepository(rawSet = {}, options = {}) {
        const source = Array.isArray(rawSet) ? { problems: rawSet } : deepClone(rawSet || {});
        const listeners = new Set();
        const storageKey = String(options.storageKey || `lle:shogi:problem-repository:${source.id || 'default'}`);
        const storageEnabled = options.persist === true && typeof window !== 'undefined' && Boolean(window.localStorage);
        let set = {
            schema: String(source.schema || PROBLEM_SET_SCHEMA),
            schema_version: String(source.schema_version || source.schemaVersion || PROBLEM_SET_SCHEMA_VERSION),
            version: Math.max(1, Math.round(Number(source.version || 1) || 1)),
            id: String(source.id || 'shogi-problem-set'),
            title: String(source.title || '詰将棋問題セット'),
            description: String(source.description || ''),
            category: String(source.category || '詰将棋'),
            difficulty: String(source.difficulty || ''),
            shuffle: Boolean(source.shuffle),
            is_published: source.is_published !== false && source.published !== false,
            problems: Array.isArray(source.problems) ? source.problems.map(deepClone) : []
        };

        const persist = () => {
            if (!storageEnabled) return;
            try { window.localStorage.setItem(storageKey, JSON.stringify({ ...set, saved_at: new Date().toISOString() })); } catch (_) {}
        };
        const emitChange = (type, detail = {}) => {
            const payload = { type, detail: deepClone(detail), set: api.getSet(), validation: api.validate() };
            listeners.forEach(listener => { try { listener(payload); } catch (_) {} });
            if (typeof options.onChange === 'function') options.onChange(payload);
            persist();
            return payload;
        };
        const nextId = () => {
            const used = new Set(set.problems.map(problem => String(problem.id || '')));
            let index = set.problems.length + 1;
            let id = `problem-${index}`;
            while (used.has(id)) { index += 1; id = `problem-${index}`; }
            return id;
        };
        const resequence = () => {
            set.problems.forEach((problem, index) => { problem.order = index + 1; });
        };
        const findIndex = id => set.problems.findIndex(problem => String(problem.id) === String(id));
        const normalizeForList = (problem, index) => ({
            ...deepClone(problem),
            ...normalizeProblemMetadata(problem, set, index),
            title: String(problem.title || ''),
            hint: String(problem.hint || ''),
            explanation: String(problem.explanation || '')
        });

        if (storageEnabled && options.restore !== false) {
            try {
                const saved = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
                if (saved && Array.isArray(saved.problems)) set = { ...set, ...saved, problems: saved.problems.map(deepClone) };
            } catch (_) {}
        }
        resequence();

        const api = {
            getSet: () => deepClone(set),
            getProblem: id => {
                const index = findIndex(id);
                return index < 0 ? null : normalizeForList(set.problems[index], index);
            },
            list: (filters = {}) => {
                const query = String(filters.query || '').trim().toLowerCase();
                let list = set.problems.map(normalizeForList);
                if (query) list = list.filter(problem => [problem.id, problem.title, problem.category, problem.difficulty, ...(problem.tags || [])].join(' ').toLowerCase().includes(query));
                if (filters.category) list = list.filter(problem => problem.category === String(filters.category));
                if (filters.difficulty) list = list.filter(problem => problem.difficulty === String(filters.difficulty));
                if (filters.published !== undefined) list = list.filter(problem => problem.is_published === Boolean(filters.published));
                if (filters.tag) list = list.filter(problem => problem.tags.includes(String(filters.tag)));
                const sortBy = String(filters.sortBy || 'order');
                const direction = filters.direction === 'desc' ? -1 : 1;
                list.sort((a, b) => {
                    const av = a[sortBy] ?? '';
                    const bv = b[sortBy] ?? '';
                    return (typeof av === 'number' && typeof bv === 'number' ? av - bv : String(av).localeCompare(String(bv), 'ja')) * direction;
                });
                return deepClone(list);
            },
            updateSet: patch => {
                const allowed = ['title','description','category','difficulty','shuffle','is_published','version'];
                allowed.forEach(key => { if (Object.prototype.hasOwnProperty.call(patch || {}, key)) set[key] = deepClone(patch[key]); });
                set.version = Math.max(1, Math.round(Number(set.version || 1) || 1));
                emitChange('set.updated', { patch });
                return api.getSet();
            },
            create: data => {
                const problem = {
                    id: String(data?.id || nextId()),
                    title: String(data?.title || '新しい問題'),
                    order: set.problems.length + 1,
                    category: String(data?.category || set.category || '詰将棋'),
                    difficulty: String(data?.difficulty || set.difficulty || ''),
                    tags: Array.isArray(data?.tags) ? [...new Set(data.tags.map(String).filter(Boolean))] : [],
                    is_published: data?.is_published === true,
                    content_version: 1,
                    abilities: normalizeAbilityTags(data?.abilities),
                    solution_moves: Array.isArray(data?.solution_moves) ? deepClone(data.solution_moves) : [], solution_routes: Array.isArray(data?.solution_routes) ? deepClone(data.solution_routes) : undefined,
                    ...deepClone(data || {})
                };
                if (findIndex(problem.id) >= 0) throw new Error(`問題ID「${problem.id}」は既に使用されています。`);
                set.problems.push(problem);
                resequence();
                emitChange('problem.created', { id: problem.id });
                return api.getProblem(problem.id);
            },
            update: (id, patch) => {
                const index = findIndex(id);
                if (index < 0) throw new Error(`問題ID「${id}」が見つかりません。`);
                const next = { ...set.problems[index], ...deepClone(patch || {}) };
                const nextIdValue = String(next.id || id);
                const duplicateIndex = findIndex(nextIdValue);
                if (duplicateIndex >= 0 && duplicateIndex !== index) throw new Error(`問題ID「${nextIdValue}」は既に使用されています。`);
                next.id = nextIdValue;
                next.content_version = Math.max(1, Math.round(Number(next.content_version || 1) || 1));
                set.problems[index] = next;
                resequence();
                emitChange('problem.updated', { id: next.id, previous_id: String(id), patch });
                return api.getProblem(next.id);
            },
            duplicate: (id, overrides = {}) => {
                const sourceProblem = api.getProblem(id);
                if (!sourceProblem) throw new Error(`問題ID「${id}」が見つかりません。`);
                delete sourceProblem.order;
                return api.create({ ...sourceProblem, id: overrides.id || nextId(), title: overrides.title || `${sourceProblem.title}（コピー）`, is_published: false, ...overrides });
            },
            remove: id => {
                const index = findIndex(id);
                if (index < 0) return false;
                const [removed] = set.problems.splice(index, 1);
                resequence();
                emitChange('problem.removed', { id: removed.id });
                return true;
            },
            reorder: ids => {
                if (!Array.isArray(ids)) throw new Error('並び順は問題IDの配列で指定してください。');
                const map = new Map(set.problems.map(problem => [String(problem.id), problem]));
                const ordered = [];
                ids.map(String).forEach(id => { if (map.has(id)) { ordered.push(map.get(id)); map.delete(id); } });
                ordered.push(...map.values());
                set.problems = ordered;
                resequence();
                emitChange('problems.reordered', { ids: set.problems.map(problem => problem.id) });
                return api.list();
            },
            setPublished: (id, published) => api.update(id, { is_published: Boolean(published) }),
            validate: validationOptions => deepClone(validateProblemSet(set, validationOptions)),
            export: exportOptions => {
                const output = deepClone(set);
                output.version = Math.max(1, Math.round(Number(output.version || 1) || 1));
                output.exported_at = new Date().toISOString();
                if (exportOptions?.publishedOnly) output.problems = output.problems.filter(problem => problem.is_published !== false && problem.published !== false);
                if (exportOptions?.validate !== false) {
                    const validation = validateProblemSet(output, { strict: Boolean(exportOptions?.strict) });
                    if (!validation.valid) throw new Error(validation.errors.map(item => item.message).join('\n'));
                }
                return output;
            },
            import: (nextSet, importOptions = {}) => {
                const incoming = Array.isArray(nextSet) ? { problems: nextSet } : deepClone(nextSet || {});
                if (!Array.isArray(incoming.problems)) throw new Error('取込データにproblems配列がありません。');
                if (importOptions.merge) {
                    incoming.problems.forEach(problem => {
                        const index = findIndex(problem.id);
                        if (index >= 0) set.problems[index] = { ...set.problems[index], ...deepClone(problem) };
                        else set.problems.push(deepClone(problem));
                    });
                    set = { ...set, ...Object.fromEntries(Object.entries(incoming).filter(([key]) => key !== 'problems')), problems: set.problems };
                } else {
                    set = { ...set, ...incoming, problems: incoming.problems.map(deepClone) };
                }
                resequence();
                emitChange('set.imported', { merge: Boolean(importOptions.merge), count: incoming.problems.length });
                return api.getSet();
            },
            subscribe: listener => {
                if (typeof listener !== 'function') return () => {};
                listeners.add(listener);
                return () => listeners.delete(listener);
            },
            clearSaved: () => {
                if (!storageEnabled) return false;
                try { window.localStorage.removeItem(storageKey); return true; } catch (_) { return false; }
            },
            destroy: () => listeners.clear()
        };
        return api;
    }


    function createProblemAdminSession(rawSet = {}, options = {}) {
        const repository = options.repository || createProblemRepository(rawSet, options.repositoryOptions || {});
        const listeners = new Set();
        const selectedIds = new Set();
        let filters = {
            query: '',
            category: '',
            difficulty: '',
            published: undefined,
            tag: '',
            sortBy: 'order',
            direction: 'asc'
        };
        let pagination = {
            page: 1,
            perPage: Math.max(1, Math.min(200, Math.round(Number(options.perPage || 20) || 20)))
        };
        let draft = null;
        let draftOriginal = null;
        let lastValidation = repository.validate();

        const emit = (type, detail = {}) => {
            const payload = {
                type,
                detail: deepClone(detail),
                state: api.getState()
            };
            listeners.forEach(listener => { try { listener(payload); } catch (_) {} });
            if (typeof options.onChange === 'function') options.onChange(payload);
            return payload;
        };
        const resetPage = () => { pagination.page = 1; };
        const getFiltered = () => repository.list(filters);
        const cleanSelection = () => {
            const existing = new Set(repository.list().map(problem => String(problem.id)));
            [...selectedIds].forEach(id => { if (!existing.has(id)) selectedIds.delete(id); });
        };
        const normalizeDraft = value => {
            const source = deepClone(value || {});
            source.id = String(source.id || '');
            source.title = String(source.title || '');
            source.category = String(source.category || '');
            source.difficulty = String(source.difficulty || '');
            source.tags = Array.isArray(source.tags) ? [...new Set(source.tags.map(String).map(tag => tag.trim()).filter(Boolean))] : [];
            source.is_published = source.is_published === true;
            source.hint = String(source.hint || '');
            source.explanation = String(source.explanation || '');
            source.learning_point = String(source.learning_point || '');
            source.recommended_followup = String(source.recommended_followup || '');
            source.solution_routes = Array.isArray(source.solution_routes) ? deepClone(source.solution_routes) : [Array.isArray(source.solution_moves) ? deepClone(source.solution_moves) : []];
            source.solution_moves = deepClone(source.solution_routes[0] || []);
            source.abilities = normalizeAbilityTags(source.abilities);
            return source;
        };
        const validateDraft = value => {
            const candidate = normalizeDraft(value);
            const currentSet = repository.getSet();
            const problems = currentSet.problems.filter(problem => String(problem.id) !== String(draftOriginal?.id || ''));
            problems.push(candidate);
            const result = validateProblemSet({ ...currentSet, problems }, { strict: false });
            const ownErrors = result.errors.filter(item => !item.problem_id || String(item.problem_id) === candidate.id);
            const ownWarnings = result.warnings.filter(item => !item.problem_id || String(item.problem_id) === candidate.id);
            return {
                valid: ownErrors.length === 0,
                errors: deepClone(ownErrors),
                warnings: deepClone(ownWarnings)
            };
        };

        const unsubscribeRepository = repository.subscribe(event => {
            lastValidation = repository.validate();
            cleanSelection();
            emit('repository.changed', { repository_event: event.type });
        });

        const api = {
            repository,
            getState: () => {
                const filtered = getFiltered();
                const total = filtered.length;
                const totalPages = Math.max(1, Math.ceil(total / pagination.perPage));
                const page = Math.min(Math.max(1, pagination.page), totalPages);
                const start = (page - 1) * pagination.perPage;
                const rows = filtered.slice(start, start + pagination.perPage);
                return {
                    filters: deepClone(filters),
                    pagination: { ...pagination, page, total, totalPages },
                    rows: deepClone(rows),
                    selected_ids: [...selectedIds],
                    selected_count: selectedIds.size,
                    all_visible_selected: rows.length > 0 && rows.every(row => selectedIds.has(String(row.id))),
                    draft: draft ? deepClone(draft) : null,
                    draft_dirty: Boolean(draft && JSON.stringify(normalizeDraft(draft)) !== JSON.stringify(normalizeDraft(draftOriginal))),
                    draft_validation: draft ? validateDraft(draft) : null,
                    validation: deepClone(lastValidation),
                    summary: api.getSummary()
                };
            },
            getSummary: () => {
                const all = repository.list();
                const published = all.filter(problem => problem.is_published).length;
                const categories = [...new Set(all.map(problem => problem.category).filter(Boolean))];
                const difficulties = [...new Set(all.map(problem => problem.difficulty).filter(Boolean))];
                return {
                    total: all.length,
                    published,
                    unpublished: all.length - published,
                    selected: selectedIds.size,
                    categories,
                    difficulties,
                    error_count: lastValidation.errors.length,
                    warning_count: lastValidation.warnings.length
                };
            },
            setFilters: patch => {
                filters = { ...filters, ...deepClone(patch || {}) };
                if (Object.prototype.hasOwnProperty.call(patch || {}, 'published') && patch.published === null) filters.published = undefined;
                resetPage();
                emit('filters.changed', { filters });
                return api.getState();
            },
            clearFilters: () => {
                filters = { query:'', category:'', difficulty:'', published:undefined, tag:'', sortBy:'order', direction:'asc' };
                resetPage();
                emit('filters.cleared');
                return api.getState();
            },
            setPage: page => {
                pagination.page = Math.max(1, Math.round(Number(page) || 1));
                emit('page.changed', { page: pagination.page });
                return api.getState();
            },
            setPerPage: perPage => {
                pagination.perPage = Math.max(1, Math.min(200, Math.round(Number(perPage) || 20)));
                resetPage();
                emit('per-page.changed', { per_page: pagination.perPage });
                return api.getState();
            },
            select: (id, selected = true) => {
                const key = String(id);
                if (selected) selectedIds.add(key); else selectedIds.delete(key);
                cleanSelection();
                emit('selection.changed', { id:key, selected:Boolean(selected) });
                return [...selectedIds];
            },
            toggleSelect: id => api.select(id, !selectedIds.has(String(id))),
            selectVisible: (selected = true) => {
                const state = api.getState();
                state.rows.forEach(row => { if (selected) selectedIds.add(String(row.id)); else selectedIds.delete(String(row.id)); });
                emit('selection.visible', { selected:Boolean(selected), ids:state.rows.map(row => row.id) });
                return [...selectedIds];
            },
            clearSelection: () => {
                selectedIds.clear();
                emit('selection.cleared');
                return [];
            },
            beginCreate: initial => {
                draftOriginal = null;
                draft = normalizeDraft({
                    id: '', title: '新しい問題', category: repository.getSet().category || '詰将棋',
                    difficulty: repository.getSet().difficulty || '', is_published: false,
                    tags: [], abilities: {}, solution_moves: [], solution_routes: [[]], ...deepClone(initial || {})
                });
                emit('draft.created');
                return deepClone(draft);
            },
            beginEdit: id => {
                const problem = repository.getProblem(id);
                if (!problem) throw new Error(`問題ID「${id}」が見つかりません。`);
                draftOriginal = normalizeDraft(problem);
                draft = deepClone(draftOriginal);
                emit('draft.opened', { id:String(id) });
                return deepClone(draft);
            },
            patchDraft: patch => {
                if (!draft) throw new Error('編集中の問題がありません。');
                draft = normalizeDraft({ ...draft, ...deepClone(patch || {}) });
                emit('draft.changed', { patch });
                return deepClone(draft);
            },
            cancelDraft: () => {
                const previousId = draft?.id || draftOriginal?.id || null;
                draft = null;
                draftOriginal = null;
                emit('draft.cancelled', { id:previousId });
                return true;
            },
            saveDraft: saveOptions => {
                if (!draft) throw new Error('保存する問題がありません。');
                const normalized = normalizeDraft(draft);
                const validation = validateDraft(normalized);
                if (!validation.valid) throw new Error(validation.errors.map(item => item.message).join('\n'));
                let saved;
                if (draftOriginal) saved = repository.update(draftOriginal.id, normalized);
                else saved = repository.create(normalized);
                if (saveOptions?.publish === true) saved = repository.setPublished(saved.id, true);
                draftOriginal = normalizeDraft(saved);
                draft = deepClone(draftOriginal);
                emit('draft.saved', { id:saved.id, published:saved.is_published });
                return deepClone(saved);
            },
            batchPublish: published => {
                const ids = [...selectedIds];
                ids.forEach(id => repository.setPublished(id, Boolean(published)));
                emit('batch.published', { ids, published:Boolean(published) });
                return ids.length;
            },
            batchAddTag: tag => {
                const value = String(tag || '').trim();
                if (!value) return 0;
                const ids = [...selectedIds];
                ids.forEach(id => {
                    const problem = repository.getProblem(id);
                    if (problem) repository.update(id, { tags:[...new Set([...(problem.tags || []), value])] });
                });
                emit('batch.tag-added', { ids, tag:value });
                return ids.length;
            },
            batchRemoveTag: tag => {
                const value = String(tag || '').trim();
                const ids = [...selectedIds];
                ids.forEach(id => {
                    const problem = repository.getProblem(id);
                    if (problem) repository.update(id, { tags:(problem.tags || []).filter(item => item !== value) });
                });
                emit('batch.tag-removed', { ids, tag:value });
                return ids.length;
            },
            batchDelete: () => {
                const ids = [...selectedIds];
                ids.forEach(id => repository.remove(id));
                selectedIds.clear();
                emit('batch.deleted', { ids });
                return ids.length;
            },
            duplicateSelected: () => {
                const created = [...selectedIds].map(id => repository.duplicate(id));
                selectedIds.clear();
                created.forEach(problem => selectedIds.add(String(problem.id)));
                emit('batch.duplicated', { ids:created.map(problem => problem.id) });
                return deepClone(created);
            },
            validateAll: validationOptions => {
                lastValidation = repository.validate(validationOptions);
                emit('validation.completed', { valid:lastValidation.valid });
                return deepClone(lastValidation);
            },
            exportJson: exportOptions => JSON.stringify(repository.export(exportOptions), null, Number(exportOptions?.space ?? 2)),
            importJson: (json, importOptions) => {
                let parsed;
                try { parsed = typeof json === 'string' ? JSON.parse(json) : deepClone(json); }
                catch (error) { throw new Error(`JSONの解析に失敗しました：${error.message}`); }
                const result = repository.import(parsed, importOptions);
                selectedIds.clear(); draft = null; draftOriginal = null; resetPage();
                emit('json.imported', { merge:Boolean(importOptions?.merge) });
                return result;
            },
            subscribe: listener => {
                if (typeof listener !== 'function') return () => {};
                listeners.add(listener);
                return () => listeners.delete(listener);
            },
            destroy: () => {
                unsubscribeRepository();
                listeners.clear();
                if (!options.repository) repository.destroy();
            }
        };
        return api;
    }


    /**
     * 問題管理画面と createProblemAdminSession を接続するコントローラー。
     * DOMへの依存を最小限にし、Blade側は data-shogi-admin-* 属性だけで接続できる。
     */
    function createProblemAdminController(root, options = {}) {
        if (!(root instanceof Element)) throw new Error('問題管理画面のルート要素が必要です。');
        const session = options.session || createProblemAdminSession(options);
        const listeners = new Set();
        const disposers = [];
        const history = [];
        const future = [];
        const maxHistory = Math.max(10, Math.min(200, Number(options.maxHistory) || 50));
        let autosaveTimer = null;
        let destroyed = false;
        let suppressHistory = false;
        let lastSnapshot = null;

        const q = selector => root.querySelector(selector);
        const qa = selector => [...root.querySelectorAll(selector)];
        const emit = (type, detail = {}) => {
            const payload = { type, ...deepClone(detail), state: controller.getState() };
            listeners.forEach(listener => listener(payload));
            root.dispatchEvent(new CustomEvent(`lle-shogi-admin:${type}`, { detail:payload }));
        };
        const readFieldValue = element => {
            if (element.type === 'checkbox') return Boolean(element.checked);
            if (element.multiple) return [...element.selectedOptions].map(option => option.value);
            if (element.dataset.valueType === 'number') return element.value === '' ? null : Number(element.value);
            if (element.dataset.valueType === 'json') {
                if (!element.value.trim()) return null;
                try { return JSON.parse(element.value); }
                catch (error) { return element.value; }
            }
            if (element.dataset.valueType === 'list') return element.value.split(',').map(value => value.trim()).filter(Boolean);
            return element.value;
        };
        const writeFieldValue = (element, value) => {
            if (element.type === 'checkbox') element.checked = Boolean(value);
            else if (element.multiple) [...element.options].forEach(option => { option.selected = Array.isArray(value) && value.includes(option.value); });
            else if (element.dataset.valueType === 'json') element.value = value == null ? '' : JSON.stringify(value, null, 2);
            else if (element.dataset.valueType === 'list') element.value = Array.isArray(value) ? value.join(', ') : (value || '');
            else element.value = value == null ? '' : String(value);
        };
        const snapshot = () => {
            const state = session.getState();
            return {
                draft: state.draft ? deepClone(state.draft) : null,
                filters: deepClone(state.filters),
                pagination: { page:state.pagination.page, perPage:state.pagination.perPage },
                selected_ids: [...state.selected_ids]
            };
        };
        const sameSnapshot = (a,b) => JSON.stringify(a) === JSON.stringify(b);
        const pushHistory = () => {
            if (suppressHistory) return;
            const current = snapshot();
            if (lastSnapshot && sameSnapshot(lastSnapshot,current)) return;
            if (lastSnapshot) history.push(lastSnapshot);
            while (history.length > maxHistory) history.shift();
            lastSnapshot = current;
            future.length = 0;
        };
        const restoreSnapshot = snap => {
            suppressHistory = true;
            try {
                session.setFilters(snap.filters || {});
                session.setPerPage(snap.pagination?.perPage || 20);
                session.setPage(snap.pagination?.page || 1);
                session.clearSelection();
                (snap.selected_ids || []).forEach(id => session.select(id,true));
                session.cancelDraft();
                if (snap.draft) {
                    const existing = snap.draft.id ? session.getState().rows.find(row => String(row.id) === String(snap.draft.id)) : null;
                    if (existing) session.beginEdit(snap.draft.id); else session.beginCreate(snap.draft);
                    session.patchDraft(snap.draft);
                }
            } finally {
                suppressHistory = false;
            }
            lastSnapshot = snapshot();
            controller.render();
        };
        const scheduleAutosave = () => {
            clearTimeout(autosaveTimer);
            if (options.autosave === false) return;
            autosaveTimer = setTimeout(() => {
                const state = session.getState();
                if (!state.draft || !state.draft_dirty || !state.draft_validation?.valid) return;
                try {
                    const saved = session.saveDraft({ publish:false });
                    emit('autosaved', { problem:saved });
                } catch (error) {
                    emit('autosave-failed', { message:error.message });
                }
            }, Math.max(300, Number(options.autosaveDelay) || 1200));
        };
        const renderValidation = state => {
            qa('[data-shogi-admin-error-for]').forEach(element => { element.textContent=''; element.hidden=true; });
            const errors = state.draft_validation?.errors || [];
            errors.forEach(error => {
                const field = error.field || error.path || '';
                qa(`[data-shogi-admin-error-for="${CSS.escape(field)}"]`).forEach(element => {
                    element.textContent = error.message || String(error);
                    element.hidden = false;
                });
            });
            qa('[data-shogi-admin-validation-summary]').forEach(element => {
                element.textContent = errors.map(error => error.message || String(error)).join('\n');
                element.hidden = errors.length === 0;
            });
        };
        const renderRows = state => {
            qa('[data-shogi-admin-row]').forEach(element => element.remove());
            const template = q('template[data-shogi-admin-row-template]');
            const container = q('[data-shogi-admin-rows]');
            if (!template || !container) return;
            state.rows.forEach(problem => {
                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('[data-shogi-admin-row]') || fragment.firstElementChild;
                if (!row) return;
                row.dataset.problemId = problem.id;
                row.querySelectorAll('[data-shogi-admin-cell]').forEach(cell => {
                    const key = cell.dataset.shogiAdminCell;
                    const value = key.split('.').reduce((obj,part)=>obj?.[part],problem);
                    cell.textContent = Array.isArray(value) ? value.join(', ') : (value ?? '');
                });
                row.querySelectorAll('[data-shogi-admin-select]').forEach(input => { input.checked = state.selected_ids.includes(String(problem.id)); });
                container.appendChild(fragment);
            });
        };
        const renderDraft = state => {
            qa('[data-shogi-admin-field]').forEach(element => {
                const path = element.dataset.shogiAdminField;
                const value = path.split('.').reduce((obj,part)=>obj?.[part],state.draft || {});
                if (document.activeElement !== element) writeFieldValue(element,value);
                element.disabled = !state.draft;
            });
            qa('[data-shogi-admin-draft-only]').forEach(element => { element.hidden = !state.draft; });
            qa('[data-shogi-admin-save]').forEach(element => { element.disabled = !state.draft || !state.draft_dirty || !state.draft_validation?.valid; });
            renderValidation(state);
        };
        const renderSummary = state => {
            qa('[data-shogi-admin-summary]').forEach(element => {
                const key = element.dataset.shogiAdminSummary;
                element.textContent = state.summary?.[key] ?? '';
            });
            qa('[data-shogi-admin-page]').forEach(element => { element.textContent = state.pagination.page; });
            qa('[data-shogi-admin-total-pages]').forEach(element => { element.textContent = state.pagination.totalPages; });
            qa('[data-shogi-admin-undo]').forEach(element => { element.disabled = history.length === 0; });
            qa('[data-shogi-admin-redo]').forEach(element => { element.disabled = future.length === 0; });
        };
        const controller = {
            session,
            getState: () => ({ ...session.getState(), canUndo:history.length>0, canRedo:future.length>0 }),
            render: () => {
                if (destroyed) return null;
                const state = session.getState();
                renderRows(state); renderDraft(state); renderSummary(state);
                emit('rendered');
                return state;
            },
            undo: () => {
                if (!history.length) return false;
                future.push(snapshot());
                const previous = history.pop();
                restoreSnapshot(previous);
                emit('undone');
                return true;
            },
            redo: () => {
                if (!future.length) return false;
                history.push(snapshot());
                const next = future.pop();
                restoreSnapshot(next);
                emit('redone');
                return true;
            },
            subscribe: listener => { if (typeof listener!=='function') return ()=>{}; listeners.add(listener); return ()=>listeners.delete(listener); },
            destroy: () => {
                destroyed = true;
                clearTimeout(autosaveTimer);
                disposers.splice(0).forEach(dispose => dispose());
                listeners.clear();
                if (!options.session) session.destroy();
            }
        };

        const on = (target,event,handler) => { target.addEventListener(event,handler); disposers.push(()=>target.removeEventListener(event,handler)); };
        on(root,'input',event => {
            const field = event.target.closest('[data-shogi-admin-field]');
            if (!field) return;
            const path = field.dataset.shogiAdminField;
            const current = session.getState().draft || {};
            const patch = deepClone(current);
            const parts = path.split('.'); let cursor = patch;
            parts.slice(0,-1).forEach(part => { cursor[part] = cursor[part] && typeof cursor[part]==='object' ? cursor[part] : {}; cursor = cursor[part]; });
            cursor[parts.at(-1)] = readFieldValue(field);
            session.patchDraft(patch); pushHistory(); scheduleAutosave(); controller.render();
        });
        on(root,'click',event => {
            const action = event.target.closest('[data-shogi-admin-action]')?.dataset.shogiAdminAction;
            if (!action) return;
            event.preventDefault();
            try {
                if (action==='create') session.beginCreate();
                else if (action==='edit') session.beginEdit(event.target.closest('[data-problem-id]')?.dataset.problemId);
                else if (action==='save') session.saveDraft({publish:false});
                else if (action==='save-publish') session.saveDraft({publish:true});
                else if (action==='cancel') session.cancelDraft();
                else if (action==='delete-selected') session.batchDelete();
                else if (action==='publish-selected') session.batchPublish(true);
                else if (action==='unpublish-selected') session.batchPublish(false);
                else if (action==='duplicate-selected') session.duplicateSelected();
                else if (action==='select-visible') session.selectVisible(true);
                else if (action==='clear-selection') session.clearSelection();
                else if (action==='prev-page') session.setPage(session.getState().pagination.page-1);
                else if (action==='next-page') session.setPage(session.getState().pagination.page+1);
                else if (action==='undo') return controller.undo();
                else if (action==='redo') return controller.redo();
                pushHistory(); controller.render(); emit('action', { action });
            } catch (error) { emit('error', { action, message:error.message }); }
        });
        on(root,'change',event => {
            const select = event.target.closest('[data-shogi-admin-select]');
            if (select) {
                const id = select.closest('[data-problem-id]')?.dataset.problemId;
                if (id != null) session.select(id,select.checked);
                controller.render();
            }
            const filter = event.target.closest('[data-shogi-admin-filter]');
            if (filter) session.setFilters({ [filter.dataset.shogiAdminFilter]:readFieldValue(filter) }), controller.render();
        });
        on(document,'keydown',event => {
            if (!(event.ctrlKey || event.metaKey)) return;
            const key = event.key.toLowerCase();
            if (key==='s') { event.preventDefault(); try { session.saveDraft({publish:false}); pushHistory(); controller.render(); emit('saved-by-shortcut'); } catch(error){ emit('error',{action:'save',message:error.message}); } }
            if (key==='z' && !event.shiftKey) { event.preventDefault(); controller.undo(); }
            if ((key==='y') || (key==='z' && event.shiftKey)) { event.preventDefault(); controller.redo(); }
        });
        const beforeUnload = event => {
            if (!session.getState().draft_dirty) return;
            event.preventDefault(); event.returnValue='';
        };
        on(window,'beforeunload',beforeUnload);
        const unsubscribe = session.subscribe(() => { if (!suppressHistory) controller.render(); });
        disposers.push(unsubscribe);
        lastSnapshot = snapshot();
        controller.render();
        return controller;
    }


    /**
     * Laravel等の問題管理APIと問題リポジトリを接続する同期アダプター。
     * APIが未実装の環境でも、送信待ちキューを保持して画面操作を継続できる。
     */
    function createProblemApiSync(options={}) {
        const repository = options.repository || createProblemRepository(options.initialSet || {});
        const baseUrl = String(options.baseUrl || '/api/admin/shogi/problems').replace(/\/$/, '');
        const setUrl = String(options.setUrl || '/api/admin/shogi/problem-set').replace(/\/$/, '');
        const storageKey = options.storageKey || 'lle_shogi_problem_api_queue_v1';
        const maxRetries = Math.max(1, Number(options.maxRetries || 5));
        const listeners = new Set();
        let syncing = false;
        let lastSyncedAt = null;
        let lastError = null;

        const emit = (type, detail={}) => {
            const payload = { type, detail:deepClone(detail), timestamp:new Date().toISOString() };
            listeners.forEach(listener => { try { listener(payload); } catch (_) {} });
            if (typeof window !== 'undefined' && typeof window.CustomEvent === 'function') {
                window.dispatchEvent(new CustomEvent(`lle-shogi-problem-api:${type}`, { detail:payload.detail }));
            }
        };
        const csrfToken = () => options.csrfToken
            || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || '';
        const readQueue = () => {
            try {
                const value = JSON.parse(localStorage.getItem(storageKey) || '[]');
                return Array.isArray(value) ? value : [];
            } catch (_) { return []; }
        };
        const writeQueue = queue => {
            try { localStorage.setItem(storageKey, JSON.stringify(queue)); } catch (_) {}
            emit('queue-changed', { pending:queue.length });
        };
        const enqueue = operation => {
            const queue = readQueue();
            queue.push({
                id:`op_${Date.now()}_${Math.random().toString(36).slice(2,9)}`,
                attempts:0,
                created_at:new Date().toISOString(),
                ...deepClone(operation)
            });
            writeQueue(queue);
        };
        const request = async (url, init={}) => {
            const headers = {
                'Accept':'application/json',
                'Content-Type':'application/json',
                'X-Requested-With':'XMLHttpRequest',
                ...(csrfToken() ? {'X-CSRF-TOKEN':csrfToken()} : {}),
                ...(init.headers || {})
            };
            const response = await fetch(url, { credentials:'same-origin', ...init, headers });
            let body = null;
            const text = await response.text();
            if (text) {
                try { body = JSON.parse(text); } catch (_) { body = { message:text }; }
            }
            if (!response.ok) {
                const error = new Error(body?.message || `HTTP ${response.status}`);
                error.status = response.status;
                error.payload = body;
                throw error;
            }
            return body;
        };
        const perform = async operation => {
            const { type, payload } = operation;
            if (type === 'create') return request(baseUrl, { method:'POST', body:JSON.stringify(payload) });
            if (type === 'update') return request(`${baseUrl}/${encodeURIComponent(payload.id)}`, { method:'PUT', body:JSON.stringify(payload) });
            if (type === 'delete') return request(`${baseUrl}/${encodeURIComponent(payload.id)}`, { method:'DELETE' });
            if (type === 'publish') return request(`${baseUrl}/${encodeURIComponent(payload.id)}/publish`, { method:'PATCH', body:JSON.stringify({published:!!payload.published,revision:payload.revision}) });
            if (type === 'reorder') return request(`${baseUrl}/reorder`, { method:'POST', body:JSON.stringify(payload) });
            if (type === 'bulk') return request(`${baseUrl}/bulk`, { method:'POST', body:JSON.stringify(payload) });
            if (type === 'set-update') return request(setUrl, { method:'PUT', body:JSON.stringify(payload) });
            throw new Error(`未対応の同期操作です: ${type}`);
        };
        const executeOrQueue = async operation => {
            try {
                const result = await perform(operation);
                lastSyncedAt = new Date().toISOString();
                lastError = null;
                emit('synced', { operation:operation.type, result });
                return { queued:false, result };
            } catch (error) {
                lastError = error.message;
                if (error.status === 409 || error.status === 412) {
                    emit('conflict', { operation, message:error.message, server:error.payload });
                    throw error;
                }
                enqueue(operation);
                emit('queued', { operation:operation.type, message:error.message });
                return { queued:true, error:error.message };
            }
        };

        const api = {
            repository,
            subscribe(listener) { if (typeof listener!=='function') return ()=>{}; listeners.add(listener); return ()=>listeners.delete(listener); },
            getState() {
                return {
                    syncing,
                    online:typeof navigator === 'undefined' ? true : navigator.onLine,
                    pending:readQueue().length,
                    last_synced_at:lastSyncedAt,
                    last_error:lastError
                };
            },
            async loadRemote(params={}) {
                const query = new URLSearchParams();
                Object.entries(params).forEach(([key,value]) => {
                    if (value !== undefined && value !== null && value !== '') query.set(key,String(value));
                });
                const result = await request(`${setUrl}${query.toString()?`?${query}`:''}`, { method:'GET' });
                const rawSet = result?.data || result?.problem_set || result;
                repository.import(rawSet, { replace:true });
                lastSyncedAt = new Date().toISOString();
                lastError = null;
                emit('loaded', { count:repository.list().length });
                return repository.export();
            },
            async create(problem) {
                const created = repository.create(problem);
                const result = await executeOrQueue({ type:'create', payload:created });
                return { problem:deepClone(created), ...result };
            },
            async update(id, patch) {
                const current = repository.find(id);
                if (!current) throw new Error(`問題が見つかりません: ${id}`);
                const updated = repository.update(id, patch);
                const payload = { ...updated, revision:current.revision ?? current.version ?? null };
                const result = await executeOrQueue({ type:'update', payload });
                return { problem:deepClone(updated), ...result };
            },
            async remove(id) {
                const current = repository.find(id);
                if (!current) return false;
                repository.remove(id);
                await executeOrQueue({ type:'delete', payload:{id,revision:current.revision ?? current.version ?? null} });
                return true;
            },
            async setPublished(id, published) {
                const current = repository.find(id);
                if (!current) throw new Error(`問題が見つかりません: ${id}`);
                const updated = repository.update(id, { published:!!published });
                const result = await executeOrQueue({ type:'publish', payload:{id, published:!!published, revision:current.revision ?? current.version ?? null} });
                return { problem:deepClone(updated), ...result };
            },
            async reorder(ids) {
                repository.reorder(ids);
                return executeOrQueue({ type:'reorder', payload:{ids:[...ids]} });
            },
            async bulk(action, ids, values={}) {
                const targetIds = [...new Set((ids||[]).map(String))];
                if (!targetIds.length) return { queued:false, result:null };
                if (action === 'publish' || action === 'unpublish') targetIds.forEach(id => repository.update(id,{published:action==='publish'}));
                if (action === 'delete') targetIds.forEach(id => repository.remove(id));
                return executeOrQueue({ type:'bulk', payload:{action,ids:targetIds,values:deepClone(values)} });
            },
            async updateProblemSet(patch) {
                repository.updateSet(patch);
                return executeOrQueue({ type:'set-update', payload:repository.export() });
            },
            async flushQueue() {
                if (syncing) return api.getState();
                syncing = true;
                emit('flush-started', { pending:readQueue().length });
                const queue = readQueue();
                const remaining = [];
                for (const operation of queue) {
                    try {
                        await perform(operation);
                        lastSyncedAt = new Date().toISOString();
                        emit('queue-item-synced', { id:operation.id, operation:operation.type });
                    } catch (error) {
                        const next = { ...operation, attempts:(operation.attempts||0)+1, last_error:error.message };
                        if ((error.status === 409 || error.status === 412) || next.attempts >= maxRetries) {
                            emit(error.status===409||error.status===412 ? 'conflict' : 'queue-item-failed', { operation:next, message:error.message, server:error.payload });
                        } else remaining.push(next);
                    }
                }
                writeQueue(remaining);
                syncing = false;
                lastError = remaining.length ? remaining.at(-1).last_error : null;
                emit('flush-completed', { pending:remaining.length });
                return api.getState();
            },
            clearQueue() { writeQueue([]); },
            exportQueue() { return deepClone(readQueue()); }
        };

        const onlineHandler = () => api.flushQueue();
        if (typeof window !== 'undefined') window.addEventListener('online', onlineHandler);
        api.destroy = () => { if (typeof window !== 'undefined') window.removeEventListener('online', onlineHandler); listeners.clear(); };
        return api;
    }


    /**
     * 問題管理画面で編集中の問題を、その場で実際に解いて確認するためのプレビュー機能。
     * 本番の学習履歴・進捗・実績には一切書き込まず、管理者向けの検証結果だけを返す。
     */
    function createProblemPreviewSession(options={}) {
        const listeners = new Set();
        const repository = options.repository || null;
        const root = typeof options.root === 'string'
            ? document.querySelector(options.root)
            : options.root || null;
        let player = null;
        let currentProblem = null;
        let currentSource = null;
        let startedAt = null;
        let completedAt = null;
        let lastResult = null;
        let validation = { valid:false, errors:[], warnings:[] };

        const emit = (type, detail={}) => {
            const payload = { type, detail:deepClone(detail), timestamp:new Date().toISOString() };
            listeners.forEach(listener => { try { listener(payload); } catch (_) {} });
            if (typeof window !== 'undefined' && typeof window.CustomEvent === 'function') {
                window.dispatchEvent(new CustomEvent(`lle-shogi-problem-preview:${type}`, { detail:payload.detail }));
            }
        };

        const resolveProblem = source => {
            if (source && typeof source === 'object') return deepClone(source);
            if (repository && source !== undefined && source !== null) {
                const found = repository.find(String(source));
                if (found) return deepClone(found);
            }
            throw new Error('プレビュー対象の問題が見つかりません。');
        };

        const toPreviewSet = problem => ({
            schema:PROBLEM_SET_SCHEMA,
            schema_version:PROBLEM_SET_SCHEMA_VERSION,
            id:'admin-preview',
            title:'管理者プレビュー',
            version:1,
            problems:[{ ...deepClone(problem), published:true }]
        });

        const validate = problem => {
            const report = validateProblemSet(toPreviewSet(problem), {
                requireHint:false,
                requireExplanation:false,
                requireAbilities:false,
                includeUnpublished:true
            });
            const normalized = normalizeProblemSet(toPreviewSet(problem), { includeUnpublished:true });
            const normalizedProblem = normalized.problems?.[0] || null;
            const errors = Array.isArray(report.errors) ? report.errors : [];
            const warnings = Array.isArray(report.warnings) ? report.warnings : [];
            return {
                valid:errors.length === 0 && !!normalizedProblem,
                errors:deepClone(errors),
                warnings:deepClone(warnings),
                problem:normalizedProblem ? deepClone(normalizedProblem) : null
            };
        };

        const destroyPlayer = () => {
            if (player && typeof player.destroy === 'function') {
                try { player.destroy(); } catch (_) {}
            }
            player = null;
            if (root) root.innerHTML = '';
        };

        const attachPlayerEvents = () => {
            if (!root || typeof window === 'undefined') return;
            const correctHandler = event => {
                completedAt = new Date().toISOString();
                lastResult = deepClone(event?.detail || player?.getResult?.() || {});
                emit('completed', api.getState());
            };
            const incorrectHandler = event => emit('incorrect', deepClone(event?.detail || {}));
            root.addEventListener('lle-shogi:completed', correctHandler, { once:true });
            root.addEventListener('lle-shogi:incorrect', incorrectHandler);
        };

        const api = {
            subscribe(listener) {
                if (typeof listener !== 'function') return ()=>{};
                listeners.add(listener);
                return ()=>listeners.delete(listener);
            },
            getState() {
                return {
                    mounted:!!player,
                    source:currentSource,
                    problem:currentProblem ? deepClone(currentProblem) : null,
                    validation:deepClone(validation),
                    started_at:startedAt,
                    completed_at:completedAt,
                    elapsed_ms:startedAt ? Math.max(0, Date.now()-new Date(startedAt).getTime()) : 0,
                    result:lastResult ? deepClone(lastResult) : null
                };
            },
            validate(source) {
                const raw = resolveProblem(source);
                const report = validate(raw);
                validation = { valid:report.valid, errors:report.errors, warnings:report.warnings };
                emit('validated', validation);
                return deepClone({ ...validation, problem:report.problem });
            },
            mount(source, mountOptions={}) {
                if (!root) throw new Error('プレビュー表示先の要素が指定されていません。');
                const raw = resolveProblem(source);
                const report = validate(raw);
                validation = { valid:report.valid, errors:report.errors, warnings:report.warnings };
                if (!report.valid || !report.problem) {
                    emit('validation-failed', validation);
                    const error = new Error('問題データにエラーがあるためプレビューできません。');
                    error.validation = deepClone(validation);
                    throw error;
                }
                destroyPlayer();
                currentSource = typeof source === 'object' ? 'draft' : String(source);
                currentProblem = deepClone(report.problem);
                startedAt = new Date().toISOString();
                completedAt = null;
                lastResult = null;
                player = mountPlayer(root, {
                    ...deepClone(currentProblem),
                    ...deepClone(mountOptions),
                    previewMode:true,
                    disableProgressStorage:true,
                    disableLearningLogSync:true,
                    published:true
                });
                attachPlayerEvents();
                emit('mounted', api.getState());
                return player;
            },
            reload(source=currentProblem, mountOptions={}) {
                return api.mount(source, mountOptions);
            },
            reset() {
                if (player && typeof player.reset === 'function') player.reset();
                else if (currentProblem) api.mount(currentProblem);
                startedAt = new Date().toISOString();
                completedAt = null;
                lastResult = null;
                emit('reset', api.getState());
                return api.getState();
            },
            getPlayer() { return player; },
            getResult() {
                const result = player && typeof player.getResult === 'function' ? player.getResult() : lastResult;
                return result ? deepClone(result) : null;
            },
            exportReport() {
                const result = api.getResult();
                return {
                    previewed_at:new Date().toISOString(),
                    problem_id:currentProblem?.id ?? null,
                    title:currentProblem?.title ?? '',
                    validation:deepClone(validation),
                    started_at:startedAt,
                    completed_at:completedAt,
                    elapsed_ms:startedAt ? Math.max(0,(completedAt ? new Date(completedAt).getTime() : Date.now())-new Date(startedAt).getTime()) : 0,
                    result:result ? deepClone(result) : null,
                    passed:!!(result && (result.correct || result.completed || result.cleared))
                };
            },
            destroy() {
                destroyPlayer();
                listeners.clear();
                currentProblem = null;
                currentSource = null;
                startedAt = null;
                completedAt = null;
                lastResult = null;
            }
        };
        return api;
    }


    /**
     * 問題のレビュー・公開・版管理を扱うワークフロー。
     * 管理画面の下書きを直接公開せず、検証・レビュー・公開履歴を一元管理する。
     */
    function createProblemPublicationWorkflow(repository, options={}) {
        if (!repository || typeof repository.getAll !== 'function') {
            throw new Error('問題リポジトリが指定されていません。');
        }
        const storageKey = options.storageKey || 'lle-shogi-publication-workflow-v1';
        const maxRevisions = Math.max(1, Number(options.maxRevisions || 20));
        const listeners = new Map();
        let records = {};

        const emit = (name, detail={}) => {
            const payload = deepClone(detail);
            (listeners.get(name) || []).forEach(listener => {
                try { listener(payload); } catch (error) { console.error(error); }
            });
            if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
                window.dispatchEvent(new CustomEvent(`lle-shogi-publication:${name}`, { detail:payload }));
            }
        };
        const persist = () => {
            if (options.disableStorage || typeof localStorage === 'undefined') return;
            localStorage.setItem(storageKey, JSON.stringify({ version:1, records }));
        };
        const restore = () => {
            if (options.disableStorage || typeof localStorage === 'undefined') return;
            try {
                const raw = JSON.parse(localStorage.getItem(storageKey) || '{}');
                if (raw && raw.records && typeof raw.records === 'object') records = raw.records;
            } catch (_) { records = {}; }
        };
        const findProblem = id => {
            const all = repository.getAll();
            return all.find(problem => String(problem.id) === String(id)) || null;
        };
        const getRecord = id => {
            const key = String(id);
            if (!records[key]) {
                records[key] = {
                    problem_id:key,
                    status:'draft',
                    review_comment:'',
                    requested_at:null,
                    reviewed_at:null,
                    published_at:null,
                    unpublished_at:null,
                    reviewer:null,
                    publisher:null,
                    revision:0,
                    revisions:[]
                };
            }
            return records[key];
        };
        const snapshot = (problem, action, actor=null, note='') => ({
            revision:Number(getRecord(problem.id).revision || 0) + 1,
            action,
            actor:actor || null,
            note:String(note || ''),
            created_at:new Date().toISOString(),
            problem:deepClone(problem)
        });
        const addRevision = (problem, action, actor, note) => {
            const record = getRecord(problem.id);
            const revision = snapshot(problem, action, actor, note);
            record.revision = revision.revision;
            record.revisions.unshift(revision);
            record.revisions = record.revisions.slice(0, maxRevisions);
            return revision;
        };
        const validateForPublication = problem => {
            const set = { schema:PROBLEM_SET_SCHEMA, schema_version:PROBLEM_SET_SCHEMA_VERSION, problems:[problem] };
            const report = validateProblemSet(set, { requirePublished:false });
            const errors = (report.errors || []).filter(error => String(error.problem_id ?? problem.id) === String(problem.id) || error.problem_id == null);
            const warnings = (report.warnings || []).filter(warning => String(warning.problem_id ?? problem.id) === String(problem.id) || warning.problem_id == null);
            return { valid:errors.length === 0, errors, warnings, problem:report.problemSet?.problems?.[0] || deepClone(problem) };
        };
        const setPublished = (id, published) => {
            if (typeof repository.update !== 'function') throw new Error('リポジトリが更新に対応していません。');
            return repository.update(id, { published:!!published, is_published:!!published });
        };

        restore();

        const api = {
            on(name, listener) {
                if (!listeners.has(name)) listeners.set(name, []);
                listeners.get(name).push(listener);
                return () => api.off(name, listener);
            },
            off(name, listener) {
                if (!listeners.has(name)) return;
                listeners.set(name, listeners.get(name).filter(item => item !== listener));
            },
            getStatus(id) { return deepClone(getRecord(id)); },
            getAllStatuses() { return deepClone(Object.values(records)); },
            validate(id) {
                const problem = findProblem(id);
                if (!problem) throw new Error('対象の問題が見つかりません。');
                return deepClone(validateForPublication(problem));
            },
            requestReview(id, requester=null, comment='') {
                const problem = findProblem(id);
                if (!problem) throw new Error('対象の問題が見つかりません。');
                const validation = validateForPublication(problem);
                if (!validation.valid) {
                    const error = new Error('公開前チェックに失敗しました。');
                    error.validation = deepClone(validation);
                    throw error;
                }
                const record = getRecord(id);
                record.status = 'review_requested';
                record.review_comment = String(comment || '');
                record.requested_at = new Date().toISOString();
                record.reviewed_at = null;
                record.reviewer = null;
                addRevision(problem, 'review_requested', requester, comment);
                persist();
                emit('review-requested', { problem_id:id, record, validation });
                return deepClone(record);
            },
            approve(id, reviewer=null, comment='') {
                const problem = findProblem(id);
                if (!problem) throw new Error('対象の問題が見つかりません。');
                const validation = validateForPublication(problem);
                if (!validation.valid) throw new Error('問題データにエラーがあるため承認できません。');
                const record = getRecord(id);
                record.status = 'approved';
                record.review_comment = String(comment || record.review_comment || '');
                record.reviewed_at = new Date().toISOString();
                record.reviewer = reviewer || null;
                addRevision(problem, 'approved', reviewer, comment);
                persist();
                emit('approved', { problem_id:id, record, validation });
                return deepClone(record);
            },
            reject(id, reviewer=null, comment='') {
                const problem = findProblem(id);
                if (!problem) throw new Error('対象の問題が見つかりません。');
                const record = getRecord(id);
                record.status = 'changes_requested';
                record.review_comment = String(comment || '修正が必要です。');
                record.reviewed_at = new Date().toISOString();
                record.reviewer = reviewer || null;
                addRevision(problem, 'changes_requested', reviewer, comment);
                persist();
                emit('changes-requested', { problem_id:id, record });
                return deepClone(record);
            },
            publish(id, publisher=null, note='') {
                const problem = findProblem(id);
                if (!problem) throw new Error('対象の問題が見つかりません。');
                const validation = validateForPublication(problem);
                if (!validation.valid) {
                    const error = new Error('公開前チェックに失敗しました。');
                    error.validation = deepClone(validation);
                    throw error;
                }
                const record = getRecord(id);
                if (options.requireApproval !== false && record.status !== 'approved' && record.status !== 'published') {
                    throw new Error('レビュー承認後に公開してください。');
                }
                const updated = setPublished(id, true);
                record.status = 'published';
                record.published_at = new Date().toISOString();
                record.unpublished_at = null;
                record.publisher = publisher || null;
                const revision = addRevision(updated || problem, 'published', publisher, note);
                persist();
                emit('published', { problem_id:id, record, revision, problem:updated || problem });
                return deepClone({ record, problem:updated || problem });
            },
            unpublish(id, actor=null, note='') {
                const problem = findProblem(id);
                if (!problem) throw new Error('対象の問題が見つかりません。');
                const updated = setPublished(id, false);
                const record = getRecord(id);
                record.status = 'draft';
                record.unpublished_at = new Date().toISOString();
                addRevision(updated || problem, 'unpublished', actor, note);
                persist();
                emit('unpublished', { problem_id:id, record, problem:updated || problem });
                return deepClone({ record, problem:updated || problem });
            },
            getRevisions(id) { return deepClone(getRecord(id).revisions || []); },
            rollback(id, revisionNumber, actor=null) {
                const record = getRecord(id);
                const revision = (record.revisions || []).find(item => Number(item.revision) === Number(revisionNumber));
                if (!revision) throw new Error('指定された版が見つかりません。');
                if (typeof repository.update !== 'function') throw new Error('リポジトリが更新に対応していません。');
                const restored = repository.update(id, deepClone(revision.problem));
                record.status = restored?.published ? 'published' : 'draft';
                addRevision(restored || revision.problem, 'rollback', actor, `revision ${revisionNumber} へ復元`);
                persist();
                emit('rolled-back', { problem_id:id, source_revision:revisionNumber, record, problem:restored || revision.problem });
                return deepClone(restored || revision.problem);
            },
            clear(id=null) {
                if (id == null) records = {};
                else delete records[String(id)];
                persist();
                emit('cleared', { problem_id:id });
            }
        };
        return api;
    }


    /**
     * LifeForce連携ブリッジ
     * 将棋問題の学習結果を、ポイント・バッジ・生徒カルテ・ルーティンへ渡す
     * 共通データへ変換する。実際のDB保存はコールバックまたは既存同期APIに委譲する。
     */
    function createStudentLearningBridge(options={}) {
        const storageKey = String(options.storageKey || 'lle_shogi_student_learning_v1');
        const now = () => new Date().toISOString();
        const listeners = new Map();
        const maxAttempts = Math.max(20, Number(options.maxAttempts || 500));
        let state = {
            attempts: [],
            awarded_attempt_ids: [],
            badge_keys: [],
            routine_completions: []
        };

        const emit = (name, detail={}) => {
            const payload = deepClone(detail);
            (listeners.get(name) || []).forEach(fn => {
                try { fn(payload); } catch (error) { console.error(error); }
            });
            if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
                window.dispatchEvent(new CustomEvent(`lle-shogi:${name}`, { detail: payload }));
            }
        };
        const persist = () => {
            try { localStorage.setItem(storageKey, JSON.stringify(state)); } catch (_) {}
        };
        const restore = () => {
            try {
                const raw = JSON.parse(localStorage.getItem(storageKey) || 'null');
                if (raw && typeof raw === 'object') {
                    state.attempts = Array.isArray(raw.attempts) ? raw.attempts : [];
                    state.awarded_attempt_ids = Array.isArray(raw.awarded_attempt_ids) ? raw.awarded_attempt_ids : [];
                    state.badge_keys = Array.isArray(raw.badge_keys) ? raw.badge_keys : [];
                    state.routine_completions = Array.isArray(raw.routine_completions) ? raw.routine_completions : [];
                }
            } catch (_) {}
        };
        restore();

        const makeAttemptId = result => String(
            result.attempt_id ||
            `${result.student_id || 'guest'}:${result.problem_id || result.id || 'unknown'}:${result.started_at || Date.now()}`
        );
        const normalizeAbilities = abilities => {
            const source = abilities && typeof abilities === 'object' ? abilities : {};
            return ABILITY_KEYS.reduce((acc, key) => {
                const value = Number(source[key] || 0);
                acc[key] = Math.max(0, Math.min(100, Number.isFinite(value) ? value : 0));
                return acc;
            }, {});
        };
        const calculatePoints = result => {
            const base = Math.max(0, Number(result.reward_points ?? result.points ?? 0));
            if (!result.correct) return 0;
            const score = Math.max(0, Math.min(100, Number(result.score ?? 100)));
            const scoreBonus = Math.floor(base * (score / 100));
            const perfectBonus = result.perfect ? Math.max(1, Math.floor(base * 0.25)) : 0;
            return Math.max(base, scoreBonus) + perfectBonus;
        };
        const buildBadgeCandidates = (result, history) => {
            const candidates = [];
            const completed = history.filter(item => item.correct).length;
            const perfects = history.filter(item => item.perfect).length;
            if (completed >= 1) candidates.push('shogi_first_clear');
            if (completed >= 10) candidates.push('shogi_clear_10');
            if (completed >= 50) candidates.push('shogi_clear_50');
            if (perfects >= 1) candidates.push('shogi_first_perfect');
            if (perfects >= 10) candidates.push('shogi_perfect_10');
            if (Number(result.score || 0) >= 90) candidates.push('shogi_score_90');
            return candidates;
        };
        const buildAttempt = result => {
            const attemptId = makeAttemptId(result);
            return {
                attempt_id: attemptId,
                student_id: result.student_id ?? options.studentId ?? null,
                school_id: result.school_id ?? options.schoolId ?? null,
                routine_id: result.routine_id ?? options.routineId ?? null,
                routine_item_id: result.routine_item_id ?? options.routineItemId ?? null,
                component_type: 'shogi',
                content_type: result.content_type || result.category || 'tsume_shogi',
                problem_set_id: result.problem_set_id ?? null,
                problem_id: result.problem_id ?? result.id ?? null,
                difficulty: result.difficulty || null,
                correct: Boolean(result.correct),
                score: Math.max(0, Math.min(100, Number(result.score ?? 0))),
                rank: result.rank || null,
                stars: Math.max(0, Number(result.stars || 0)),
                perfect: Boolean(result.perfect),
                elapsed_ms: Math.max(0, Number(result.elapsed_ms ?? result.elapsedTime ?? 0)),
                hint_count: Math.max(0, Number(result.hint_count ?? result.hintCount ?? 0)),
                mistake_count: Math.max(0, Number(result.mistake_count ?? result.mistakeCount ?? 0)),
                undo_count: Math.max(0, Number(result.undo_count ?? result.undoCount ?? 0)),
                retry_count: Math.max(0, Number(result.retry_count ?? result.retryCount ?? 0)),
                moves: deepClone(result.moves || []),
                start_sfen: result.start_sfen || result.sfen || null,
                end_sfen: result.end_sfen || null,
                abilities: normalizeAbilities(result.abilities || result.ability_scores),
                started_at: result.started_at || null,
                completed_at: result.completed_at || now(),
                recorded_at: now()
            };
        };
        const buildStudentChartSummary = studentId => {
            const attempts = state.attempts.filter(item => String(item.student_id) === String(studentId));
            const completed = attempts.filter(item => item.correct);
            const abilityTotals = ABILITY_KEYS.reduce((acc, key) => ({ ...acc, [key]: 0 }), {});
            completed.forEach(item => ABILITY_KEYS.forEach(key => { abilityTotals[key] += Number(item.abilities?.[key] || 0); }));
            const abilities = ABILITY_KEYS.reduce((acc, key) => {
                acc[key] = completed.length ? Math.round(abilityTotals[key] / completed.length) : 0;
                return acc;
            }, {});
            const average = key => completed.length
                ? Math.round(completed.reduce((sum, item) => sum + Number(item[key] || 0), 0) / completed.length)
                : 0;
            return {
                student_id: studentId,
                attempt_count: attempts.length,
                clear_count: completed.length,
                correct_rate: attempts.length ? Math.round((completed.length / attempts.length) * 100) : 0,
                average_score: average('score'),
                average_elapsed_ms: average('elapsed_ms'),
                perfect_count: completed.filter(item => item.perfect).length,
                total_hint_count: attempts.reduce((sum, item) => sum + item.hint_count, 0),
                total_mistake_count: attempts.reduce((sum, item) => sum + item.mistake_count, 0),
                abilities,
                latest_completed_at: completed.at(-1)?.completed_at || null
            };
        };

        const api = {
            on(name, handler) {
                if (typeof handler !== 'function') return () => {};
                if (!listeners.has(name)) listeners.set(name, []);
                listeners.get(name).push(handler);
                return () => listeners.set(name, (listeners.get(name) || []).filter(fn => fn !== handler));
            },
            record(result={}) {
                const attempt = buildAttempt(result);
                const existingIndex = state.attempts.findIndex(item => item.attempt_id === attempt.attempt_id);
                if (existingIndex >= 0) state.attempts[existingIndex] = attempt;
                else state.attempts.push(attempt);
                state.attempts = state.attempts.slice(-maxAttempts);

                let awardedPoints = 0;
                if (!state.awarded_attempt_ids.includes(attempt.attempt_id)) {
                    awardedPoints = calculatePoints({ ...result, ...attempt });
                    state.awarded_attempt_ids.push(attempt.attempt_id);
                    emit('points-awarded', {
                        attempt_id: attempt.attempt_id,
                        student_id: attempt.student_id,
                        points: awardedPoints,
                        reason: 'shogi_problem_complete'
                    });
                }

                const studentHistory = state.attempts.filter(item => String(item.student_id) === String(attempt.student_id));
                const newBadges = buildBadgeCandidates(attempt, studentHistory).filter(key => !state.badge_keys.includes(`${attempt.student_id}:${key}`));
                newBadges.forEach(key => {
                    state.badge_keys.push(`${attempt.student_id}:${key}`);
                    emit('badge-candidate', { student_id: attempt.student_id, badge_key: key, attempt_id: attempt.attempt_id });
                });

                let routineCompletion = null;
                if (attempt.correct && attempt.routine_item_id != null) {
                    const completionKey = `${attempt.student_id}:${attempt.routine_item_id}:${String(attempt.completed_at).slice(0,10)}`;
                    if (!state.routine_completions.includes(completionKey)) {
                        state.routine_completions.push(completionKey);
                        routineCompletion = {
                            student_id: attempt.student_id,
                            routine_id: attempt.routine_id,
                            routine_item_id: attempt.routine_item_id,
                            completed_on: String(attempt.completed_at).slice(0,10),
                            source_type: 'shogi',
                            source_id: attempt.problem_id,
                            attempt_id: attempt.attempt_id
                        };
                        emit('routine-completed', routineCompletion);
                    }
                }

                const studentChart = buildStudentChartSummary(attempt.student_id);
                persist();
                const payload = { attempt, awarded_points: awardedPoints, new_badges: newBadges, routine_completion: routineCompletion, student_chart: studentChart };
                emit('learning-recorded', payload);
                if (typeof options.onRecord === 'function') options.onRecord(deepClone(payload));
                return deepClone(payload);
            },
            getAttempts(studentId=null) {
                const rows = studentId == null ? state.attempts : state.attempts.filter(item => String(item.student_id) === String(studentId));
                return deepClone(rows);
            },
            getStudentChartSummary(studentId) { return deepClone(buildStudentChartSummary(studentId)); },
            buildServerPayload(result={}) {
                const attempt = buildAttempt(result);
                return {
                    question_log: {
                        student_id: attempt.student_id,
                        component_type: attempt.component_type,
                        question_id: attempt.problem_id,
                        correct: attempt.correct,
                        elapsed_time_ms: attempt.elapsed_ms,
                        hint_count: attempt.hint_count,
                        retry_count: attempt.retry_count,
                        score: attempt.score,
                        started_at: attempt.started_at,
                        completed_at: attempt.completed_at
                    },
                    shogi_log: {
                        attempt_id: attempt.attempt_id,
                        sfen: attempt.start_sfen,
                        end_sfen: attempt.end_sfen,
                        moves: attempt.moves,
                        undo_count: attempt.undo_count,
                        mistake_count: attempt.mistake_count,
                        abilities: attempt.abilities
                    },
                    routine: attempt.routine_item_id == null ? null : {
                        routine_id: attempt.routine_id,
                        routine_item_id: attempt.routine_item_id
                    }
                };
            },
            clear(studentId=null) {
                if (studentId == null) {
                    state = { attempts: [], awarded_attempt_ids: [], badge_keys: [], routine_completions: [] };
                } else {
                    const ids = new Set(state.attempts.filter(item => String(item.student_id) === String(studentId)).map(item => item.attempt_id));
                    state.attempts = state.attempts.filter(item => String(item.student_id) !== String(studentId));
                    state.awarded_attempt_ids = state.awarded_attempt_ids.filter(id => !ids.has(id));
                    state.badge_keys = state.badge_keys.filter(key => !key.startsWith(`${studentId}:`));
                    state.routine_completions = state.routine_completions.filter(key => !key.startsWith(`${studentId}:`));
                }
                persist();
                emit('learning-cleared', { student_id: studentId });
            }
        };
        return api;
    }


    /**
     * LifeForce教材連携コーディネーター。
     * 学習結果を生徒カルテ・ポイント・バッジ・ルーティンの各APIへ安全に同期する。
     * 通信失敗時はブラウザへ保存し、オンライン復帰後に再送する。
     */
    function createLifeForceLearningSync(options={}) {
        const storageKey = options.storageKey || 'lle-shogi-lifeforce-sync-v1';
        const maxAttempts = Math.max(1, Number(options.maxAttempts || 5));
        const retryBaseMs = Math.max(250, Number(options.retryBaseMs || 1500));
        const endpoints = Object.assign({
            learning: '/api/shogi/learning-results',
            points: '/api/shogi/points',
            badges: '/api/shogi/badges',
            routine: '/api/shogi/routine-completions',
            studentChart: '/api/shogi/student-chart'
        }, options.endpoints || {});
        const fetchImpl = options.fetch || (typeof window !== 'undefined' ? window.fetch.bind(window) : null);
        const listeners = new Map();
        let processing = false;
        let queue = [];

        const emit = (name, detail={}) => {
            const payload = deepClone(detail);
            (listeners.get(name) || []).forEach(fn => { try { fn(payload); } catch (_) {} });
            if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
                window.dispatchEvent(new CustomEvent(`lle-shogi:${name}`, { detail: payload }));
            }
        };
        const persist = () => {
            try { localStorage.setItem(storageKey, JSON.stringify(queue)); } catch (_) {}
        };
        const restore = () => {
            try {
                const raw = JSON.parse(localStorage.getItem(storageKey) || '[]');
                queue = Array.isArray(raw) ? raw.filter(item => item && item.id && item.type) : [];
            } catch (_) { queue = []; }
        };
        const csrfToken = () => options.csrfToken
            || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value
            || '';
        const uid = () => `shogi-sync-${Date.now()}-${Math.random().toString(36).slice(2,10)}`;
        const createTask = (type, url, payload, dependencyId=null) => ({
            id: uid(), type, url, payload: deepClone(payload), dependency_id: dependencyId,
            attempts: 0, status: 'pending', created_at: new Date().toISOString(),
            next_retry_at: null, last_error: null
        });
        const request = async task => {
            if (!fetchImpl) throw new Error('fetch APIが利用できません。');
            const response = await fetchImpl(task.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...(csrfToken() ? {'X-CSRF-TOKEN': csrfToken()} : {}),
                    ...(options.headers || {})
                },
                body: JSON.stringify(task.payload)
            });
            let body = null;
            try { body = await response.json(); } catch (_) {}
            if (!response.ok) {
                const error = new Error(body?.message || `HTTP ${response.status}`);
                error.status = response.status;
                error.body = body;
                throw error;
            }
            return body || { ok: true };
        };
        const dependencyDone = task => !task.dependency_id || !queue.some(item => item.id === task.dependency_id && item.status !== 'done');
        const due = task => !task.next_retry_at || Date.parse(task.next_retry_at) <= Date.now();

        async function flush() {
            if (processing) return api.getStatus();
            processing = true;
            emit('lifeforce-sync-started', { pending: queue.filter(i => i.status !== 'done').length });
            try {
                let progressed = true;
                while (progressed) {
                    progressed = false;
                    const task = queue.find(item => item.status !== 'done' && item.status !== 'failed' && dependencyDone(item) && due(item));
                    if (!task) break;
                    progressed = true;
                    task.status = 'sending';
                    task.attempts += 1;
                    persist();
                    emit('lifeforce-sync-task-started', task);
                    try {
                        const result = await request(task);
                        task.status = 'done';
                        task.completed_at = new Date().toISOString();
                        task.response = result;
                        task.last_error = null;
                        emit('lifeforce-sync-task-completed', { task, result });
                    } catch (error) {
                        task.last_error = String(error?.message || error);
                        if (task.attempts >= maxAttempts || (error?.status >= 400 && error?.status < 500 && error?.status !== 408 && error?.status !== 429)) {
                            task.status = 'failed';
                            task.failed_at = new Date().toISOString();
                            emit('lifeforce-sync-task-failed', { task, error: task.last_error });
                        } else {
                            task.status = 'pending';
                            const wait = retryBaseMs * Math.pow(2, task.attempts - 1);
                            task.next_retry_at = new Date(Date.now() + wait).toISOString();
                            emit('lifeforce-sync-task-retry', { task, wait_ms: wait });
                        }
                    }
                    persist();
                }
                queue = queue.filter(item => item.status !== 'done');
                persist();
            } finally {
                processing = false;
                emit('lifeforce-sync-finished', api.getStatus());
            }
            return api.getStatus();
        }

        function enqueueLearningResult(result, context={}) {
            const bridge = options.bridge || createStudentLearningBridge({ storageKey: options.bridgeStorageKey });
            const recorded = bridge.record(result);
            const serverPayload = bridge.buildServerPayload(result);
            const root = createTask('learning', endpoints.learning, Object.assign({}, serverPayload, { context }));
            queue.push(root);

            if (Number(recorded.awarded_points || 0) > 0) {
                queue.push(createTask('points', endpoints.points, {
                    student_id: recorded.attempt.student_id,
                    attempt_id: recorded.attempt.attempt_id,
                    points: recorded.awarded_points,
                    source_type: 'shogi', source_id: recorded.attempt.problem_id
                }, root.id));
            }
            (recorded.new_badges || []).forEach(badgeKey => queue.push(createTask('badge', endpoints.badges, {
                student_id: recorded.attempt.student_id,
                attempt_id: recorded.attempt.attempt_id,
                badge_key: badgeKey,
                source_type: 'shogi', source_id: recorded.attempt.problem_id
            }, root.id)));
            if (recorded.routine_completion) {
                queue.push(createTask('routine', endpoints.routine, recorded.routine_completion, root.id));
            }
            if (recorded.student_chart) {
                queue.push(createTask('student-chart', endpoints.studentChart, {
                    student_id: recorded.attempt.student_id,
                    summary: recorded.student_chart,
                    latest_attempt_id: recorded.attempt.attempt_id
                }, root.id));
            }
            persist();
            emit('lifeforce-sync-enqueued', { recorded, queued: queue.length });
            if (options.autoFlush !== false && (typeof navigator === 'undefined' || navigator.onLine !== false)) flush();
            return deepClone(recorded);
        }

        restore();
        if (typeof window !== 'undefined') {
            window.addEventListener('online', () => flush());
        }
        const api = {
            enqueueLearningResult,
            flush,
            retryFailed() {
                queue.forEach(item => { if (item.status === 'failed') { item.status='pending'; item.attempts=0; item.next_retry_at=null; } });
                persist();
                return flush();
            },
            getQueue() { return deepClone(queue); },
            getStatus() {
                return {
                    processing,
                    total: queue.length,
                    pending: queue.filter(i => i.status === 'pending' || i.status === 'sending').length,
                    failed: queue.filter(i => i.status === 'failed').length,
                    online: typeof navigator === 'undefined' ? true : navigator.onLine !== false
                };
            },
            clearCompleted() { queue = queue.filter(item => item.status !== 'done'); persist(); },
            clearAll() { queue = []; persist(); emit('lifeforce-sync-cleared', {}); },
            on(name, handler) {
                if (!listeners.has(name)) listeners.set(name, []);
                listeners.get(name).push(handler);
                return () => listeners.set(name, (listeners.get(name)||[]).filter(fn => fn !== handler));
            }
        };
        return api;
    }


    /**
     * 生徒カルテ・学習ダッシュボード用の集計モデルを生成する。
     * サーバー未接続でも、StudentLearningBridge の attempts 配列から表示用データを作成できる。
     */
    function createStudentLearningDashboard(options={}) {
        const abilityKeys = typeof ABILITY_KEYS !== 'undefined'
            ? ABILITY_KEYS.slice()
            : ['judgment','logic','foresight','spatial','concentration','memory'];
        const abilityLabels = typeof ABILITY_LABELS !== 'undefined'
            ? ABILITY_LABELS
            : {
                judgment:'判断力', logic:'論理的思考力', foresight:'先読み力',
                spatial:'空間認識力', concentration:'集中力', memory:'記憶力'
            };
        const dayKey = value => {
            const date = value ? new Date(value) : null;
            if (!date || Number.isNaN(date.getTime())) return null;
            const y = date.getFullYear();
            const m = String(date.getMonth()+1).padStart(2,'0');
            const d = String(date.getDate()).padStart(2,'0');
            return `${y}-${m}-${d}`;
        };
        const number = (value, fallback=0) => Number.isFinite(Number(value)) ? Number(value) : fallback;
        const average = values => values.length ? values.reduce((sum,v)=>sum+number(v),0)/values.length : 0;
        const round = (value, digits=1) => {
            const unit = Math.pow(10,digits);
            return Math.round(number(value)*unit)/unit;
        };
        const normalizeAttempt = raw => {
            const attempt = raw?.attempt || raw || {};
            const result = raw?.result || attempt.result || {};
            const abilities = attempt.abilities || result.abilities || raw?.abilities || {};
            return {
                attempt_id: attempt.attempt_id || attempt.id || null,
                student_id: attempt.student_id ?? options.studentId ?? null,
                problem_id: attempt.problem_id || result.problem_id || null,
                title: attempt.title || result.title || '',
                category: attempt.category || result.category || '未分類',
                difficulty: attempt.difficulty || result.difficulty || '未設定',
                correct: Boolean(attempt.correct ?? result.correct ?? result.completed),
                score: number(attempt.score ?? result.score),
                elapsed_time: number(attempt.elapsed_time ?? result.elapsed_time ?? result.elapsed_seconds),
                hint_count: number(attempt.hint_count ?? result.hint_count),
                mistake_count: number(attempt.mistake_count ?? result.mistake_count ?? result.incorrect_count),
                perfect: Boolean(attempt.perfect ?? result.perfect),
                completed_at: attempt.completed_at || result.completed_at || attempt.created_at || null,
                abilities: abilityKeys.reduce((map,key)=>{
                    map[key]=number(abilities[key]);
                    return map;
                },{})
            };
        };
        const groupSummary = (attempts, key) => {
            const groups = new Map();
            attempts.forEach(item => {
                const name = item[key] || '未設定';
                if (!groups.has(name)) groups.set(name,[]);
                groups.get(name).push(item);
            });
            return Array.from(groups.entries()).map(([name,items])=>({
                name,
                attempts: items.length,
                correct: items.filter(item=>item.correct).length,
                correct_rate: round(items.length ? items.filter(item=>item.correct).length/items.length*100 : 0),
                average_score: round(average(items.map(item=>item.score))),
                average_time: round(average(items.map(item=>item.elapsed_time))),
                hints: items.reduce((sum,item)=>sum+item.hint_count,0),
                mistakes: items.reduce((sum,item)=>sum+item.mistake_count,0)
            })).sort((a,b)=>b.attempts-a.attempts || a.name.localeCompare(b.name,'ja'));
        };
        const calculateStreak = attempts => {
            const days = Array.from(new Set(attempts.map(item=>dayKey(item.completed_at)).filter(Boolean))).sort();
            if (!days.length) return { current:0, longest:0, active_days:0 };
            let longest=1, run=1;
            for (let i=1;i<days.length;i++) {
                const prev=new Date(`${days[i-1]}T00:00:00`);
                const curr=new Date(`${days[i]}T00:00:00`);
                const diff=Math.round((curr-prev)/86400000);
                if (diff===1) { run+=1; longest=Math.max(longest,run); } else run=1;
            }
            const today=dayKey(new Date());
            const yesterday=dayKey(new Date(Date.now()-86400000));
            let current=0;
            if (days.includes(today) || days.includes(yesterday)) {
                let cursor=new Date(`${days[days.length-1]}T00:00:00`);
                current=1;
                for (let i=days.length-2;i>=0;i--) {
                    const d=new Date(`${days[i]}T00:00:00`);
                    if (Math.round((cursor-d)/86400000)===1) { current+=1; cursor=d; } else break;
                }
            }
            return { current, longest, active_days:days.length };
        };
        const build = (records=[], problemCatalog=[]) => {
            const attempts=(Array.isArray(records)?records:[]).map(normalizeAttempt)
                .filter(item=>!options.studentId || String(item.student_id)===String(options.studentId));
            const completed=attempts.filter(item=>item.completed_at).sort((a,b)=>Date.parse(a.completed_at)-Date.parse(b.completed_at));
            const correct=attempts.filter(item=>item.correct);
            const abilitySummary=abilityKeys.map(key=>{
                const values=attempts.map(item=>item.abilities[key]).filter(value=>value>0);
                return { key, label:abilityLabels[key] || key, value:round(average(values)), samples:values.length };
            });
            const dailyMap=new Map();
            completed.forEach(item=>{
                const key=dayKey(item.completed_at);
                if (!dailyMap.has(key)) dailyMap.set(key,[]);
                dailyMap.get(key).push(item);
            });
            const daily=Array.from(dailyMap.entries()).map(([date,items])=>({
                date,
                attempts:items.length,
                correct:items.filter(item=>item.correct).length,
                correct_rate:round(items.filter(item=>item.correct).length/items.length*100),
                average_score:round(average(items.map(item=>item.score))),
                study_seconds:Math.round(items.reduce((sum,item)=>sum+item.elapsed_time,0))
            }));
            const weakAbilities=abilitySummary.filter(item=>item.samples>0).sort((a,b)=>a.value-b.value).slice(0,3);
            const weakDifficulties=groupSummary(attempts,'difficulty').filter(item=>item.attempts>=2)
                .sort((a,b)=>a.correct_rate-b.correct_rate || a.average_score-b.average_score).slice(0,3);
            const weakCategories=groupSummary(attempts,'category').filter(item=>item.attempts>=2)
                .sort((a,b)=>a.correct_rate-b.correct_rate || a.average_score-b.average_score).slice(0,3);
            const catalog=Array.isArray(problemCatalog)?problemCatalog:[];
            const solvedIds=new Set(correct.map(item=>String(item.problem_id)));
            const recommended=catalog.filter(problem=>problem && problem.id!=null && !solvedIds.has(String(problem.id)))
                .map(problem=>{
                    const abilities=problem.abilities || {};
                    const abilityMatch=weakAbilities.reduce((sum,item)=>sum+number(abilities[item.key]),0);
                    const categoryMatch=weakCategories.some(item=>item.name===(problem.category||'未分類')) ? 20 : 0;
                    const difficultyMatch=weakDifficulties.some(item=>item.name===(problem.difficulty||'未設定')) ? 10 : 0;
                    return { problem:deepClone(problem), priority:abilityMatch+categoryMatch+difficultyMatch };
                }).sort((a,b)=>b.priority-a.priority).slice(0,Number(options.recommendationLimit||5));
            return {
                student_id: options.studentId ?? attempts[0]?.student_id ?? null,
                generated_at:new Date().toISOString(),
                summary:{
                    attempts:attempts.length,
                    correct:correct.length,
                    correct_rate:round(attempts.length ? correct.length/attempts.length*100 : 0),
                    average_score:round(average(attempts.map(item=>item.score))),
                    average_time:round(average(attempts.map(item=>item.elapsed_time))),
                    total_study_seconds:Math.round(attempts.reduce((sum,item)=>sum+item.elapsed_time,0)),
                    hints:attempts.reduce((sum,item)=>sum+item.hint_count,0),
                    mistakes:attempts.reduce((sum,item)=>sum+item.mistake_count,0),
                    perfect:attempts.filter(item=>item.perfect).length
                },
                streak:calculateStreak(completed),
                abilities:abilitySummary,
                by_difficulty:groupSummary(attempts,'difficulty'),
                by_category:groupSummary(attempts,'category'),
                daily,
                weak_points:{ abilities:weakAbilities, difficulties:weakDifficulties, categories:weakCategories },
                recommendations:recommended,
                recent:completed.slice(-Number(options.recentLimit||10)).reverse()
            };
        };
        return {
            build,
            normalizeAttempt: raw=>deepClone(normalizeAttempt(raw)),
            exportForStudentChart(records,catalog=[]) {
                const dashboard=build(records,catalog);
                return {
                    student_id:dashboard.student_id,
                    summary:dashboard.summary,
                    streak:dashboard.streak,
                    abilities:dashboard.abilities,
                    weak_points:dashboard.weak_points,
                    generated_at:dashboard.generated_at
                };
            }
        };
    }


    function createClassroomLearningReport(options={}) {
        const number=value=>Number.isFinite(Number(value)) ? Number(value) : 0;
        const round=(value,digits=1)=>{
            const factor=10**digits;
            return Math.round(number(value)*factor)/factor;
        };
        const average=values=>values.length ? values.reduce((sum,value)=>sum+number(value),0)/values.length : 0;
        const normalizeStudent=raw=>({
            student_id:raw?.student_id ?? raw?.id ?? null,
            student_name:String(raw?.student_name ?? raw?.name ?? ''),
            school_id:raw?.school_id ?? null,
            grade:String(raw?.grade ?? ''),
            class_name:String(raw?.class_name ?? ''),
            dashboard:raw?.dashboard && typeof raw.dashboard==='object' ? deepClone(raw.dashboard) : null,
            records:Array.isArray(raw?.records) ? deepClone(raw.records) : []
        });
        const riskLevel=score=>score>=70?'high':score>=40?'medium':'low';
        const buildStudentRow=(student,problemCatalog=[])=>{
            const dashboard=student.dashboard || createStudentLearningDashboard({studentId:student.student_id}).build(student.records,problemCatalog);
            const summary=dashboard.summary || {};
            const weakAbilities=dashboard.weak_points?.abilities || [];
            const inactiveDays=(()=>{
                const latest=Array.isArray(dashboard.recent) && dashboard.recent[0]?.completed_at ? Date.parse(dashboard.recent[0].completed_at) : NaN;
                return Number.isFinite(latest) ? Math.max(0,Math.floor((Date.now()-latest)/86400000)) : null;
            })();
            let riskScore=0;
            if (number(summary.attempts)===0) riskScore+=45;
            if (number(summary.attempts)>0 && number(summary.correct_rate)<60) riskScore+=30;
            if (number(summary.average_score)<60 && number(summary.attempts)>0) riskScore+=20;
            if (inactiveDays===null || inactiveDays>=7) riskScore+=25;
            else if (inactiveDays>=3) riskScore+=10;
            if (number(summary.hints)>Math.max(3,number(summary.attempts))) riskScore+=10;
            riskScore=Math.min(100,riskScore);
            const notices=[];
            if (number(summary.attempts)===0) notices.push('未学習');
            if (number(summary.attempts)>0 && number(summary.correct_rate)<60) notices.push('正答率低下');
            if (number(summary.average_score)<60 && number(summary.attempts)>0) notices.push('平均点低下');
            if (inactiveDays===null || inactiveDays>=7) notices.push('学習間隔が空いています');
            if (weakAbilities.length) notices.push(`苦手：${weakAbilities.map(item=>item.label||item.key).slice(0,2).join('・')}`);
            return {
                student_id:student.student_id,
                student_name:student.student_name,
                school_id:student.school_id,
                grade:student.grade,
                class_name:student.class_name,
                summary:deepClone(summary),
                streak:deepClone(dashboard.streak || {}),
                abilities:deepClone(dashboard.abilities || []),
                weak_points:deepClone(dashboard.weak_points || {}),
                recommendations:deepClone(dashboard.recommendations || []),
                inactive_days:inactiveDays,
                risk_score:riskScore,
                risk_level:riskLevel(riskScore),
                notices
            };
        };
        const build=(students=[],problemCatalog=[])=>{
            const rows=(Array.isArray(students)?students:[]).map(normalizeStudent).map(student=>buildStudentRow(student,problemCatalog));
            const active=rows.filter(row=>number(row.summary.attempts)>0);
            const abilityKeys=Array.from(new Set(rows.flatMap(row=>row.abilities.map(item=>item.key))));
            const abilities=abilityKeys.map(key=>{
                const values=rows.flatMap(row=>row.abilities.filter(item=>item.key===key && number(item.samples)>0).map(item=>number(item.value)));
                const label=rows.flatMap(row=>row.abilities).find(item=>item.key===key)?.label || key;
                return {key,label,value:round(average(values)),students:values.length};
            });
            const rankings=[...rows].filter(row=>number(row.summary.attempts)>0).sort((a,b)=>
                number(b.summary.average_score)-number(a.summary.average_score) ||
                number(b.summary.correct_rate)-number(a.summary.correct_rate) ||
                number(b.summary.attempts)-number(a.summary.attempts)
            ).map((row,index)=>({
                rank:index+1,
                student_id:row.student_id,
                student_name:row.student_name,
                average_score:number(row.summary.average_score),
                correct_rate:number(row.summary.correct_rate),
                attempts:number(row.summary.attempts)
            }));
            const alerts=rows.filter(row=>row.risk_level!=='low').sort((a,b)=>b.risk_score-a.risk_score);
            return {
                generated_at:new Date().toISOString(),
                school_id:options.schoolId ?? null,
                summary:{
                    students:rows.length,
                    active_students:active.length,
                    inactive_students:rows.length-active.length,
                    attempts:rows.reduce((sum,row)=>sum+number(row.summary.attempts),0),
                    correct_rate:round(average(active.map(row=>number(row.summary.correct_rate)))),
                    average_score:round(average(active.map(row=>number(row.summary.average_score)))),
                    total_study_seconds:Math.round(rows.reduce((sum,row)=>sum+number(row.summary.total_study_seconds),0)),
                    high_risk:rows.filter(row=>row.risk_level==='high').length,
                    medium_risk:rows.filter(row=>row.risk_level==='medium').length
                },
                abilities,
                students:rows,
                alerts,
                rankings
            };
        };
        return {
            build,
            exportForTeacher(students,catalog=[]) {
                const report=build(students,catalog);
                return {
                    generated_at:report.generated_at,
                    school_id:report.school_id,
                    summary:report.summary,
                    abilities:report.abilities,
                    alerts:report.alerts.map(row=>({
                        student_id:row.student_id,
                        student_name:row.student_name,
                        risk_score:row.risk_score,
                        risk_level:row.risk_level,
                        notices:row.notices
                    })),
                    rankings:report.rankings
                };
            }
        };
    }


    function createLearningInterventionPlanner(options={}) {
        const storageKey=String(options.storageKey || 'lle-shogi-learning-interventions-v1');
        const defaultDueDays=Math.max(1,Math.min(90,Number(options.defaultDueDays)||14));
        const nowIso=()=>new Date().toISOString();
        const clone=value=>deepClone(value);
        const normalizeStatus=value=>['open','in_progress','completed','cancelled'].includes(value)?value:'open';
        const normalizePriority=value=>['high','medium','low'].includes(value)?value:'medium';
        const normalizeDate=value=>{
            if (!value) return null;
            const date=new Date(value);
            return Number.isNaN(date.getTime())?null:date.toISOString();
        };
        const load=()=>{
            try {
                const raw=localStorage.getItem(storageKey);
                const parsed=raw?JSON.parse(raw):[];
                return Array.isArray(parsed)?parsed:[];
            } catch (error) {
                return [];
            }
        };
        let records=load();
        const save=()=>{
            try { localStorage.setItem(storageKey,JSON.stringify(records)); } catch (error) {}
            document.dispatchEvent(new CustomEvent('lle:shogi:interventions-changed',{detail:{count:records.length}}));
        };
        const dueDate=(days=defaultDueDays)=>{
            const date=new Date();
            date.setDate(date.getDate()+Math.max(1,Number(days)||defaultDueDays));
            return date.toISOString();
        };
        const uniqueId=()=>`shi_${Date.now()}_${Math.random().toString(36).slice(2,10)}`;
        const normalize=item=>({
            id:String(item.id||uniqueId()),
            student_id:item.student_id??null,
            student_name:String(item.student_name||''),
            school_id:item.school_id??options.schoolId??null,
            source:String(item.source||'manual'),
            source_key:item.source_key?String(item.source_key):null,
            title:String(item.title||'学習フォロー'),
            description:String(item.description||''),
            priority:normalizePriority(item.priority),
            status:normalizeStatus(item.status),
            assigned_teacher_id:item.assigned_teacher_id??null,
            assigned_teacher_name:String(item.assigned_teacher_name||''),
            due_at:normalizeDate(item.due_at)||dueDate(),
            completed_at:normalizeDate(item.completed_at),
            notes:String(item.notes||''),
            recommended_problem_ids:Array.isArray(item.recommended_problem_ids)?item.recommended_problem_ids.map(String):[],
            metadata:item.metadata&&typeof item.metadata==='object'?clone(item.metadata):{},
            created_at:normalizeDate(item.created_at)||nowIso(),
            updated_at:normalizeDate(item.updated_at)||nowIso()
        });
        records=records.map(normalize);
        const buildSuggestions=(classroomReport)=>{
            const rows=Array.isArray(classroomReport?.students)?classroomReport.students:[];
            const suggestions=[];
            rows.forEach(row=>{
                const common={
                    student_id:row.student_id,
                    student_name:row.student_name,
                    school_id:row.school_id??classroomReport?.school_id??options.schoolId??null,
                    source:'classroom_report',
                    recommended_problem_ids:(row.recommendations||[]).map(item=>String(item.problem_id??item.id??'')).filter(Boolean),
                    metadata:{risk_score:Number(row.risk_score)||0,risk_level:row.risk_level||'low'}
                };
                if (row.risk_level==='high') suggestions.push(normalize({...common,
                    source_key:`risk:${row.student_id}:high`,
                    title:'至急：学習状況の個別確認',
                    description:(row.notices||[]).join('／')||'学習状況に複数の注意点があります。',
                    priority:'high',due_at:dueDate(3)
                }));
                else if (row.risk_level==='medium') suggestions.push(normalize({...common,
                    source_key:`risk:${row.student_id}:medium`,
                    title:'学習状況のフォロー',
                    description:(row.notices||[]).join('／')||'学習状況を確認してください。',
                    priority:'medium',due_at:dueDate(7)
                }));
                const weak=(row.weak_points?.abilities||row.weak_points?.ability||[]);
                const weakList=Array.isArray(weak)?weak:[];
                if (weakList.length) suggestions.push(normalize({...common,
                    source_key:`ability:${row.student_id}:${weakList.map(item=>item.key||item.label).join(',')}`,
                    title:'苦手能力の重点トレーニング',
                    description:`重点項目：${weakList.slice(0,3).map(item=>item.label||item.key).join('・')}`,
                    priority:row.risk_level==='high'?'high':'medium',due_at:dueDate(14),
                    metadata:{...common.metadata,weak_abilities:clone(weakList)}
                }));
            });
            return suggestions;
        };
        const addMany=(items,{deduplicate=true}={})=>{
            const added=[];
            (Array.isArray(items)?items:[]).forEach(item=>{
                const normalized=normalize(item);
                if (deduplicate && normalized.source_key && records.some(record=>record.source_key===normalized.source_key && record.status!=='cancelled')) return;
                records.push(normalized); added.push(normalized);
            });
            if (added.length) save();
            return clone(added);
        };
        return {
            suggestFromClassroomReport:report=>clone(buildSuggestions(report)),
            create:item=>addMany([item],{deduplicate:false})[0]||null,
            createFromClassroomReport:(report,opts={})=>addMany(buildSuggestions(report),opts),
            list(filters={}) {
                let list=[...records];
                if (filters.student_id!=null) list=list.filter(item=>String(item.student_id)===String(filters.student_id));
                if (filters.status) list=list.filter(item=>item.status===filters.status);
                if (filters.priority) list=list.filter(item=>item.priority===filters.priority);
                if (filters.assigned_teacher_id!=null) list=list.filter(item=>String(item.assigned_teacher_id)===String(filters.assigned_teacher_id));
                if (filters.overdue===true) list=list.filter(item=>item.status!=='completed' && item.status!=='cancelled' && item.due_at && new Date(item.due_at)<new Date());
                return clone(list.sort((a,b)=>new Date(a.due_at||0)-new Date(b.due_at||0)));
            },
            get:id=>clone(records.find(item=>item.id===String(id))||null),
            update(id,changes={}) {
                const index=records.findIndex(item=>item.id===String(id));
                if (index<0) return null;
                const next=normalize({...records[index],...changes,id:records[index].id,created_at:records[index].created_at,updated_at:nowIso()});
                if (next.status==='completed' && !next.completed_at) next.completed_at=nowIso();
                if (next.status!=='completed') next.completed_at=null;
                records[index]=next; save(); return clone(next);
            },
            complete(id,notes='') {
                const index=records.findIndex(item=>item.id===String(id));
                if (index<0) return null;
                records[index]=normalize({...records[index],status:'completed',notes:notes||records[index].notes,completed_at:nowIso(),updated_at:nowIso()});
                save(); return clone(records[index]);
            },
            remove(id) {
                const before=records.length; records=records.filter(item=>item.id!==String(id));
                if (records.length!==before) save();
                return records.length!==before;
            },
            summary() {
                const current=new Date();
                return {
                    total:records.length,
                    open:records.filter(item=>item.status==='open').length,
                    in_progress:records.filter(item=>item.status==='in_progress').length,
                    completed:records.filter(item=>item.status==='completed').length,
                    overdue:records.filter(item=>!['completed','cancelled'].includes(item.status)&&item.due_at&&new Date(item.due_at)<current).length,
                    high_priority:records.filter(item=>item.priority==='high'&&!['completed','cancelled'].includes(item.status)).length
                };
            },
            exportForLaravel() {
                return records.map(item=>({
                    client_id:item.id,student_id:item.student_id,school_id:item.school_id,title:item.title,
                    description:item.description,priority:item.priority,status:item.status,
                    assigned_teacher_id:item.assigned_teacher_id,due_at:item.due_at,completed_at:item.completed_at,
                    notes:item.notes,recommended_problem_ids:item.recommended_problem_ids,metadata:item.metadata,
                    created_at:item.created_at,updated_at:item.updated_at
                }));
            },
            clear() { records=[]; save(); },
            storageKey
        };
    }


    function createPersonalizedLearningPlan(options={}) {
        const storageKey=String(options.storageKey || 'lle-shogi-personalized-learning-plans-v1');
        const clone=value=>deepClone(value);
        const nowIso=()=>new Date().toISOString();
        const uniqueId=()=>`shp_${Date.now()}_${Math.random().toString(36).slice(2,10)}`;
        const normalizeDate=value=>{
            if (!value) return null;
            const date=new Date(value);
            return Number.isNaN(date.getTime())?null:date.toISOString();
        };
        const load=()=>{
            try {
                const raw=localStorage.getItem(storageKey);
                const parsed=raw?JSON.parse(raw):[];
                return Array.isArray(parsed)?parsed:[];
            } catch (error) { return []; }
        };
        let plans=load();
        const save=()=>{
            try { localStorage.setItem(storageKey,JSON.stringify(plans)); } catch (error) {}
            document.dispatchEvent(new CustomEvent('lle:shogi:learning-plans-changed',{detail:{count:plans.length}}));
        };
        const normalizeStatus=value=>['draft','active','completed','cancelled'].includes(value)?value:'draft';
        const normalizeTask=task=>({
            id:String(task?.id||uniqueId()),
            problem_id:task?.problem_id!=null?String(task.problem_id):null,
            title:String(task?.title||'将棋問題'),
            reason:String(task?.reason||''),
            difficulty:String(task?.difficulty||''),
            category:String(task?.category||''),
            target_count:Math.max(1,Number(task?.target_count)||1),
            completed_count:Math.max(0,Number(task?.completed_count)||0),
            target_score:Math.max(0,Math.min(100,Number(task?.target_score)||80)),
            due_at:normalizeDate(task?.due_at),
            completed_at:normalizeDate(task?.completed_at),
            metadata:task?.metadata&&typeof task.metadata==='object'?clone(task.metadata):{}
        });
        const normalize=plan=>({
            id:String(plan?.id||uniqueId()),
            student_id:plan?.student_id??null,
            student_name:String(plan?.student_name||''),
            school_id:plan?.school_id??options.schoolId??null,
            title:String(plan?.title||'将棋 個別学習プラン'),
            objective:String(plan?.objective||''),
            status:normalizeStatus(plan?.status),
            start_at:normalizeDate(plan?.start_at)||nowIso(),
            end_at:normalizeDate(plan?.end_at),
            assigned_teacher_id:plan?.assigned_teacher_id??null,
            assigned_teacher_name:String(plan?.assigned_teacher_name||''),
            tasks:(Array.isArray(plan?.tasks)?plan.tasks:[]).map(normalizeTask),
            notes:String(plan?.notes||''),
            source:String(plan?.source||'manual'),
            metadata:plan?.metadata&&typeof plan.metadata==='object'?clone(plan.metadata):{},
            created_at:normalizeDate(plan?.created_at)||nowIso(),
            updated_at:normalizeDate(plan?.updated_at)||nowIso()
        });
        plans=plans.map(normalize);
        const addDays=(base,days)=>{
            const date=new Date(base||Date.now());
            date.setDate(date.getDate()+Math.max(1,Number(days)||7));
            return date.toISOString();
        };
        const findProblem=(catalog,id)=>{
            const list=Array.isArray(catalog)?catalog:[];
            return list.find(item=>String(item.id??item.problem_id??'')===String(id))||null;
        };
        const deriveRecommendations=(dashboard,catalog,limit=5)=>{
            const rows=[];
            const seen=new Set();
            const push=(item,reason)=>{
                if (!item) return;
                const id=String(item.problem_id??item.id??'');
                if (!id || seen.has(id)) return;
                seen.add(id);
                const problem=findProblem(catalog,id)||item;
                rows.push({
                    problem_id:id,
                    title:String(problem.title||item.title||`問題 ${id}`),
                    reason:String(reason||item.reason||'おすすめ問題'),
                    difficulty:String(problem.difficulty||item.difficulty||''),
                    category:String(problem.category||item.category||''),
                    target_count:1,
                    target_score:80,
                    metadata:{abilities:clone(problem.abilities||item.abilities||{})}
                });
            };
            (dashboard?.recommendations||[]).forEach(item=>push(item,item.reason||'学習状況に基づくおすすめ'));
            const weakAbilities=dashboard?.weak_points?.abilities||dashboard?.weak_abilities||[];
            const weakKeys=(Array.isArray(weakAbilities)?weakAbilities:[]).map(item=>String(item.key||item.ability||item)).filter(Boolean);
            if (weakKeys.length) {
                (Array.isArray(catalog)?catalog:[]).forEach(problem=>{
                    const abilities=problem.abilities&&typeof problem.abilities==='object'?problem.abilities:{};
                    const matched=weakKeys.filter(key=>Number(abilities[key])>0);
                    if (matched.length) push(problem,`苦手能力（${matched.map(key=>ABILITY_LABELS[key]||key).join('・')}）の強化`);
                });
            }
            return rows.slice(0,Math.max(1,Number(limit)||5));
        };
        const progressOf=plan=>{
            const tasks=Array.isArray(plan.tasks)?plan.tasks:[];
            const target=tasks.reduce((sum,item)=>sum+Math.max(1,Number(item.target_count)||1),0);
            const completed=tasks.reduce((sum,item)=>sum+Math.min(Math.max(0,Number(item.completed_count)||0),Math.max(1,Number(item.target_count)||1)),0);
            return {
                task_count:tasks.length,
                completed_task_count:tasks.filter(item=>Number(item.completed_count)>=Number(item.target_count)).length,
                target_count:target,
                completed_count:completed,
                completion_rate:target?Math.round((completed/target)*100):0
            };
        };
        return {
            create(plan={}) {
                const normalized=normalize(plan);
                plans.push(normalized); save(); return clone(normalized);
            },
            createFromDashboard(dashboard,catalog=[],settings={}) {
                const weeks=Math.max(1,Math.min(12,Number(settings.weeks)||4));
                const start=normalizeDate(settings.start_at)||nowIso();
                const tasks=deriveRecommendations(dashboard,catalog,settings.problem_limit||5).map((task,index)=>normalizeTask({
                    ...task,
                    target_count:Math.max(1,Number(settings.target_count_per_problem)||1),
                    target_score:Math.max(0,Math.min(100,Number(settings.target_score)||80)),
                    due_at:addDays(start,Math.ceil(((index+1)/Math.max(1,Number(settings.problem_limit)||5))*weeks*7))
                }));
                const weakLabels=(dashboard?.weak_points?.abilities||[]).map(item=>item.label||ABILITY_LABELS[item.key]||item.key).filter(Boolean);
                const plan=normalize({
                    student_id:dashboard?.student_id??settings.student_id??null,
                    student_name:dashboard?.student_name??settings.student_name??'',
                    school_id:dashboard?.school_id??settings.school_id??options.schoolId??null,
                    title:settings.title||'将棋 個別学習プラン',
                    objective:settings.objective||(`${weakLabels.length?`${weakLabels.slice(0,3).join('・')}を重点的に強化し、`:''}正答率と考える力の向上を目指します。`),
                    status:settings.status||'draft',
                    start_at:start,
                    end_at:addDays(start,weeks*7),
                    assigned_teacher_id:settings.assigned_teacher_id??null,
                    assigned_teacher_name:settings.assigned_teacher_name||'',
                    tasks,
                    source:'student_dashboard',
                    metadata:{weeks,generated_from_dashboard:true}
                });
                plans.push(plan); save(); return clone(plan);
            },
            list(filters={}) {
                let list=[...plans];
                if (filters.student_id!=null) list=list.filter(item=>String(item.student_id)===String(filters.student_id));
                if (filters.status) list=list.filter(item=>item.status===filters.status);
                if (filters.assigned_teacher_id!=null) list=list.filter(item=>String(item.assigned_teacher_id)===String(filters.assigned_teacher_id));
                return clone(list.sort((a,b)=>new Date(b.updated_at)-new Date(a.updated_at)));
            },
            get:id=>clone(plans.find(item=>item.id===String(id))||null),
            update(id,changes={}) {
                const index=plans.findIndex(item=>item.id===String(id));
                if (index<0) return null;
                plans[index]=normalize({...plans[index],...changes,id:plans[index].id,created_at:plans[index].created_at,updated_at:nowIso()});
                save(); return clone(plans[index]);
            },
            updateTask(planId,taskId,changes={}) {
                const index=plans.findIndex(item=>item.id===String(planId));
                if (index<0) return null;
                const tasks=plans[index].tasks.map(task=>task.id===String(taskId)?normalizeTask({...task,...changes,id:task.id}):task);
                const allCompleted=tasks.length>0&&tasks.every(task=>Number(task.completed_count)>=Number(task.target_count));
                plans[index]=normalize({...plans[index],tasks,status:allCompleted?'completed':plans[index].status,updated_at:nowIso()});
                save(); return clone(plans[index]);
            },
            applyLearningResult(planId,result={}) {
                const problemId=String(result.problem_id??result.problemId??'');
                if (!problemId) return null;
                const index=plans.findIndex(item=>item.id===String(planId));
                if (index<0) return null;
                const tasks=plans[index].tasks.map(task=>{
                    if (String(task.problem_id)!==problemId) return task;
                    const count=Math.min(task.target_count,task.completed_count+(result.correct===false?0:1));
                    return normalizeTask({...task,completed_count:count,completed_at:count>=task.target_count?nowIso():task.completed_at,metadata:{...task.metadata,last_score:Number(result.score)||0,last_result_at:nowIso()}});
                });
                const allCompleted=tasks.length>0&&tasks.every(task=>task.completed_count>=task.target_count);
                plans[index]=normalize({...plans[index],tasks,status:allCompleted?'completed':(plans[index].status==='draft'?'active':plans[index].status),updated_at:nowIso()});
                save(); return clone(plans[index]);
            },
            progress(id) {
                const plan=plans.find(item=>item.id===String(id));
                return plan?progressOf(plan):null;
            },
            summary(studentId=null) {
                const list=studentId==null?plans:plans.filter(item=>String(item.student_id)===String(studentId));
                const active=list.filter(item=>item.status==='active');
                return {
                    total:list.length,
                    draft:list.filter(item=>item.status==='draft').length,
                    active:active.length,
                    completed:list.filter(item=>item.status==='completed').length,
                    overdue_tasks:active.reduce((sum,plan)=>sum+plan.tasks.filter(task=>task.completed_count<task.target_count&&task.due_at&&new Date(task.due_at)<new Date()).length,0),
                    average_completion_rate:list.length?Math.round(list.reduce((sum,plan)=>sum+progressOf(plan).completion_rate,0)/list.length):0
                };
            },
            remove(id) {
                const before=plans.length; plans=plans.filter(item=>item.id!==String(id));
                if (plans.length!==before) save();
                return plans.length!==before;
            },
            exportForLaravel(id=null) {
                const list=id?plans.filter(item=>item.id===String(id)):plans;
                return clone(list.map(plan=>({...plan,progress:progressOf(plan)})));
            },
            clear() { plans=[]; save(); },
            storageKey
        };
    }


    /**
     * 保護者向け将棋学習レポート生成
     * 生徒カルテ用ダッシュボードと個別学習プランを、専門用語を抑えた
     * 家庭向けの報告データへ変換する。表示や送信そのものはLaravel側で行う。
     */
    function createGuardianLearningReport(options={}) {
        const clone=value=>deepClone(value);
        const percent=value=>Math.max(0,Math.min(100,Math.round(Number(value)||0)));
        const formatMinutes=seconds=>{
            const total=Math.max(0,Math.round(Number(seconds)||0));
            if (total<60) return `${total}秒`;
            const minutes=Math.floor(total/60);
            const remain=total%60;
            return remain?`${minutes}分${remain}秒`:`${minutes}分`;
        };
        const abilityLabel=key=>ABILITY_LABELS[key]||String(key||'');
        const difficultyLabel=value=>String(value||'未設定');
        const buildEncouragement=dashboard=>{
            const attempts=Number(dashboard?.summary?.total_attempts??dashboard?.summary?.attempts??0);
            const accuracy=percent(dashboard?.summary?.accuracy_rate??dashboard?.summary?.correct_rate??0);
            const streak=Number(dashboard?.summary?.current_streak_days??0);
            if (!attempts) return 'まずは一問ずつ、盤面をよく見ながら取り組んでいきましょう。';
            if (accuracy>=90) return 'とても高い正答率です。考えた手順を言葉にすると、さらに理解が深まります。';
            if (streak>=3) return `現在${streak}日連続で学習できています。この調子で無理なく続けていきましょう。`;
            if (accuracy>=70) return '着実に力がついています。間違えた問題をもう一度解くと、読みの精度が上がります。';
            return '難しい問題にも挑戦できています。正解だけでなく、考えた過程を大切に進めましょう。';
        };
        const create=(dashboard={},plans=[],settings={})=>{
            const summary=dashboard.summary||{};
            const abilities=Array.isArray(dashboard.abilities)?dashboard.abilities:[];
            const sortedAbilities=[...abilities].sort((a,b)=>Number(b.score??b.value??0)-Number(a.score??a.value??0));
            const strong=sortedAbilities.slice(0,2).map(item=>({
                key:item.key,
                label:item.label||abilityLabel(item.key),
                score:percent(item.score??item.value)
            }));
            const weak=[...sortedAbilities].reverse().slice(0,2).map(item=>({
                key:item.key,
                label:item.label||abilityLabel(item.key),
                score:percent(item.score??item.value)
            }));
            const activePlans=(Array.isArray(plans)?plans:[]).filter(plan=>plan&&['active','draft'].includes(plan.status));
            const planItems=activePlans.map(plan=>({
                id:plan.id,
                title:plan.title||'個別学習プラン',
                status:plan.status,
                end_at:plan.end_at||null,
                completion_rate:percent(plan.progress?.completion_rate??plan.completion_rate??0),
                remaining_tasks:Number(plan.progress?.remaining_tasks??0)
            }));
            const recent=Array.isArray(dashboard.recent_history)?dashboard.recent_history.slice(0,5):[];
            const report={
                schema:'lifeforce.shogi.guardian-report',
                schema_version:1,
                report_id:settings.report_id||`shogi-guardian-${Date.now()}`,
                generated_at:new Date().toISOString(),
                period:{
                    from:settings.from||dashboard.period?.from||null,
                    to:settings.to||dashboard.period?.to||null,
                    label:settings.period_label||dashboard.period?.label||'最近の学習'
                },
                student:{
                    id:dashboard.student_id??settings.student_id??null,
                    name:dashboard.student_name||settings.student_name||''
                },
                school:{
                    id:dashboard.school_id??settings.school_id??null,
                    name:dashboard.school_name||settings.school_name||''
                },
                headline:settings.headline||'将棋学習レポート',
                summary:{
                    attempts:Number(summary.total_attempts??summary.attempts??0),
                    correct:Number(summary.correct_count??summary.correct??0),
                    accuracy_rate:percent(summary.accuracy_rate??summary.correct_rate??0),
                    average_score:percent(summary.average_score??0),
                    total_learning_time_seconds:Number(summary.total_learning_time_seconds??summary.total_learning_time??0),
                    total_learning_time_label:formatMinutes(summary.total_learning_time_seconds??summary.total_learning_time??0),
                    current_streak_days:Number(summary.current_streak_days??0),
                    perfect_count:Number(summary.perfect_count??0)
                },
                strengths:strong,
                focus_areas:weak,
                weak_difficulty:dashboard.weak_difficulty?difficultyLabel(dashboard.weak_difficulty):null,
                weak_category:dashboard.weak_category||null,
                active_plans:planItems,
                recent_learning:clone(recent),
                recommended_problems:clone((dashboard.recommended_problems||[]).slice(0,5)),
                message:settings.message||buildEncouragement(dashboard),
                teacher_comment:settings.teacher_comment||'',
                home_support:settings.home_support||[
                    '答えをすぐに教えず、どの駒に注目したかを聞いてみてください。',
                    '短時間でも、同じ問題をもう一度解くことで読みの定着につながります。'
                ],
                privacy:{
                    includes_internal_risk:false,
                    includes_teacher_only_notes:false
                }
            };
            return clone(report);
        };
        return {
            create,
            toLaravelPayload:(dashboard,plans,settings={})=>({
                student_id:dashboard?.student_id??settings.student_id??null,
                report_type:'shogi_learning',
                report:create(dashboard,plans,settings)
            }),
            toPlainText:(report={})=>{
                const name=report.student?.name||'生徒';
                const s=report.summary||{};
                const strengths=(report.strengths||[]).map(item=>item.label).join('・')||'学習継続';
                const focus=(report.focus_areas||[]).map(item=>item.label).join('・')||'基礎力';
                return [
                    `${name}さんの${report.headline||'将棋学習レポート'}`,
                    `挑戦数：${Number(s.attempts)||0}問`,
                    `正答率：${percent(s.accuracy_rate)}%`,
                    `平均点：${percent(s.average_score)}点`,
                    `学習時間：${s.total_learning_time_label||formatMinutes(s.total_learning_time_seconds)}`,
                    `伸びている力：${strengths}`,
                    `これから伸ばす力：${focus}`,
                    report.message||''
                ].filter(Boolean).join('\n');
            }
        };
    }


    /**
     * 保護者向け学習レポートの配信・既読管理。
     * 実際のメール／アプリ通知送信はLaravel側が担当し、このクラスは
     * 配信予約・送信結果・既読状態を同一形式で管理する。
     */
    function createGuardianReportDeliveryManager(options={}){
        const storageKey=String(options.storage_key||'lle_shogi_guardian_report_deliveries_v1');
        const maxItems=Math.max(20,Number(options.max_items)||500);
        let deliveries=[];
        const cloneValue=value=>deepClone(value);
        const now=()=>new Date().toISOString();
        const makeId=()=>`shogi-report-delivery-${Date.now()}-${Math.random().toString(36).slice(2,9)}`;
        const normalizeChannel=value=>['in_app','email','both'].includes(value)?value:'in_app';
        const normalizeStatus=value=>['draft','scheduled','sending','sent','failed','cancelled'].includes(value)?value:'draft';
        const normalize=item=>({
            id:String(item?.id||makeId()),
            report_id:item?.report_id??item?.report?.report_id??null,
            student_id:item?.student_id??item?.report?.student?.id??null,
            guardian_ids:Array.isArray(item?.guardian_ids)?[...new Set(item.guardian_ids.filter(Boolean))]:[],
            channel:normalizeChannel(item?.channel),
            subject:String(item?.subject||'将棋学習レポート'),
            message:String(item?.message||''),
            report:cloneValue(item?.report||null),
            status:normalizeStatus(item?.status),
            scheduled_at:item?.scheduled_at||null,
            sent_at:item?.sent_at||null,
            failed_at:item?.failed_at||null,
            failure_reason:item?.failure_reason||null,
            read_by:Array.isArray(item?.read_by)?cloneValue(item.read_by):[],
            created_by:item?.created_by??null,
            created_at:item?.created_at||now(),
            updated_at:item?.updated_at||now()
        });
        const persist=()=>{
            try{localStorage.setItem(storageKey,JSON.stringify(deliveries.slice(-maxItems)));}catch(error){}
        };
        const restore=()=>{
            try{
                const raw=JSON.parse(localStorage.getItem(storageKey)||'[]');
                deliveries=Array.isArray(raw)?raw.map(normalize).slice(-maxItems):[];
            }catch(error){deliveries=[];}
            return cloneValue(deliveries);
        };
        const emit=(name,detail)=>{
            if(typeof window!=='undefined'&&typeof window.dispatchEvent==='function'){
                window.dispatchEvent(new CustomEvent(`lle:shogi:guardian-report:${name}`,{detail:cloneValue(detail)}));
            }
        };
        const findIndex=id=>deliveries.findIndex(item=>String(item.id)===String(id));
        const save=item=>{
            const normalized=normalize(item);
            const index=findIndex(normalized.id);
            if(index>=0) deliveries[index]=normalized; else deliveries.push(normalized);
            deliveries=deliveries.slice(-maxItems);
            persist();
            emit('changed',normalized);
            return cloneValue(normalized);
        };
        const create=(report,settings={})=>save({
            report_id:report?.report_id||settings.report_id||null,
            student_id:report?.student?.id??settings.student_id??null,
            guardian_ids:settings.guardian_ids||[],
            channel:settings.channel||'in_app',
            subject:settings.subject||`${report?.student?.name||'生徒'}さんの将棋学習レポート`,
            message:settings.message||report?.message||'',
            report,
            status:settings.scheduled_at?'scheduled':'draft',
            scheduled_at:settings.scheduled_at||null,
            created_by:settings.created_by??null
        });
        const update=(id,changes={})=>{
            const index=findIndex(id);
            if(index<0) throw new Error('配信データが見つかりません。');
            return save({...deliveries[index],...cloneValue(changes),id:deliveries[index].id,updated_at:now()});
        };
        const markSent=(id,result={})=>update(id,{
            status:'sent',sent_at:result.sent_at||now(),failed_at:null,failure_reason:null,
            provider_message_id:result.provider_message_id||null
        });
        const markFailed=(id,reason='送信に失敗しました。')=>update(id,{
            status:'failed',failed_at:now(),failure_reason:String(reason||'送信に失敗しました。')
        });
        const markRead=(id,guardianId,readAt=now())=>{
            const index=findIndex(id);
            if(index<0) throw new Error('配信データが見つかりません。');
            const readBy=(deliveries[index].read_by||[]).filter(item=>String(item.guardian_id)!==String(guardianId));
            readBy.push({guardian_id:guardianId,read_at:readAt});
            return update(id,{read_by:readBy});
        };
        const list=(filters={})=>cloneValue(deliveries.filter(item=>{
            if(filters.student_id!=null&&String(item.student_id)!==String(filters.student_id)) return false;
            if(filters.status&&item.status!==filters.status) return false;
            if(filters.channel&&item.channel!==filters.channel) return false;
            return true;
        }).sort((a,b)=>String(b.updated_at).localeCompare(String(a.updated_at))));
        const summary=()=>{
            const counts={draft:0,scheduled:0,sending:0,sent:0,failed:0,cancelled:0};
            deliveries.forEach(item=>{counts[item.status]=(counts[item.status]||0)+1;});
            return {
                total:deliveries.length,
                ...counts,
                unread_sent:deliveries.filter(item=>item.status==='sent'&&(!item.read_by||item.read_by.length===0)).length
            };
        };
        const toLaravelPayload=id=>{
            const index=findIndex(id);
            if(index<0) throw new Error('配信データが見つかりません。');
            const item=deliveries[index];
            return {
                delivery_id:item.id,
                report_id:item.report_id,
                student_id:item.student_id,
                guardian_ids:cloneValue(item.guardian_ids),
                channel:item.channel,
                subject:item.subject,
                message:item.message,
                scheduled_at:item.scheduled_at,
                report:cloneValue(item.report)
            };
        };
        restore();
        return {
            create,
            update,
            get:id=>cloneValue(deliveries[findIndex(id)]||null),
            list,
            remove:id=>{
                const index=findIndex(id); if(index<0) return false;
                const [removed]=deliveries.splice(index,1); persist(); emit('removed',removed); return true;
            },
            schedule:(id,scheduledAt)=>update(id,{status:'scheduled',scheduled_at:scheduledAt}),
            cancel:id=>update(id,{status:'cancelled'}),
            markSending:id=>update(id,{status:'sending'}),
            markSent,
            markFailed,
            markRead,
            summary,
            toLaravelPayload,
            restore,
            clear:()=>{deliveries=[];persist();emit('cleared',{});}
        };
    }


    /**
     * 保護者向け学習レポートの予約配信を実行するバッチ処理。
     * 実際の送信処理は sendDelivery に委譲し、管理画面・Laravel APIの
     * どちらからでも同じ状態遷移を利用できるようにする。
     */
    function createGuardianReportBatchProcessor(options={}){
        const manager=options.manager;
        if(!manager||typeof manager.list!=='function'||typeof manager.update!=='function'){
            throw new Error('有効な保護者レポート配信マネージャーが必要です。');
        }
        const sendDelivery=typeof options.sendDelivery==='function'
            ? options.sendDelivery
            : async payload=>({sent_at:new Date().toISOString(),provider_message_id:null,payload});
        const concurrency=Math.max(1,Math.min(10,Number(options.concurrency)||2));
        const maxAttempts=Math.max(1,Number(options.max_attempts)||3);
        const retryDelayMs=Math.max(0,Number(options.retry_delay_ms)||1500);
        const listeners=new Set();
        let running=false;
        let stopped=false;
        let lastRun=null;
        const attempts=new Map();
        const now=()=>new Date().toISOString();
        const emit=(type,detail={})=>{
            const event={type,detail:cloneValue(detail),at:now()};
            listeners.forEach(listener=>{try{listener(event);}catch(error){console.error(error);}});
            if(typeof window!=='undefined'&&typeof window.dispatchEvent==='function'){
                window.dispatchEvent(new CustomEvent(`lle-shogi:guardian-batch:${type}`,{detail:event.detail}));
            }
        };
        const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));
        const dueItems=(at=new Date())=>manager.list({status:'scheduled'}).filter(item=>{
            if(!item.scheduled_at) return true;
            const time=new Date(item.scheduled_at).getTime();
            return Number.isFinite(time)&&time<=at.getTime();
        });
        const processOne=async item=>{
            if(stopped) return {id:item.id,status:'stopped'};
            const count=(attempts.get(String(item.id))||0)+1;
            attempts.set(String(item.id),count);
            manager.markSending(item.id);
            emit('sending',{delivery_id:item.id,attempt:count});
            try{
                const payload=manager.toLaravelPayload(item.id);
                const result=await sendDelivery(cloneValue(payload),cloneValue(item));
                const sent=manager.markSent(item.id,result||{});
                attempts.delete(String(item.id));
                emit('sent',{delivery_id:item.id,result:sent});
                return {id:item.id,status:'sent',result:sent};
            }catch(error){
                const message=error instanceof Error?error.message:String(error||'送信に失敗しました。');
                if(count<maxAttempts&&!stopped){
                    manager.update(item.id,{status:'scheduled',failure_reason:message,failed_at:now()});
                    emit('retry',{delivery_id:item.id,attempt:count,error:message});
                    if(retryDelayMs>0) await sleep(retryDelayMs*count);
                    const latest=manager.get(item.id);
                    return latest?processOne(latest):{id:item.id,status:'removed'};
                }
                const failed=manager.markFailed(item.id,message);
                emit('failed',{delivery_id:item.id,attempt:count,error:message,result:failed});
                return {id:item.id,status:'failed',error:message,result:failed};
            }
        };
        const run=async(at=new Date())=>{
            if(running) return {running:true,processed:0,results:[]};
            running=true;
            stopped=false;
            const startedAt=now();
            const queue=dueItems(at);
            const results=[];
            let cursor=0;
            emit('started',{started_at:startedAt,target_count:queue.length});
            const worker=async()=>{
                while(!stopped){
                    const index=cursor++;
                    if(index>=queue.length) break;
                    results[index]=await processOne(queue[index]);
                }
            };
            try{
                await Promise.all(Array.from({length:Math.min(concurrency,Math.max(1,queue.length))},worker));
            }finally{
                running=false;
                lastRun={
                    started_at:startedAt,
                    completed_at:now(),
                    target_count:queue.length,
                    processed_count:results.filter(Boolean).length,
                    sent_count:results.filter(item=>item?.status==='sent').length,
                    failed_count:results.filter(item=>item?.status==='failed').length,
                    stopped
                };
                emit('completed',lastRun);
            }
            return {...cloneValue(lastRun),results:cloneValue(results.filter(Boolean))};
        };
        return {
            run,
            stop:()=>{stopped=true;emit('stopping',{});},
            isRunning:()=>running,
            getDue:at=>cloneValue(dueItems(at?new Date(at):new Date())),
            getStatus:()=>({
                running,
                stopped,
                due_count:dueItems().length,
                attempts:Object.fromEntries(attempts),
                last_run:cloneValue(lastRun)
            }),
            resetAttempts:()=>attempts.clear(),
            on:listener=>{if(typeof listener==='function') listeners.add(listener);return()=>listeners.delete(listener);}
        };
    }


    function createGuardianReportDeliveryAudit(options={}){
        const storageKey=String(options.storageKey||'lle_shogi_guardian_delivery_audit_v1');
        const maxRecords=Math.max(100,Number(options.maxRecords||5000));
        const listeners=new Set();
        let records=[];
        const now=()=>new Date().toISOString();
        const emit=(type,payload={})=>{
            const detail={type,at:now(),...cloneValue(payload)};
            listeners.forEach(listener=>{try{listener(detail);}catch(error){console.error(error);}});
            if(typeof window!=='undefined'&&typeof window.dispatchEvent==='function'){
                window.dispatchEvent(new CustomEvent('lle:shogi:guardian-delivery-audit',{detail}));
            }
        };
        const save=()=>{
            try{localStorage.setItem(storageKey,JSON.stringify(records.slice(-maxRecords)));}catch(error){/* noop */}
        };
        const load=()=>{
            try{
                const raw=JSON.parse(localStorage.getItem(storageKey)||'[]');
                records=Array.isArray(raw)?raw.slice(-maxRecords):[];
            }catch(error){records=[];}
            return cloneValue(records);
        };
        const normalizeRecipient=recipient=>({
            guardian_id:String(recipient?.guardian_id||recipient?.id||''),
            channel:String(recipient?.channel||'in_app'),
            status:String(recipient?.status||'pending'),
            sent_at:recipient?.sent_at||null,
            read_at:recipient?.read_at||null,
            failed_at:recipient?.failed_at||null,
            failure_reason:String(recipient?.failure_reason||''),
            provider_message_id:String(recipient?.provider_message_id||''),
            attempt_count:Math.max(0,Number(recipient?.attempt_count||0))
        });
        const recordDelivery=(delivery,result={})=>{
            const recipients=(result.recipients||delivery.recipients||delivery.guardian_ids||[]).map(item=>{
                if(typeof item==='string'||typeof item==='number') return normalizeRecipient({guardian_id:item,status:result.status||delivery.status||'sent'});
                return normalizeRecipient(item);
            });
            const item={
                audit_id:String(result.audit_id||`audit_${Date.now()}_${Math.random().toString(36).slice(2,8)}`),
                delivery_id:String(delivery.id||delivery.delivery_id||''),
                report_id:String(delivery.report_id||''),
                student_id:String(delivery.student_id||''),
                channel:String(delivery.channel||delivery.delivery_channel||'in_app'),
                status:String(result.status||delivery.status||'sent'),
                scheduled_at:delivery.scheduled_at||null,
                started_at:result.started_at||delivery.started_at||null,
                completed_at:result.completed_at||result.sent_at||delivery.sent_at||now(),
                failure_reason:String(result.failure_reason||delivery.failure_reason||''),
                batch_id:String(result.batch_id||delivery.batch_id||''),
                recipients,
                metadata:cloneValue(result.metadata||delivery.metadata||{}),
                created_at:now()
            };
            records.push(item);
            if(records.length>maxRecords) records=records.slice(-maxRecords);
            save();
            emit('recorded',{record:item});
            return cloneValue(item);
        };
        const updateRecipient=(auditId,guardianId,patch={})=>{
            const item=records.find(row=>String(row.audit_id)===String(auditId));
            if(!item) throw new Error('配信監査レコードが見つかりません。');
            const recipient=item.recipients.find(row=>String(row.guardian_id)===String(guardianId));
            if(!recipient) throw new Error('保護者別配信結果が見つかりません。');
            Object.assign(recipient,normalizeRecipient({...recipient,...patch}));
            save();
            emit('recipient-updated',{audit_id:item.audit_id,guardian_id:String(guardianId),recipient});
            return cloneValue(recipient);
        };
        const list=(filters={})=>records.filter(item=>{
            if(filters.delivery_id&&String(item.delivery_id)!==String(filters.delivery_id)) return false;
            if(filters.report_id&&String(item.report_id)!==String(filters.report_id)) return false;
            if(filters.student_id&&String(item.student_id)!==String(filters.student_id)) return false;
            if(filters.status&&String(item.status)!==String(filters.status)) return false;
            if(filters.channel&&String(item.channel)!==String(filters.channel)) return false;
            if(filters.batch_id&&String(item.batch_id)!==String(filters.batch_id)) return false;
            if(filters.from&&new Date(item.created_at)<new Date(filters.from)) return false;
            if(filters.to&&new Date(item.created_at)>new Date(filters.to)) return false;
            return true;
        }).map(cloneValue);
        const summarize=(filters={})=>{
            const target=list(filters);
            const recipients=target.flatMap(item=>item.recipients||[]);
            const byStatus={};
            target.forEach(item=>{byStatus[item.status]=(byStatus[item.status]||0)+1;});
            const recipientStatus={};
            recipients.forEach(item=>{recipientStatus[item.status]=(recipientStatus[item.status]||0)+1;});
            return {
                delivery_count:target.length,
                sent_count:target.filter(item=>item.status==='sent').length,
                failed_count:target.filter(item=>item.status==='failed').length,
                recipient_count:recipients.length,
                read_count:recipients.filter(item=>Boolean(item.read_at)).length,
                unread_count:recipients.filter(item=>item.status==='sent'&&!item.read_at).length,
                failed_recipient_count:recipients.filter(item=>item.status==='failed').length,
                read_rate:recipients.length?Math.round(recipients.filter(item=>Boolean(item.read_at)).length/recipients.length*1000)/10:0,
                by_status:byStatus,
                recipient_by_status:recipientStatus
            };
        };
        const retryCandidates=(filters={})=>list(filters).flatMap(item=>(item.recipients||[])
            .filter(recipient=>recipient.status==='failed')
            .map(recipient=>({
                audit_id:item.audit_id,
                delivery_id:item.delivery_id,
                report_id:item.report_id,
                student_id:item.student_id,
                guardian_id:recipient.guardian_id,
                channel:recipient.channel,
                attempt_count:recipient.attempt_count,
                failure_reason:recipient.failure_reason
            })));
        const exportCsv=(filters={})=>{
            const rows=[['監査ID','配信ID','レポートID','生徒ID','保護者ID','チャネル','状態','送信日時','既読日時','失敗理由','試行回数']];
            list(filters).forEach(item=>(item.recipients||[]).forEach(recipient=>rows.push([
                item.audit_id,item.delivery_id,item.report_id,item.student_id,recipient.guardian_id,recipient.channel,
                recipient.status,recipient.sent_at||'',recipient.read_at||'',recipient.failure_reason||'',recipient.attempt_count
            ])));
            return rows.map(row=>row.map(value=>`"${String(value??'').replace(/"/g,'""')}"`).join(',')).join('\n');
        };
        load();
        return {
            recordDelivery,
            updateRecipient,
            markRead:(auditId,guardianId,readAt=now())=>updateRecipient(auditId,guardianId,{status:'sent',read_at:readAt}),
            markFailed:(auditId,guardianId,reason='')=>updateRecipient(auditId,guardianId,{status:'failed',failed_at:now(),failure_reason:reason}),
            list,
            summarize,
            retryCandidates,
            exportCsv,
            clear:()=>{records=[];save();emit('cleared',{});},
            reload:load,
            on:listener=>{if(typeof listener==='function') listeners.add(listener);return()=>listeners.delete(listener);}
        };
    }


    function createGuardianReportDeliveryDashboard(options={}){
        const audit=options.audit;
        if(!audit||typeof audit.list!=='function'||typeof audit.summarize!=='function'){
            throw new Error('配信監査インスタンスが必要です。');
        }
        const nowProvider=typeof options.now==='function'?options.now:()=>new Date();
        const dayKey=value=>{
            const date=new Date(value);
            if(Number.isNaN(date.getTime())) return '';
            const y=date.getFullYear();
            const m=String(date.getMonth()+1).padStart(2,'0');
            const d=String(date.getDate()).padStart(2,'0');
            return `${y}-${m}-${d}`;
        };
        const round=value=>Math.round(Number(value||0)*10)/10;
        const percent=(num,den)=>den?round(num/den*100):0;
        const build=(filters={})=>{
            const records=audit.list(filters);
            const summary=audit.summarize(filters);
            const recipients=records.flatMap(record=>(record.recipients||[]).map(recipient=>({
                ...recipient,
                audit_id:record.audit_id,
                delivery_id:record.delivery_id,
                report_id:record.report_id,
                student_id:record.student_id,
                delivery_status:record.status,
                delivery_channel:record.channel,
                created_at:record.created_at
            })));
            const channelMap={};
            const failureMap={};
            const dailyMap={};
            recipients.forEach(recipient=>{
                const channel=String(recipient.channel||recipient.delivery_channel||'unknown');
                if(!channelMap[channel]) channelMap[channel]={channel,total:0,sent:0,failed:0,read:0,unread:0};
                const c=channelMap[channel];
                c.total+=1;
                if(recipient.status==='sent') c.sent+=1;
                if(recipient.status==='failed') c.failed+=1;
                if(recipient.read_at) c.read+=1;
                if(recipient.status==='sent'&&!recipient.read_at) c.unread+=1;
                if(recipient.status==='failed'){
                    const reason=String(recipient.failure_reason||'理由未登録');
                    failureMap[reason]=(failureMap[reason]||0)+1;
                }
                const key=dayKey(recipient.sent_at||recipient.failed_at||recipient.created_at);
                if(key){
                    if(!dailyMap[key]) dailyMap[key]={date:key,total:0,sent:0,failed:0,read:0};
                    dailyMap[key].total+=1;
                    if(recipient.status==='sent') dailyMap[key].sent+=1;
                    if(recipient.status==='failed') dailyMap[key].failed+=1;
                    if(recipient.read_at) dailyMap[key].read+=1;
                }
            });
            const channels=Object.values(channelMap).map(item=>({
                ...item,
                delivery_rate:percent(item.sent,item.total),
                failure_rate:percent(item.failed,item.total),
                read_rate:percent(item.read,item.sent)
            })).sort((a,b)=>b.total-a.total);
            const failures=Object.entries(failureMap).map(([reason,count])=>({reason,count})).sort((a,b)=>b.count-a.count);
            const daily=Object.values(dailyMap).sort((a,b)=>a.date.localeCompare(b.date)).map(item=>({
                ...item,
                delivery_rate:percent(item.sent,item.total),
                read_rate:percent(item.read,item.sent)
            }));
            const now=nowProvider();
            const unreadAging={within_24h:0,days_1_3:0,days_4_7:0,over_7d:0,unknown:0};
            recipients.filter(item=>item.status==='sent'&&!item.read_at).forEach(item=>{
                const sent=new Date(item.sent_at||item.created_at||'');
                if(Number.isNaN(sent.getTime())){unreadAging.unknown+=1;return;}
                const hours=(now.getTime()-sent.getTime())/3600000;
                if(hours<24) unreadAging.within_24h+=1;
                else if(hours<96) unreadAging.days_1_3+=1;
                else if(hours<192) unreadAging.days_4_7+=1;
                else unreadAging.over_7d+=1;
            });
            const attention={
                failed_recipients:recipients.filter(item=>item.status==='failed').map(item=>({
                    audit_id:item.audit_id,delivery_id:item.delivery_id,guardian_id:item.guardian_id,
                    student_id:item.student_id,channel:item.channel,failure_reason:item.failure_reason||'',attempt_count:Number(item.attempt_count||0)
                })),
                long_unread:recipients.filter(item=>{
                    if(item.status!=='sent'||item.read_at) return false;
                    const sent=new Date(item.sent_at||item.created_at||'');
                    return !Number.isNaN(sent.getTime())&&((now.getTime()-sent.getTime())/86400000)>=7;
                }).map(item=>({audit_id:item.audit_id,delivery_id:item.delivery_id,guardian_id:item.guardian_id,student_id:item.student_id,sent_at:item.sent_at||item.created_at}))
            };
            return {
                generated_at:now.toISOString(),
                filters:cloneValue(filters),
                overview:{
                    delivery_count:summary.delivery_count,
                    recipient_count:summary.recipient_count,
                    sent_count:summary.sent_count,
                    failed_count:summary.failed_count,
                    read_count:summary.read_count,
                    unread_count:summary.unread_count,
                    delivery_rate:percent(summary.sent_count,summary.delivery_count),
                    recipient_success_rate:percent(summary.recipient_count-summary.failed_recipient_count,summary.recipient_count),
                    read_rate:percent(summary.read_count,summary.sent_count||summary.recipient_count)
                },
                channels,
                failures,
                daily,
                unread_aging:unreadAging,
                attention
            };
        };
        const exportForLaravel=(filters={})=>{
            const dashboard=build(filters);
            return {
                generated_at:dashboard.generated_at,
                overview:dashboard.overview,
                channel_breakdown:dashboard.channels,
                failure_breakdown:dashboard.failures,
                daily_trend:dashboard.daily,
                unread_aging:dashboard.unread_aging,
                attention:dashboard.attention
            };
        };
        return {build,exportForLaravel};
    }


    function createGuardianReportDeliveryAlertMonitor(options={}){
        const dashboard=options.dashboard;
        if(!dashboard||typeof dashboard.build!=='function'){
            throw new Error('配信ダッシュボードインスタンスが必要です。');
        }
        const nowProvider=typeof options.now==='function'?options.now:()=>new Date();
        const storage=options.storage||window.localStorage;
        const storageKey=String(options.storageKey||'lle_shogi_guardian_delivery_alerts_v1');
        const thresholds={
            failure_rate:Number(options.failureRateThreshold??10),
            read_rate:Number(options.readRateThreshold??50),
            long_unread:Number(options.longUnreadThreshold??1),
            failed_recipients:Number(options.failedRecipientThreshold??1)
        };
        let alerts=[];
        const listeners=new Set();
        const clone=value=>deepClone(value);
        const now=()=>nowProvider().toISOString();
        const emit=(type,payload={})=>listeners.forEach(listener=>listener({type,payload:clone(payload),at:now()}));
        const save=()=>{
            try{storage?.setItem(storageKey,JSON.stringify(alerts));}catch(error){emit('storage_error',{message:error.message});}
        };
        const load=()=>{
            try{
                const raw=storage?.getItem(storageKey);
                const parsed=raw?JSON.parse(raw):[];
                alerts=Array.isArray(parsed)?parsed:[];
            }catch(error){alerts=[];emit('storage_error',{message:error.message});}
            return clone(alerts);
        };
        const fingerprint=(type,scope)=>`${type}:${String(scope||'global')}`;
        const upsert=(type,severity,title,message,details={},scope='global')=>{
            const key=fingerprint(type,scope);
            const existing=alerts.find(item=>item.fingerprint===key&&item.status!=='resolved');
            if(existing){
                Object.assign(existing,{severity,title,message,details:clone(details),last_detected_at:now(),occurrence_count:Number(existing.occurrence_count||1)+1});
                save();emit('updated',existing);return clone(existing);
            }
            const alert={
                alert_id:`gda_${Date.now()}_${Math.random().toString(36).slice(2,8)}`,
                fingerprint:key,type,scope:String(scope||'global'),severity,title,message,details:clone(details),
                status:'open',created_at:now(),last_detected_at:now(),occurrence_count:1,
                acknowledged_at:null,acknowledged_by:null,resolved_at:null,resolved_by:null,resolution_note:''
            };
            alerts.unshift(alert);save();emit('created',alert);return clone(alert);
        };
        const evaluate=(filters={})=>{
            const data=dashboard.build(filters);
            const detected=[];
            const recipientTotal=Number(data.overview?.recipient_count||0);
            const failed=Number(data.overview?.failed_count||0);
            const failureRate=recipientTotal?Math.round(failed/recipientTotal*1000)/10:0;
            const readRate=Number(data.overview?.read_rate||0);
            const longUnread=Number(data.unread_aging?.over_7d||0);
            const failedRecipients=Number(data.attention?.failed_recipients?.length||0);
            if(failureRate>=thresholds.failure_rate&&recipientTotal>0){
                detected.push(upsert('high_failure_rate',failureRate>=25?'high':'medium','保護者レポートの配信失敗率が高くなっています',`配信失敗率は${failureRate}%です。`,{failure_rate:failureRate,failed_count:failed,recipient_count:recipientTotal,filters},'global'));
            }
            if(readRate<thresholds.read_rate&&Number(data.overview?.sent_count||0)>0){
                detected.push(upsert('low_read_rate',readRate<25?'high':'medium','保護者レポートの既読率が低下しています',`現在の既読率は${readRate}%です。`,{read_rate:readRate,sent_count:data.overview.sent_count,unread_count:data.overview.unread_count,filters},'global'));
            }
            if(longUnread>=thresholds.long_unread){
                detected.push(upsert('long_unread',longUnread>=10?'high':'medium','7日以上未読の保護者レポートがあります',`${longUnread}件が7日以上未読です。`,{count:longUnread,targets:clone(data.attention?.long_unread||[]),filters},'global'));
            }
            if(failedRecipients>=thresholds.failed_recipients){
                detected.push(upsert('failed_recipients',failedRecipients>=10?'high':'medium','再送確認が必要な配信があります',`${failedRecipients}件の配信失敗を確認してください。`,{count:failedRecipients,targets:clone(data.attention?.failed_recipients||[]),filters},'global'));
            }
            (data.channels||[]).forEach(channel=>{
                if(Number(channel.failure_rate||0)>=thresholds.failure_rate&&Number(channel.total||0)>0){
                    detected.push(upsert('channel_failure_rate',Number(channel.failure_rate)>=25?'high':'medium',`${channel.channel}の配信失敗率が高くなっています`,`${channel.channel}の失敗率は${channel.failure_rate}%です。`,clone(channel),channel.channel));
                }
            });
            emit('evaluated',{detected_count:detected.length,filters,data});
            return {evaluated_at:now(),detected,dashboard:data};
        };
        const list=(filters={})=>alerts.filter(item=>{
            if(filters.status&&item.status!==filters.status) return false;
            if(filters.severity&&item.severity!==filters.severity) return false;
            if(filters.type&&item.type!==filters.type) return false;
            return true;
        }).map(clone);
        const updateStatus=(alertId,status,userId='',note='')=>{
            const item=alerts.find(alert=>alert.alert_id===alertId);
            if(!item) throw new Error('対象のアラートが見つかりません。');
            item.status=status;
            if(status==='acknowledged'){
                item.acknowledged_at=now();item.acknowledged_by=String(userId||'');
            }
            if(status==='resolved'){
                item.resolved_at=now();item.resolved_by=String(userId||'');item.resolution_note=String(note||'');
            }
            save();emit('status_changed',item);return clone(item);
        };
        const summarize=()=>({
            total:alerts.length,
            open:alerts.filter(item=>item.status==='open').length,
            acknowledged:alerts.filter(item=>item.status==='acknowledged').length,
            resolved:alerts.filter(item=>item.status==='resolved').length,
            high:alerts.filter(item=>item.status!=='resolved'&&item.severity==='high').length,
            medium:alerts.filter(item=>item.status!=='resolved'&&item.severity==='medium').length
        });
        const exportForLaravel=()=>({generated_at:now(),thresholds:clone(thresholds),summary:summarize(),alerts:list()});
        load();
        return {
            evaluate,list,summarize,exportForLaravel,
            acknowledge:(alertId,userId='')=>updateStatus(alertId,'acknowledged',userId),
            resolve:(alertId,userId='',note='')=>updateStatus(alertId,'resolved',userId,note),
            reopen:(alertId)=>updateStatus(alertId,'open'),
            clearResolved:()=>{alerts=alerts.filter(item=>item.status!=='resolved');save();emit('resolved_cleared',{});},
            reload:load,
            on:listener=>{if(typeof listener==='function') listeners.add(listener);return()=>listeners.delete(listener);}
        };
    }


    function createGuardianDeliveryAlertActionManager(options={}){
        const monitor=options.monitor;
        if(!monitor||typeof monitor.list!=='function'){
            throw new Error('配信アラート監視インスタンスが必要です。');
        }
        const nowProvider=typeof options.now==='function'?options.now:()=>new Date();
        const storage=options.storage||window.localStorage;
        const storageKey=String(options.storageKey||'lle_shogi_guardian_delivery_alert_actions_v1');
        const defaultDueHours={high:Number(options.highDueHours??24),medium:Number(options.mediumDueHours??72),low:Number(options.lowDueHours??168)};
        let actions=[];
        const listeners=new Set();
        const clone=value=>deepClone(value);
        const nowDate=()=>nowProvider();
        const now=()=>nowDate().toISOString();
        const emit=(type,payload={})=>listeners.forEach(listener=>listener({type,payload:clone(payload),at:now()}));
        const save=()=>{
            try{storage?.setItem(storageKey,JSON.stringify(actions));}
            catch(error){emit('storage_error',{message:error.message});}
        };
        const load=()=>{
            try{
                const raw=storage?.getItem(storageKey);
                const parsed=raw?JSON.parse(raw):[];
                actions=Array.isArray(parsed)?parsed:[];
            }catch(error){actions=[];emit('storage_error',{message:error.message});}
            return clone(actions);
        };
        const addHours=(iso,hours)=>{
            const date=new Date(iso);
            date.setHours(date.getHours()+Number(hours||0));
            return date.toISOString();
        };
        const buildRecommendedSteps=alert=>{
            const common=['対象データを確認する','原因を特定する','必要な対応を実施する','対応結果を記録する'];
            const byType={
                high_failure_rate:['配信失敗の内訳を確認する','メールアドレス・通知先情報を確認する','失敗対象を再送する','配信チャネル設定を見直す'],
                channel_failure_rate:['該当チャネルの障害状況を確認する','認証情報・送信設定を確認する','代替チャネルでの配信を検討する'],
                low_read_rate:['未読対象を抽出する','保護者への案内文を確認する','再通知または講師からの声かけを行う'],
                long_unread:['長期未読の保護者を確認する','連絡先と利用状況を確認する','必要に応じて個別連絡する'],
                failed_recipients:['失敗対象ごとの理由を確認する','修正可能な宛先情報を更新する','対象レポートを再送する']
            };
            return clone(byType[alert.type]||common);
        };
        const createFromAlert=(alertId,input={})=>{
            const alert=monitor.list().find(item=>item.alert_id===alertId);
            if(!alert) throw new Error('対象のアラートが見つかりません。');
            const existing=actions.find(item=>item.alert_id===alertId&&item.status!=='cancelled'&&item.status!=='completed');
            if(existing) return clone(existing);
            const createdAt=now();
            const severity=String(input.severity||alert.severity||'medium');
            const dueAt=input.due_at||addHours(createdAt,defaultDueHours[severity]??72);
            const action={
                action_id:`gdaa_${Date.now()}_${Math.random().toString(36).slice(2,8)}`,
                alert_id:alert.alert_id,
                alert_type:alert.type,
                title:String(input.title||alert.title||'配信アラート対応'),
                description:String(input.description||alert.message||''),
                severity,
                status:'open',
                assignee_id:String(input.assignee_id||''),
                assignee_name:String(input.assignee_name||''),
                due_at:dueAt,
                recommended_steps:Array.isArray(input.recommended_steps)?clone(input.recommended_steps):buildRecommendedSteps(alert),
                checklist:[],
                notes:[],
                escalation_level:0,
                escalation_history:[],
                created_at:createdAt,
                updated_at:createdAt,
                started_at:null,
                completed_at:null,
                completed_by:null,
                completion_note:'',
                cancelled_at:null,
                cancelled_by:null,
                cancel_reason:''
            };
            actions.unshift(action);save();emit('created',action);return clone(action);
        };
        const generateFromOpenAlerts=()=>{
            const created=[];
            monitor.list().filter(alert=>alert.status!=='resolved').forEach(alert=>{
                const before=actions.length;
                const action=createFromAlert(alert.alert_id);
                if(actions.length>before) created.push(action);
            });
            emit('generated',{created_count:created.length});
            return created;
        };
        const getMutable=actionId=>{
            const item=actions.find(action=>action.action_id===actionId);
            if(!item) throw new Error('対象の対応タスクが見つかりません。');
            return item;
        };
        const touch=item=>{item.updated_at=now();save();};
        const assign=(actionId,userId,userName='')=>{
            const item=getMutable(actionId);
            item.assignee_id=String(userId||'');item.assignee_name=String(userName||'');
            touch(item);emit('assigned',item);return clone(item);
        };
        const start=(actionId,userId='')=>{
            const item=getMutable(actionId);
            item.status='in_progress';item.started_at=item.started_at||now();
            if(userId&&!item.assignee_id) item.assignee_id=String(userId);
            touch(item);emit('started',item);return clone(item);
        };
        const addChecklistItem=(actionId,label)=>{
            const item=getMutable(actionId);
            const checklistItem={id:`chk_${Date.now()}_${Math.random().toString(36).slice(2,7)}`,label:String(label||''),completed:false,completed_at:null};
            item.checklist.push(checklistItem);touch(item);emit('checklist_added',{action:item,item:checklistItem});return clone(checklistItem);
        };
        const setChecklistCompleted=(actionId,itemId,completed=true)=>{
            const item=getMutable(actionId);
            const target=item.checklist.find(entry=>entry.id===itemId);
            if(!target) throw new Error('対象のチェック項目が見つかりません。');
            target.completed=Boolean(completed);target.completed_at=target.completed?now():null;
            touch(item);emit('checklist_changed',{action:item,item:target});return clone(target);
        };
        const addNote=(actionId,text,userId='')=>{
            const item=getMutable(actionId);
            const note={note_id:`note_${Date.now()}_${Math.random().toString(36).slice(2,7)}`,text:String(text||''),user_id:String(userId||''),created_at:now()};
            item.notes.push(note);touch(item);emit('note_added',{action:item,note});return clone(note);
        };
        const complete=(actionId,userId='',note='')=>{
            const item=getMutable(actionId);
            item.status='completed';item.completed_at=now();item.completed_by=String(userId||'');item.completion_note=String(note||'');
            touch(item);emit('completed',item);return clone(item);
        };
        const cancel=(actionId,userId='',reason='')=>{
            const item=getMutable(actionId);
            item.status='cancelled';item.cancelled_at=now();item.cancelled_by=String(userId||'');item.cancel_reason=String(reason||'');
            touch(item);emit('cancelled',item);return clone(item);
        };
        const escalate=(actionId,reason='',toUserId='')=>{
            const item=getMutable(actionId);
            item.escalation_level=Number(item.escalation_level||0)+1;
            const entry={level:item.escalation_level,reason:String(reason||''),to_user_id:String(toUserId||''),escalated_at:now()};
            item.escalation_history.push(entry);
            if(toUserId) item.assignee_id=String(toUserId);
            if(item.status==='open') item.status='in_progress';
            touch(item);emit('escalated',{action:item,entry});return clone(item);
        };
        const evaluateOverdue=()=>{
            const current=nowDate().getTime();
            const overdue=[];
            actions.forEach(item=>{
                if(['completed','cancelled'].includes(item.status)||!item.due_at) return;
                if(new Date(item.due_at).getTime()<current) overdue.push(clone(item));
            });
            emit('overdue_evaluated',{count:overdue.length});
            return overdue;
        };
        const list=(filters={})=>actions.filter(item=>{
            if(filters.status&&item.status!==filters.status) return false;
            if(filters.severity&&item.severity!==filters.severity) return false;
            if(filters.assignee_id&&item.assignee_id!==String(filters.assignee_id)) return false;
            if(filters.alert_type&&item.alert_type!==filters.alert_type) return false;
            if(filters.overdue){
                if(['completed','cancelled'].includes(item.status)||!item.due_at||new Date(item.due_at).getTime()>=nowDate().getTime()) return false;
            }
            return true;
        }).map(clone);
        const summarize=()=>{
            const overdue=evaluateOverdue();
            return {
                total:actions.length,
                open:actions.filter(item=>item.status==='open').length,
                in_progress:actions.filter(item=>item.status==='in_progress').length,
                completed:actions.filter(item=>item.status==='completed').length,
                cancelled:actions.filter(item=>item.status==='cancelled').length,
                overdue:overdue.length,
                high:actions.filter(item=>!['completed','cancelled'].includes(item.status)&&item.severity==='high').length,
                unassigned:actions.filter(item=>!['completed','cancelled'].includes(item.status)&&!item.assignee_id).length
            };
        };
        const exportForLaravel=()=>({generated_at:now(),summary:summarize(),actions:list()});
        load();
        return {
            createFromAlert,generateFromOpenAlerts,list,summarize,evaluateOverdue,assign,start,
            addChecklistItem,setChecklistCompleted,addNote,complete,cancel,escalate,exportForLaravel,reload:load,
            on:listener=>{if(typeof listener==='function') listeners.add(listener);return()=>listeners.delete(listener);}
        };
    }


    function createGuardianDeliveryOperationsDashboard(options={}){
        const actionManager=options.actionManager;
        if(!actionManager||typeof actionManager.list!=='function'||typeof actionManager.summarize!=='function'){
            throw new Error('actionManagerが必要です。');
        }
        const nowProvider=typeof options.nowProvider==='function'?options.nowProvider:()=>new Date();
        const clone=value=>deepClone(value);
        const toTime=value=>{const time=new Date(value||0).getTime();return Number.isFinite(time)?time:0;};
        const hoursBetween=(from,to)=>Math.max(0,(toTime(to)-toTime(from))/3600000);
        const dayKey=value=>{
            const date=new Date(value);
            if(Number.isNaN(date.getTime())) return '';
            const y=date.getFullYear();
            const m=String(date.getMonth()+1).padStart(2,'0');
            const d=String(date.getDate()).padStart(2,'0');
            return `${y}-${m}-${d}`;
        };
        const build=(filters={})=>{
            const all=actionManager.list(filters);
            const current=nowProvider();
            const active=all.filter(item=>!['completed','cancelled'].includes(item.status));
            const completed=all.filter(item=>item.status==='completed');
            const overdue=active.filter(item=>item.due_at&&toTime(item.due_at)<current.getTime());
            const resolutionHours=completed
                .map(item=>hoursBetween(item.created_at,item.completed_at))
                .filter(value=>Number.isFinite(value));
            const averageResolutionHours=resolutionHours.length
                ? Math.round((resolutionHours.reduce((sum,value)=>sum+value,0)/resolutionHours.length)*10)/10
                : 0;
            const completionRate=all.length?Math.round((completed.length/all.length)*1000)/10:0;
            const slaMet=completed.filter(item=>!item.due_at||toTime(item.completed_at)<=toTime(item.due_at)).length;
            const slaRate=completed.length?Math.round((slaMet/completed.length)*1000)/10:0;
            const byAssigneeMap=new Map();
            active.forEach(item=>{
                const key=item.assignee_id||'unassigned';
                if(!byAssigneeMap.has(key)) byAssigneeMap.set(key,{assignee_id:key,assignee_name:item.assignee_name||'未担当',open:0,in_progress:0,overdue:0,high:0,total:0});
                const row=byAssigneeMap.get(key);
                row.total+=1;
                if(item.status==='open') row.open+=1;
                if(item.status==='in_progress') row.in_progress+=1;
                if(item.due_at&&toTime(item.due_at)<current.getTime()) row.overdue+=1;
                if(item.severity==='high') row.high+=1;
            });
            const workload=[...byAssigneeMap.values()].sort((a,b)=>b.overdue-a.overdue||b.high-a.high||b.total-a.total);
            const byTypeMap=new Map();
            all.forEach(item=>{
                const key=item.alert_type||'unknown';
                if(!byTypeMap.has(key)) byTypeMap.set(key,{alert_type:key,total:0,active:0,completed:0,overdue:0});
                const row=byTypeMap.get(key);row.total+=1;
                if(['completed','cancelled'].includes(item.status)){if(item.status==='completed') row.completed+=1;}
                else row.active+=1;
                if(!['completed','cancelled'].includes(item.status)&&item.due_at&&toTime(item.due_at)<current.getTime()) row.overdue+=1;
            });
            const byType=[...byTypeMap.values()].sort((a,b)=>b.active-a.active||b.total-a.total);
            const dailyMap=new Map();
            all.forEach(item=>{
                const created=dayKey(item.created_at);
                if(created){
                    if(!dailyMap.has(created)) dailyMap.set(created,{date:created,created:0,completed:0});
                    dailyMap.get(created).created+=1;
                }
                const done=dayKey(item.completed_at);
                if(done){
                    if(!dailyMap.has(done)) dailyMap.set(done,{date:done,created:0,completed:0});
                    dailyMap.get(done).completed+=1;
                }
            });
            const dailyTrend=[...dailyMap.values()].sort((a,b)=>a.date.localeCompare(b.date));
            const urgent=active
                .slice()
                .sort((a,b)=>{
                    const aOver=a.due_at&&toTime(a.due_at)<current.getTime()?1:0;
                    const bOver=b.due_at&&toTime(b.due_at)<current.getTime()?1:0;
                    const severity={high:3,medium:2,low:1};
                    return bOver-aOver||(severity[b.severity]||0)-(severity[a.severity]||0)||toTime(a.due_at)-toTime(b.due_at);
                })
                .slice(0,20)
                .map(item=>({...clone(item),is_overdue:Boolean(item.due_at&&toTime(item.due_at)<current.getTime())}));
            return {
                generated_at:current.toISOString(),
                summary:{
                    total:all.length,
                    active:active.length,
                    completed:completed.length,
                    overdue:overdue.length,
                    high_priority:active.filter(item=>item.severity==='high').length,
                    unassigned:active.filter(item=>!item.assignee_id).length,
                    completion_rate:completionRate,
                    sla_rate:slaRate,
                    average_resolution_hours:averageResolutionHours
                },
                workload,
                by_alert_type:byType,
                daily_trend:dailyTrend,
                urgent_actions:urgent
            };
        };
        return {
            build,
            exportForLaravel:filters=>clone(build(filters))
        };
    }


    function createGuardianDeliveryOperationsReport(options={}){
        const dashboard=options.dashboard;
        if(!dashboard||typeof dashboard.build!=='function'){
            throw new Error('dashboardが必要です。');
        }
        const storage=options.storage||window.localStorage;
        const storageKey=String(options.storageKey||'lle_shogi_guardian_delivery_operations_reports');
        const nowProvider=typeof options.nowProvider==='function'?options.nowProvider:()=>new Date();
        const clone=value=>deepClone(value);
        const safeParse=value=>{try{return JSON.parse(value);}catch(_error){return null;}};
        let snapshots=[];
        const load=()=>{
            const parsed=safeParse(storage&&storage.getItem?storage.getItem(storageKey):null);
            snapshots=Array.isArray(parsed)?parsed:[];
            return snapshots.map(clone);
        };
        const save=()=>{
            if(storage&&storage.setItem) storage.setItem(storageKey,JSON.stringify(snapshots));
        };
        const round=value=>Math.round((Number(value)||0)*10)/10;
        const createSnapshot=(label='',filters={})=>{
            const report=dashboard.build(filters);
            const snapshot={
                snapshot_id:`ops_${Date.now()}_${Math.random().toString(36).slice(2,7)}`,
                label:String(label||''),
                created_at:nowProvider().toISOString(),
                filters:clone(filters),
                report:clone(report)
            };
            snapshots.push(snapshot);
            if(snapshots.length>52) snapshots=snapshots.slice(-52);
            save();
            return clone(snapshot);
        };
        const list=()=>snapshots.map(clone).sort((a,b)=>String(b.created_at).localeCompare(String(a.created_at)));
        const remove=snapshotId=>{
            const before=snapshots.length;
            snapshots=snapshots.filter(item=>item.snapshot_id!==snapshotId);
            if(before!==snapshots.length) save();
            return before!==snapshots.length;
        };
        const clear=()=>{snapshots=[];save();};
        const compare=(olderId,newerId)=>{
            const older=snapshots.find(item=>item.snapshot_id===olderId);
            const newer=snapshots.find(item=>item.snapshot_id===newerId);
            if(!older||!newer) throw new Error('比較対象のスナップショットが見つかりません。');
            const a=older.report.summary||{};
            const b=newer.report.summary||{};
            const delta=(key)=>round((Number(b[key])||0)-(Number(a[key])||0));
            return {
                older:clone(older),
                newer:clone(newer),
                delta:{
                    total:delta('total'),
                    active:delta('active'),
                    completed:delta('completed'),
                    overdue:delta('overdue'),
                    high_priority:delta('high_priority'),
                    unassigned:delta('unassigned'),
                    completion_rate:delta('completion_rate'),
                    sla_rate:delta('sla_rate'),
                    average_resolution_hours:delta('average_resolution_hours')
                }
            };
        };
        const buildWeeklyReport=(filters={})=>{
            const current=dashboard.build(filters);
            const previous=list()[0]||null;
            const summary=current.summary||{};
            const recommendations=[];
            if((summary.overdue||0)>0) recommendations.push('期限超過タスクを優先して解消してください。');
            if((summary.unassigned||0)>0) recommendations.push('未担当タスクへ担当者を割り当ててください。');
            if((summary.sla_rate||0)<90) recommendations.push('期限内完了率が90%未満です。対応フローを確認してください。');
            if((summary.average_resolution_hours||0)>48) recommendations.push('平均対応時間が48時間を超えています。');
            if(!recommendations.length) recommendations.push('重大な運用課題は検出されていません。');
            return {
                generated_at:nowProvider().toISOString(),
                period_label:String(options.periodLabel||'週次'),
                summary:clone(summary),
                workload:clone(current.workload||[]),
                by_alert_type:clone(current.by_alert_type||[]),
                daily_trend:clone(current.daily_trend||[]),
                urgent_actions:clone(current.urgent_actions||[]),
                previous_snapshot:previous,
                recommendations
            };
        };
        const toCsv=(filters={})=>{
            const report=buildWeeklyReport(filters);
            const rows=[
                ['項目','値'],
                ['総タスク数',report.summary.total||0],
                ['未完了数',report.summary.active||0],
                ['完了数',report.summary.completed||0],
                ['期限超過数',report.summary.overdue||0],
                ['高重要度数',report.summary.high_priority||0],
                ['未担当数',report.summary.unassigned||0],
                ['完了率',`${report.summary.completion_rate||0}%`],
                ['期限内完了率',`${report.summary.sla_rate||0}%`],
                ['平均対応時間',`${report.summary.average_resolution_hours||0}時間`]
            ];
            const escape=value=>`"${String(value??'').replace(/"/g,'""')}"`;
            return '\ufeff'+rows.map(row=>row.map(escape).join(',')).join('\n');
        };
        load();
        return {createSnapshot,list,remove,clear,compare,buildWeeklyReport,toCsv,reload:load,exportForLaravel:filters=>clone(buildWeeklyReport(filters))};
    }


    function createPhase2ReadinessAudit(options={}){
        const nowProvider=typeof options.nowProvider==='function'?options.nowProvider:()=>new Date();
        const clone=value=>deepClone(value);
        const requiredEndpoints={
            problem_list:'問題一覧取得',
            problem_create:'問題登録',
            problem_update:'問題更新',
            problem_delete:'問題削除',
            learning_result:'学習結果保存',
            point_award:'ポイント付与',
            badge_award:'バッジ付与',
            routine_complete:'ルーティン完了',
            student_summary:'生徒カルテ集計保存'
        };
        const defaultCapabilities=[
            ['engine','将棋エンジン'],
            ['problem_management','問題管理'],
            ['preview','管理者プレビュー'],
            ['publication','公開ワークフロー'],
            ['learning_log','学習ログ'],
            ['student_dashboard','生徒カルテ集計'],
            ['classroom_report','教室・講師レポート'],
            ['routine_bridge','ルーティン連携'],
            ['point_badge_bridge','ポイント・バッジ連携'],
            ['guardian_report','保護者レポート']
        ];
        const normalizeBoolean=value=>value===true||value===1||value==='1'||value==='true';
        const audit=(input={})=>{
            const endpoints=input.endpoints&&typeof input.endpoints==='object'?input.endpoints:{};
            const capabilities=input.capabilities&&typeof input.capabilities==='object'?input.capabilities:{};
            const problemSet=input.problemSet||null;
            const checks=[];
            defaultCapabilities.forEach(([key,label])=>{
                const enabled=normalizeBoolean(capabilities[key]);
                checks.push({group:'capability',key,label,status:enabled?'ready':'missing',severity:enabled?'info':'high',message:enabled?'利用可能です。':'未接続または未確認です。'});
            });
            Object.entries(requiredEndpoints).forEach(([key,label])=>{
                const value=endpoints[key];
                const ready=typeof value==='string'?value.trim().length>0:normalizeBoolean(value);
                checks.push({group:'endpoint',key,label,status:ready?'ready':'missing',severity:ready?'info':'high',message:ready?'接続先が設定されています。':'Laravel APIの接続先が未設定です。'});
            });
            if(problemSet){
                const validation=validateProblemSet(problemSet,{strict:false});
                checks.push({group:'content',key:'problem_set_valid',label:'問題セット検証',status:validation.valid?'ready':'error',severity:validation.valid?'info':'high',message:validation.valid?'問題セットに重大なエラーはありません。':`${validation.errors.length}件のエラーがあります。`,details:clone(validation)});
                const published=(validation.problems||[]).filter(problem=>problem.is_published!==false).length;
                checks.push({group:'content',key:'published_problem_count',label:'公開問題数',status:published>0?'ready':'warning',severity:published>0?'info':'medium',message:published>0?`${published}問が公開対象です。`:'公開対象の問題がありません。',value:published});
            }else{
                checks.push({group:'content',key:'problem_set_valid',label:'問題セット検証',status:'missing',severity:'high',message:'問題セットが指定されていません。'});
            }
            const high=checks.filter(check=>check.status!=='ready'&&check.severity==='high').length;
            const medium=checks.filter(check=>check.status!=='ready'&&check.severity==='medium').length;
            const ready=checks.filter(check=>check.status==='ready').length;
            const total=checks.length;
            const score=total?Math.round((ready/total)*100):0;
            const releaseReady=high===0&&score>=90;
            const nextActions=checks.filter(check=>check.status!=='ready').map(check=>({key:check.key,label:check.label,priority:check.severity==='high'?'高':'中',action:check.group==='endpoint'?`${check.label}用のLaravelルートとコントローラーを実装してください。`:check.group==='capability'?`${check.label}を実際の画面またはAPIへ接続してください。`:check.message}));
            return {generated_at:nowProvider().toISOString(),release_ready:releaseReady,score,summary:{total,ready,high_issues:high,medium_issues:medium},checks,next_actions:nextActions};
        };
        const toCsv=input=>{
            const result=audit(input);
            const rows=[['区分','キー','項目','状態','重要度','内容']];
            result.checks.forEach(check=>rows.push([check.group,check.key,check.label,check.status,check.severity,check.message]));
            const escape=value=>`"${String(value??'').replace(/"/g,'""')}"`;
            return '\ufeff'+rows.map(row=>row.map(escape).join(',')).join('\n');
        };
        return {audit,toCsv,requiredEndpoints:()=>clone(requiredEndpoints),capabilityDefinitions:()=>defaultCapabilities.map(([key,label])=>({key,label})),exportForLaravel:input=>clone(audit(input))};
    }


    function createPhase2ReleaseGate(options={}){
        const nowProvider=typeof options.nowProvider==='function'?options.nowProvider:()=>new Date();
        const timeoutMs=Math.max(500,Number(options.timeoutMs||5000));
        const minimumPublishedProblems=Math.max(1,Number(options.minimumPublishedProblems||1));
        const clone=value=>deepClone(value);
        const withTimeout=(promise,label)=>new Promise((resolve,reject)=>{
            const timer=setTimeout(()=>reject(new Error(`${label}が${timeoutMs}ms以内に完了しませんでした。`)),timeoutMs);
            Promise.resolve(promise).then(value=>{clearTimeout(timer);resolve(value);},error=>{clearTimeout(timer);reject(error);});
        });
        const defaultSmokeTests={
            engine:context=>{
                const engine=context.engine||new ShogiEngine(context.engineConfig||defaultConfig());
                const state=typeof engine.getState==='function'?engine.getState():null;
                if(!state) throw new Error('将棋エンジンの状態を取得できません。');
                return {message:'将棋エンジンを初期化できました。'};
            },
            problem_set:context=>{
                if(!context.problemSet) throw new Error('問題セットが指定されていません。');
                const validation=validateProblemSet(context.problemSet,{strict:false});
                if(!validation.valid) throw new Error(`問題セットに${validation.errors.length}件のエラーがあります。`);
                const published=(validation.problems||[]).filter(problem=>problem.is_published!==false).length;
                if(published<minimumPublishedProblems) throw new Error(`公開問題が${minimumPublishedProblems}問未満です。`);
                return {message:`公開可能な問題を${published}問確認しました。`,value:published};
            },
            storage:context=>{
                const key=`lle-shogi-release-test-${Date.now()}`;
                try{
                    localStorage.setItem(key,'ok');
                    const value=localStorage.getItem(key);
                    localStorage.removeItem(key);
                    if(value!=='ok') throw new Error('保存内容を読み戻せませんでした。');
                }catch(error){
                    throw new Error(`ブラウザ保存を利用できません: ${error.message}`);
                }
                return {message:'ブラウザ保存の書込み・読込みを確認しました。'};
            },
            api_configuration:context=>{
                const endpoints=context.endpoints&&typeof context.endpoints==='object'?context.endpoints:{};
                const required=createPhase2ReadinessAudit().requiredEndpoints();
                const missing=Object.keys(required).filter(key=>typeof endpoints[key]!=='string'||!endpoints[key].trim());
                if(missing.length) throw new Error(`未設定API: ${missing.join(', ')}`);
                return {message:'必須Laravel APIの接続先を確認しました。'};
            }
        };
        const normalizeTestResult=(key,label,status,startedAt,detail={})=>({
            key,label,status,
            started_at:startedAt,
            completed_at:nowProvider().toISOString(),
            message:String(detail.message||''),
            value:detail.value??null,
            error:detail.error?String(detail.error):null
        });
        const run=async(input={})=>{
            const generatedAt=nowProvider().toISOString();
            const readiness=createPhase2ReadinessAudit({nowProvider}).audit(input);
            const configured=input.smokeTests&&typeof input.smokeTests==='object'?input.smokeTests:{};
            const tests={...defaultSmokeTests,...configured};
            const labels={engine:'将棋エンジン起動',problem_set:'問題セット検証',storage:'ブラウザ保存',api_configuration:'API設定',...(input.testLabels||{})};
            const results=[];
            for(const [key,test] of Object.entries(tests)){
                if(typeof test!=='function') continue;
                const startedAt=nowProvider().toISOString();
                try{
                    const detail=await withTimeout(test(input),labels[key]||key);
                    results.push(normalizeTestResult(key,labels[key]||key,'passed',startedAt,detail||{}));
                }catch(error){
                    results.push(normalizeTestResult(key,labels[key]||key,'failed',startedAt,{error:error&&error.message?error.message:error,message:'確認に失敗しました。'}));
                }
            }
            const passed=results.filter(result=>result.status==='passed').length;
            const failed=results.filter(result=>result.status==='failed').length;
            const releaseReady=readiness.release_ready&&failed===0;
            const blockers=[];
            readiness.checks.filter(check=>check.status!=='ready'&&check.severity==='high').forEach(check=>blockers.push({source:'readiness',key:check.key,message:check.message}));
            results.filter(result=>result.status==='failed').forEach(result=>blockers.push({source:'smoke_test',key:result.key,message:result.error||result.message}));
            const score=Math.round((readiness.score*0.7)+((results.length?passed/results.length:0)*100*0.3));
            return {
                generated_at:generatedAt,
                release_ready:releaseReady,
                release_version:String(input.releaseVersion||'phase2'),
                score,
                readiness:clone(readiness),
                smoke_tests:{total:results.length,passed,failed,results},
                blockers,
                recommendation:releaseReady?'Phase2のリリース候補として固定できます。':'ブロッカーを解消してから再実行してください。'
            };
        };
        const toCsv=result=>{
            const rows=[['種別','キー','項目','状態','内容']];
            (result.readiness?.checks||[]).forEach(check=>rows.push(['準備監査',check.key,check.label,check.status,check.message]));
            (result.smoke_tests?.results||[]).forEach(test=>rows.push(['動作確認',test.key,test.label,test.status,test.error||test.message]));
            const escape=value=>`"${String(value??'').replace(/"/g,'""')}"`;
            return '\ufeff'+rows.map(row=>row.map(escape).join(',')).join('\n');
        };
        return {run,toCsv,defaultTestKeys:()=>Object.keys(defaultSmokeTests),exportForLaravel:result=>clone(result)};
    }


    function createPhase2ReleasePackage(options={}){
        const nowProvider=typeof options.nowProvider==='function'?options.nowProvider:()=>new Date();
        const clone=value=>deepClone(value);
        const stableStringify=value=>{
            const normalize=input=>{
                if(Array.isArray(input)) return input.map(normalize);
                if(input&&typeof input==='object'){
                    return Object.keys(input).sort().reduce((out,key)=>{out[key]=normalize(input[key]);return out;},{});
                }
                return input;
            };
            return JSON.stringify(normalize(value));
        };
        const checksum=value=>{
            const text=stableStringify(value);
            let hash=2166136261;
            for(let i=0;i<text.length;i++){
                hash^=text.charCodeAt(i);
                hash=Math.imul(hash,16777619);
            }
            return (`00000000${(hash>>>0).toString(16)}`).slice(-8);
        };
        const defaultAcceptanceItems=[
            ['engine_move','駒を選択し、合法手へ移動できる'],
            ['capture_hand','相手駒を取り、持ち駒へ追加できる'],
            ['drop_rules','持ち駒を二歩・行き所・打ち歩詰め規則に従って打てる'],
            ['promotion','成る・成らないを正しく選択できる'],
            ['check_mate','王手・詰みを正しく判定できる'],
            ['problem_load','SFENと正解手順から問題を読み込める'],
            ['answer_judgement','正解・不正解・完了を判定できる'],
            ['learning_result','解答時間・ヒント・不正解・戻し回数を記録できる'],
            ['admin_crud','問題の登録・編集・複製・削除・公開切替ができる'],
            ['preview','管理画面から保存前問題をプレビューできる'],
            ['publication','レビュー・承認・公開ワークフローを実行できる'],
            ['lifeforce_sync','学習結果・ポイント・バッジ・ルーティンへ連携できる'],
            ['student_report','生徒カルテ用サマリーを生成できる'],
            ['guardian_report','保護者向けレポートを生成・配信管理できる']
        ];
        const normalizeAcceptance=input=>{
            const supplied=input&&typeof input==='object'?input:{};
            return defaultAcceptanceItems.map(([key,label])=>{
                const raw=supplied[key];
                const status=raw===true||raw==='passed'||raw==='complete'?'passed':raw===false||raw==='failed'?'failed':'pending';
                const note=raw&&typeof raw==='object'?String(raw.note||''):'';
                return {key,label,status,note};
            });
        };
        const buildApiContract=endpoints=>{
            const required=createPhase2ReadinessAudit().requiredEndpoints();
            const source=endpoints&&typeof endpoints==='object'?endpoints:{};
            return Object.entries(required).map(([key,label])=>({
                key,label,url:String(source[key]||''),configured:typeof source[key]==='string'&&source[key].trim().length>0
            }));
        };
        const build=async(input={})=>{
            const releaseVersion=String(input.releaseVersion||'v55-phase2-rc1');
            const gate=createPhase2ReleaseGate({
                nowProvider,
                timeoutMs:input.timeoutMs||options.timeoutMs,
                minimumPublishedProblems:input.minimumPublishedProblems||options.minimumPublishedProblems
            });
            const gateResult=await gate.run({...input,releaseVersion});
            const acceptance=normalizeAcceptance(input.acceptance);
            const acceptancePassed=acceptance.filter(item=>item.status==='passed').length;
            const acceptanceFailed=acceptance.filter(item=>item.status==='failed').length;
            const acceptancePending=acceptance.filter(item=>item.status==='pending').length;
            const apiContract=buildApiContract(input.endpoints);
            const problemValidation=input.problemSet?validateProblemSet(input.problemSet,{strict:false}):null;
            const contentSummary=problemValidation?{
                total_problems:(problemValidation.problems||[]).length,
                published_problems:(problemValidation.problems||[]).filter(problem=>problem.is_published!==false).length,
                errors:(problemValidation.errors||[]).length,
                warnings:(problemValidation.warnings||[]).length,
                schema:problemValidation.schema||problemSetSchema
            }:{total_problems:0,published_problems:0,errors:1,warnings:0,schema:null};
            const blockers=[...(gateResult.blockers||[])];
            acceptance.filter(item=>item.status!=='passed').forEach(item=>blockers.push({
                source:'acceptance',key:item.key,message:item.status==='failed'?`${item.label}が不合格です。`:`${item.label}が未確認です。`
            }));
            const releaseReady=gateResult.release_ready&&acceptanceFailed===0&&acceptancePending===0;
            const manifestBase={
                release_version:releaseVersion,
                generated_at:nowProvider().toISOString(),
                phase:'Phase2',
                release_ready:releaseReady,
                gate_score:gateResult.score,
                acceptance:{total:acceptance.length,passed:acceptancePassed,failed:acceptanceFailed,pending:acceptancePending},
                content:contentSummary,
                api_contract:apiContract,
                blockers,
                feature_api:Object.keys(window.LLEShogiMate||{}).sort()
            };
            const manifest={...manifestBase,checksum:checksum(manifestBase)};
            return {
                manifest,
                release_gate:clone(gateResult),
                acceptance_checklist:acceptance,
                api_contract:apiContract,
                content_validation:problemValidation?clone(problemValidation):null,
                recommendation:releaseReady?'Phase2リリース候補として固定できます。':'未確認または不合格項目を解消してから再作成してください。'
            };
        };
        const toJson=result=>JSON.stringify(result,null,2);
        const toCsv=result=>{
            const rows=[['区分','キー','項目','状態','内容']];
            (result.acceptance_checklist||[]).forEach(item=>rows.push(['受入確認',item.key,item.label,item.status,item.note]));
            (result.api_contract||[]).forEach(item=>rows.push(['API',item.key,item.label,item.configured?'configured':'missing',item.url]));
            (result.release_gate?.smoke_tests?.results||[]).forEach(item=>rows.push(['動作確認',item.key,item.label,item.status,item.error||item.message]));
            const escape=value=>`"${String(value??'').replace(/"/g,'""')}"`;
            return '\ufeff'+rows.map(row=>row.map(escape).join(',')).join('\n');
        };
        return {build,toJson,toCsv,checksum,acceptanceDefinitions:()=>defaultAcceptanceItems.map(([key,label])=>({key,label}))};
    }


    function createPhase2AcceptanceTestRunner(options={}){
        const nowProvider=typeof options.nowProvider==='function'?options.nowProvider:()=>new Date();
        const timeoutMs=Math.max(500,Number(options.timeoutMs||8000));
        const defaultTests=[
            ['engine_boot','将棋エンジン起動'],
            ['sfen_roundtrip','SFEN読込・再生成'],
            ['problem_validation','問題データ検証'],
            ['puzzle_session','問題セッション起動'],
            ['storage','ブラウザ保存'],
            ['release_package','リリースパッケージ生成']
        ];
        const withTimeout=(promise,label)=>Promise.race([
            Promise.resolve(promise),
            new Promise((_,reject)=>setTimeout(()=>reject(new Error(`${label}が${timeoutMs}ms以内に完了しませんでした。`)),timeoutMs))
        ]);
        const testMap={
            engine_boot:()=>{
                const engine=new ShogiEngine(defaultConfig());
                if(!engine||typeof engine.getState!=='function')throw new Error('将棋エンジンを生成できません。');
                return {state_available:true};
            },
            sfen_roundtrip:()=>{
                const source='9/9/9/9/9/9/9/9/9 b - 1';
                const parsed=parseSfen(source);
                const generated=generateSfen(parsed.board,parsed.hands,parsed.turn,parsed.moveNumber);
                if(generated!==source)throw new Error(`SFEN往復結果が一致しません: ${generated}`);
                return {sfen:generated};
            },
            problem_validation:context=>{
                if(!context.problemSet)throw new Error('問題セットが指定されていません。');
                const validation=validateProblemSet(context.problemSet,{strict:false});
                if((validation.errors||[]).length>0)throw new Error(`問題データに${validation.errors.length}件のエラーがあります。`);
                return {problems:(validation.problems||[]).length,warnings:(validation.warnings||[]).length};
            },
            puzzle_session:context=>{
                const normalized=normalizeProblemSet(context.problemSet||{},{});
                const first=(normalized.problems||[]).find(problem=>problem.is_published!==false)||(normalized.problems||[])[0];
                if(!first)throw new Error('テスト可能な問題がありません。');
                const session=new ShogiPuzzleSession(first);
                if(!session)throw new Error('問題セッションを生成できません。');
                return {problem_id:first.id};
            },
            storage:()=>{
                if(typeof localStorage==='undefined')return {skipped:true,reason:'localStorage unavailable'};
                const key='lle_shogi_phase2_acceptance_test';
                const value=String(nowProvider().getTime());
                localStorage.setItem(key,value);
                const restored=localStorage.getItem(key);
                localStorage.removeItem(key);
                if(restored!==value)throw new Error('ブラウザ保存の読込み結果が一致しません。');
                return {write_read:true};
            },
            release_package:async context=>{
                const acceptance={};
                const definitions=createPhase2ReleasePackage().acceptanceDefinitions();
                definitions.forEach(item=>{acceptance[item.key]={status:'passed',note:'自動受入確認'};});
                const result=await createPhase2ReleasePackage({nowProvider}).build({...context,acceptance});
                if(!result||!result.manifest)throw new Error('リリースマニフェストを生成できません。');
                return {checksum:result.manifest.checksum,release_ready:result.manifest.release_ready};
            }
        };
        const run=async(context={})=>{
            const startedAt=nowProvider();
            const requested=Array.isArray(context.tests)&&context.tests.length?context.tests:defaultTests.map(([key])=>key);
            const custom=context.customTests&&typeof context.customTests==='object'?context.customTests:{};
            const results=[];
            for(const key of requested){
                const definition=defaultTests.find(([testKey])=>testKey===key);
                const label=definition?definition[1]:key;
                const tester=custom[key]||testMap[key];
                const started=nowProvider();
                if(typeof tester!=='function'){
                    results.push({key,label,status:'skipped',duration_ms:0,message:'テスト処理が登録されていません。'});
                    continue;
                }
                try{
                    const detail=await withTimeout(tester(context),label);
                    results.push({key,label,status:'passed',duration_ms:Math.max(0,nowProvider()-started),detail:clone(detail)});
                }catch(error){
                    results.push({key,label,status:'failed',duration_ms:Math.max(0,nowProvider()-started),error:error instanceof Error?error.message:String(error)});
                }
            }
            const passed=results.filter(item=>item.status==='passed').length;
            const failed=results.filter(item=>item.status==='failed').length;
            const skipped=results.filter(item=>item.status==='skipped').length;
            const finishedAt=nowProvider();
            const report={
                phase:'Phase2',
                release_version:String(context.releaseVersion||'v56-phase2-acceptance'),
                started_at:startedAt.toISOString(),
                finished_at:finishedAt.toISOString(),
                duration_ms:Math.max(0,finishedAt-startedAt),
                passed,failed,skipped,total:results.length,
                acceptance_ready:failed===0&&skipped===0,
                results
            };
            if(typeof window!=='undefined'&&typeof window.dispatchEvent==='function'){
                window.dispatchEvent(new CustomEvent('lle-shogi:phase2-acceptance-complete',{detail:clone(report)}));
            }
            return report;
        };
        const toCsv=report=>{
            const rows=[['キー','確認項目','状態','所要時間(ms)','内容']];
            (report.results||[]).forEach(item=>rows.push([item.key,item.label,item.status,item.duration_ms,item.error||item.message||JSON.stringify(item.detail||{})]));
            const escape=value=>`"${String(value??'').replace(/"/g,'""')}"`;
            return '\ufeff'+rows.map(row=>row.map(escape).join(',')).join('\n');
        };
        return {run,toCsv,definitions:()=>defaultTests.map(([key,label])=>({key,label}))};
    }


    function createProblemSetNavigator(options={}){
        const storageKey=String(options.storageKey||'lle_shogi_problem_set_navigation_v1');
        const nowProvider=typeof options.nowProvider==='function'?options.nowProvider:()=>new Date();
        const listeners=new Set();
        let set=normalizeProblemSet(options.problemSet||{},{includeUnpublished:!!options.includeUnpublished});
        let currentIndex=Math.max(0,Math.min(Number(options.initialIndex||0),Math.max(0,(set.problems||[]).length-1)));
        let state={completed:{},results:{},updated_at:null};

        const cloneState=()=>deepClone({
            problem_set_id:set.id||set.code||null,
            current_index:currentIndex,
            current_problem_id:(set.problems[currentIndex]||{}).id||null,
            total:(set.problems||[]).length,
            completed_count:Object.keys(state.completed).filter(id=>state.completed[id]).length,
            completion_rate:(set.problems||[]).length?Math.round((Object.keys(state.completed).filter(id=>state.completed[id]).length/(set.problems||[]).length)*100):0,
            completed:state.completed,
            results:state.results,
            updated_at:state.updated_at
        });
        const emit=(type,detail={})=>{
            const payload={type,...cloneState(),...deepClone(detail)};
            listeners.forEach(listener=>{try{listener(payload);}catch(error){console.error(error);}});
            if(typeof window!=='undefined'&&typeof window.dispatchEvent==='function'){
                window.dispatchEvent(new CustomEvent(`lle-shogi:problem-set-${type}`,{detail:payload}));
            }
            return payload;
        };
        const persist=()=>{
            state.updated_at=nowProvider().toISOString();
            if(typeof localStorage!=='undefined'){
                localStorage.setItem(storageKey,JSON.stringify({
                    problem_set_id:set.id||set.code||null,
                    current_index:currentIndex,
                    completed:state.completed,
                    results:state.results,
                    updated_at:state.updated_at
                }));
            }
            emit('saved');
            return cloneState();
        };
        const restore=()=>{
            if(typeof localStorage==='undefined')return cloneState();
            try{
                const raw=JSON.parse(localStorage.getItem(storageKey)||'null');
                if(!raw||typeof raw!=='object')return cloneState();
                const setId=set.id||set.code||null;
                if(raw.problem_set_id!==null&&setId!==null&&String(raw.problem_set_id)!==String(setId))return cloneState();
                state.completed=raw.completed&&typeof raw.completed==='object'?raw.completed:{};
                state.results=raw.results&&typeof raw.results==='object'?raw.results:{};
                state.updated_at=raw.updated_at||null;
                currentIndex=Math.max(0,Math.min(Number(raw.current_index||0),Math.max(0,(set.problems||[]).length-1)));
                emit('restored');
            }catch(error){
                console.warn('詰将棋問題セットの進捗を復元できませんでした。',error);
            }
            return cloneState();
        };
        const getCurrent=()=>deepClone(set.problems[currentIndex]||null);
        const canMoveTo=index=>{
            const target=Number(index);
            if(!Number.isInteger(target)||target<0||target>=(set.problems||[]).length)return false;
            if(options.lockUntilPreviousComplete===false||target===0)return true;
            const previous=set.problems[target-1];
            return !!(previous&&state.completed[String(previous.id)]);
        };
        const goTo=index=>{
            const target=Number(index);
            if(!canMoveTo(target))return {ok:false,reason:'locked',state:cloneState()};
            currentIndex=target;
            persist();
            emit('changed',{problem:getCurrent()});
            return {ok:true,problem:getCurrent(),state:cloneState()};
        };
        const next=()=>goTo(currentIndex+1);
        const previous=()=>goTo(currentIndex-1);
        const complete=(result={})=>{
            const problem=set.problems[currentIndex];
            if(!problem)return {ok:false,reason:'problem_not_found'};
            const id=String(problem.id);
            const normalized={...deepClone(result),problem_id:problem.id,completed_at:result.completed_at||nowProvider().toISOString()};
            state.completed[id]=true;
            const old=state.results[id];
            if(!old||Number(normalized.score||0)>Number(old.score||0)||(
                Number(normalized.score||0)===Number(old.score||0)&&Number(normalized.elapsed_time_ms||Infinity)<Number(old.elapsed_time_ms||Infinity)
            ))state.results[id]=normalized;
            persist();
            emit('completed',{problem:deepClone(problem),result:normalized});
            return {ok:true,result:deepClone(normalized),state:cloneState(),has_next:currentIndex<(set.problems||[]).length-1};
        };
        const reset=()=>{
            currentIndex=0;
            state={completed:{},results:{},updated_at:null};
            if(typeof localStorage!=='undefined')localStorage.removeItem(storageKey);
            emit('reset');
            return cloneState();
        };
        const replaceProblemSet=raw=>{
            set=normalizeProblemSet(raw||{},{includeUnpublished:!!options.includeUnpublished});
            currentIndex=Math.max(0,Math.min(currentIndex,Math.max(0,(set.problems||[]).length-1)));
            emit('reloaded');
            return cloneState();
        };
        const list=()=>deepClone((set.problems||[]).map((problem,index)=>({
            index,
            id:problem.id,
            title:problem.title,
            difficulty:problem.difficulty,
            completed:!!state.completed[String(problem.id)],
            locked:!canMoveTo(index),
            current:index===currentIndex,
            best_result:state.results[String(problem.id)]||null
        })));
        const subscribe=listener=>{if(typeof listener==='function')listeners.add(listener);return()=>listeners.delete(listener);};
        if(options.autoRestore!==false)restore();
        return {getState:cloneState,getCurrent,list,goTo,next,previous,complete,reset,restore,persist,replaceProblemSet,subscribe};
    }


    function mountProblemSetNavigator(root,navigator,options={}){
        if(!(root instanceof Element))throw new Error('問題一覧の表示先が見つかりません。');
        if(!navigator||typeof navigator.list!=='function'||typeof navigator.goTo!=='function'){
            throw new Error('有効な問題セットナビゲーターを指定してください。');
        }
        const labels={
            completed:String(options.completedLabel||'クリア'),
            current:String(options.currentLabel||'挑戦中'),
            locked:String(options.lockedLabel||'未解放'),
            available:String(options.availableLabel||'挑戦する')
        };
        const showBest=options.showBestResult!==false;
        const showSummary=options.showSummary!==false;
        const emit=(type,detail={})=>{
            const payload={type,...deepClone(detail)};
            if(typeof options.onEvent==='function')options.onEvent(payload);
            if(typeof window!=='undefined'&&typeof window.dispatchEvent==='function'){
                window.dispatchEvent(new CustomEvent(`lle-shogi:problem-list-${type}`,{detail:payload}));
            }
        };
        const ensureStyles=()=>{
            if(typeof document==='undefined'||document.getElementById('lle-shogi-problem-list-style'))return;
            const style=document.createElement('style');
            style.id='lle-shogi-problem-list-style';
            style.textContent=`
                .lle-shogi-problem-list{display:grid;gap:14px;font-family:inherit}
                .lle-shogi-problem-list__summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border:1px solid #d9e0e7;border-radius:12px;background:#fff}
                .lle-shogi-problem-list__progress{flex:1;height:10px;border-radius:999px;background:#edf1f4;overflow:hidden}
                .lle-shogi-problem-list__bar{height:100%;width:0;border-radius:inherit;background:linear-gradient(90deg,#e8a33a,#d57d21);transition:width .25s ease}
                .lle-shogi-problem-list__count{white-space:nowrap;font-weight:700;color:#344054}
                .lle-shogi-problem-list__items{display:grid;gap:10px}
                .lle-shogi-problem-card{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:12px;width:100%;padding:13px 14px;border:1px solid #d9e0e7;border-radius:12px;background:#fff;text-align:left;cursor:pointer;transition:transform .15s ease,border-color .15s ease,box-shadow .15s ease}
                .lle-shogi-problem-card:hover:not(:disabled){transform:translateY(-1px);border-color:#d59a48;box-shadow:0 5px 14px rgba(43,54,67,.08)}
                .lle-shogi-problem-card:disabled{cursor:not-allowed;opacity:.62;background:#f7f8fa}
                .lle-shogi-problem-card.is-current{border-color:#d57d21;box-shadow:0 0 0 2px rgba(213,125,33,.16)}
                .lle-shogi-problem-card.is-completed{background:#fffaf2}
                .lle-shogi-problem-card__number{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:#eef1f4;font-weight:800;color:#475467}
                .lle-shogi-problem-card.is-completed .lle-shogi-problem-card__number{background:#e7f6ec;color:#18794e}
                .lle-shogi-problem-card__title{font-weight:800;color:#1d2939}
                .lle-shogi-problem-card__meta{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;font-size:12px;color:#667085}
                .lle-shogi-problem-card__status{font-size:12px;font-weight:800;padding:5px 9px;border-radius:999px;background:#eef1f4;color:#475467}
                .lle-shogi-problem-card.is-current .lle-shogi-problem-card__status{background:#fff0d7;color:#9a5b16}
                .lle-shogi-problem-card.is-completed .lle-shogi-problem-card__status{background:#e7f6ec;color:#18794e}
            `;
            document.head.appendChild(style);
        };
        const resultText=result=>{
            if(!result||typeof result!=='object')return '';
            const parts=[];
            if(Number.isFinite(Number(result.score)))parts.push(`ベスト ${Number(result.score)}点`);
            const elapsed=Number(result.elapsed_time_ms||result.elapsed_time||0);
            if(elapsed>0)parts.push(`${Math.max(1,Math.round(elapsed/1000))}秒`);
            return parts.join('・');
        };
        const render=()=>{
            ensureStyles();
            const state=navigator.getState();
            const items=navigator.list();
            root.classList.add('lle-shogi-problem-list');
            root.innerHTML='';
            if(showSummary){
                const summary=document.createElement('div');
                summary.className='lle-shogi-problem-list__summary';
                summary.innerHTML=`<div class="lle-shogi-problem-list__progress" aria-label="問題セット進捗"><div class="lle-shogi-problem-list__bar" style="width:${Math.max(0,Math.min(100,Number(state.completion_rate||0)))}%"></div></div><div class="lle-shogi-problem-list__count">${Number(state.completed_count||0)} / ${Number(state.total||0)}問クリア</div>`;
                root.appendChild(summary);
            }
            const list=document.createElement('div');
            list.className='lle-shogi-problem-list__items';
            items.forEach(item=>{
                const button=document.createElement('button');
                button.type='button';
                button.className='lle-shogi-problem-card';
                if(item.current)button.classList.add('is-current');
                if(item.completed)button.classList.add('is-completed');
                if(item.locked)button.classList.add('is-locked');
                button.disabled=!!item.locked;
                button.dataset.problemIndex=String(item.index);
                const status=item.locked?labels.locked:(item.current?labels.current:(item.completed?labels.completed:labels.available));
                const best=showBest?resultText(item.best_result):'';
                button.innerHTML=`
                    <span class="lle-shogi-problem-card__number">${item.completed?'✓':item.index+1}</span>
                    <span>
                        <span class="lle-shogi-problem-card__title">${escapeHtml(item.title||`問題 ${item.index+1}`)}</span>
                        <span class="lle-shogi-problem-card__meta">${item.difficulty?`<span>${escapeHtml(item.difficulty)}</span>`:''}${best?`<span>${escapeHtml(best)}</span>`:''}</span>
                    </span>
                    <span class="lle-shogi-problem-card__status">${escapeHtml(status)}</span>`;
                button.addEventListener('click',()=>{
                    const moved=navigator.goTo(item.index);
                    if(moved&&moved.ok){emit('selected',{problem:moved.problem,state:moved.state});render();}
                    else emit('blocked',{index:item.index,reason:moved&&moved.reason||'unknown'});
                });
                list.appendChild(button);
            });
            if(!items.length){
                const empty=document.createElement('div');
                empty.className='lle-shogi-problem-list__empty';
                empty.textContent='出題できる問題がありません。';
                list.appendChild(empty);
            }
            root.appendChild(list);
            emit('rendered',{state,items});
            return {state,items};
        };
        const unsubscribe=navigator.subscribe(event=>{
            if(['changed','completed','reset','restored','reloaded','saved'].includes(event.type))render();
        });
        render();
        return {
            render,
            destroy(){unsubscribe();root.innerHTML='';root.classList.remove('lle-shogi-problem-list');},
            focusCurrent(){const current=root.querySelector('.lle-shogi-problem-card.is-current');if(current&&typeof current.scrollIntoView==='function')current.scrollIntoView({block:'nearest',behavior:'smooth'});}
        };
    }


    function createProblemSetResult(problemSet, navigatorState = {}, options = {}) {
        const set = normalizeProblemSet(problemSet || {}, { includeUnpublished: !!options.includeUnpublished });
        const state = navigatorState && typeof navigatorState === 'object' ? navigatorState : {};
        const resultsMap = state.results && typeof state.results === 'object' ? state.results : {};
        const rows = (set.problems || []).map((problem, index) => {
            const result = resultsMap[String(problem.id)] || null;
            const score = Number(result?.evaluation?.score ?? result?.score ?? 0);
            const elapsed = Number(result?.elapsed_ms ?? result?.elapsed_time_ms ?? 0);
            const hints = Number(result?.hint_count ?? result?.hints ?? 0);
            const undos = Number(result?.undo_count ?? 0);
            const mistakes = Number(result?.mistake_count ?? result?.retry_count ?? 0);
            return {
                index,
                problem_id: problem.id,
                title: problem.title,
                difficulty: problem.difficulty,
                completed: !!result,
                score,
                elapsed_ms: elapsed,
                hint_count: hints,
                undo_count: undos,
                mistake_count: mistakes,
                perfect: !!(result?.evaluation?.perfect ?? result?.perfect)
            };
        });
        const completed = rows.filter(row => row.completed);
        const average = completed.length ? Math.round(completed.reduce((sum,row)=>sum+row.score,0)/completed.length) : 0;
        const totalElapsed = completed.reduce((sum,row)=>sum+row.elapsed_ms,0);
        const totalHints = completed.reduce((sum,row)=>sum+row.hint_count,0);
        const totalUndos = completed.reduce((sum,row)=>sum+row.undo_count,0);
        const totalMistakes = completed.reduce((sum,row)=>sum+row.mistake_count,0);
        const perfectCount = completed.filter(row=>row.perfect).length;
        const rank = average >= 95 ? 'S' : average >= 85 ? 'A' : average >= 70 ? 'B' : average >= 55 ? 'C' : 'D';
        const stars = average >= 90 ? 3 : average >= 70 ? 2 : average > 0 ? 1 : 0;
        const weakProblems = rows.filter(row => row.completed && (row.score < Number(options.weakScoreThreshold ?? 80) || row.hint_count > 0 || row.mistake_count > 0));
        return deepClone({
            problem_set_id: set.id,
            title: set.title,
            total: rows.length,
            completed: completed.length,
            completion_rate: rows.length ? Math.round(completed.length / rows.length * 100) : 0,
            is_complete: rows.length > 0 && completed.length === rows.length,
            average_score: average,
            rank,
            stars,
            total_elapsed_ms: totalElapsed,
            total_hint_count: totalHints,
            total_undo_count: totalUndos,
            total_mistake_count: totalMistakes,
            perfect_count: perfectCount,
            perfect_rate: completed.length ? Math.round(perfectCount / completed.length * 100) : 0,
            problems: rows,
            weak_problem_ids: weakProblems.map(row=>row.problem_id),
            generated_at: new Date().toISOString()
        });
    }

    function mountProblemSetResult(root, result, options = {}) {
        if (!(root instanceof Element)) throw new Error('問題セット結果の表示先が見つかりません。');
        const data = deepClone(result || {});
        root.innerHTML = '';
        root.classList.add('lle-shogi-set-result');
        const title = document.createElement('h3');
        title.textContent = data.is_complete ? '問題セットクリア！' : '学習結果';
        const summary = document.createElement('div');
        summary.className = 'lle-shogi-set-result__summary';
        summary.innerHTML = `<strong>${Number(data.average_score||0)}点</strong><span>ランク ${String(data.rank||'-')}</span><span>${'★'.repeat(Number(data.stars||0))}${'☆'.repeat(Math.max(0,3-Number(data.stars||0)))}</span><span>${Number(data.completed||0)} / ${Number(data.total||0)}問</span>`;
        const metrics = document.createElement('div');
        metrics.className = 'lle-shogi-set-result__metrics';
        metrics.innerHTML = `<span>学習時間 ${Math.round(Number(data.total_elapsed_ms||0)/1000)}秒</span><span>ヒント ${Number(data.total_hint_count||0)}回</span><span>一手戻し ${Number(data.total_undo_count||0)}回</span><span>パーフェクト ${Number(data.perfect_count||0)}問</span>`;
        const list = document.createElement('div');
        list.className = 'lle-shogi-set-result__list';
        (data.problems||[]).forEach((row,index)=>{
            const item=document.createElement('div');
            item.className='lle-shogi-set-result__item'+(row.completed?' is-complete':' is-incomplete');
            item.innerHTML=`<span>${index+1}. ${String(row.title||'問題')}</span><strong>${row.completed?`${Number(row.score||0)}点`:'未完了'}</strong>`;
            list.appendChild(item);
        });
        const actions = document.createElement('div');
        actions.className = 'lle-shogi-set-result__actions';
        const makeButton=(label,action,disabled=false)=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.disabled=disabled;b.addEventListener('click',()=>{if(typeof options[action]==='function')options[action](deepClone(data));window.dispatchEvent(new CustomEvent(`lle-shogi:problem-set-result-${action}`,{detail:deepClone(data)}));});return b;};
        actions.appendChild(makeButton('もう一度挑戦','onRetry'));
        actions.appendChild(makeButton('苦手問題だけ再挑戦','onRetryWeak',!(data.weak_problem_ids||[]).length));
        actions.appendChild(makeButton('終了','onFinish'));
        root.append(title,summary,metrics,list,actions);
        window.dispatchEvent(new CustomEvent('lle-shogi:problem-set-result-rendered',{detail:deepClone(data)}));
        return { data, destroy(){root.innerHTML='';root.classList.remove('lle-shogi-set-result');} };
    }

    function createProblemSetCompletionController(options = {}) {
        const navigator = options.navigator;
        if (!navigator || typeof navigator.getState !== 'function') throw new Error('問題セットナビゲーターが必要です。');
        const problemSet = normalizeProblemSet(options.problemSet || {}, { includeUnpublished: !!options.includeUnpublished });
        const listeners = new Set();
        const emit=(type,detail={})=>{const payload={type,...deepClone(detail)};listeners.forEach(fn=>{try{fn(payload);}catch(e){console.error(e);}});if(typeof window!=='undefined')window.dispatchEvent(new CustomEvent(`lle-shogi:completion-${type}`,{detail:payload}));return payload;};
        const build=()=>createProblemSetResult(problemSet,navigator.getState(),options);
        const finish=async()=>{
            const result=build();
            if(!result.is_complete)return {ok:false,reason:'not_complete',result};
            emit('started',{result});
            const payload={
                component_type:'shogi_problem_set',
                problem_set_id:result.problem_set_id,
                completed:true,
                completed_at:new Date().toISOString(),
                score:result.average_score,
                rank:result.rank,
                stars:result.stars,
                total_elapsed_ms:result.total_elapsed_ms,
                result
            };
            try{
                if(typeof options.onSaveLearningResult==='function')await options.onSaveLearningResult(deepClone(payload));
                if(typeof options.onCompleteRoutine==='function')await options.onCompleteRoutine(deepClone(payload));
                if(typeof options.onUpdateStudentRecord==='function')await options.onUpdateStudentRecord(deepClone(payload));
                emit('completed',{payload});
                return {ok:true,payload};
            }catch(error){
                emit('failed',{error:String(error?.message||error),payload});
                return {ok:false,reason:'sync_failed',error,payload};
            }
        };
        const retryAll=()=>{navigator.reset();emit('retry-all');return navigator.getState();};
        const retryWeak=()=>{const result=build();emit('retry-weak',{problem_ids:result.weak_problem_ids});return deepClone(result.weak_problem_ids);};
        const subscribe=fn=>{if(typeof fn==='function')listeners.add(fn);return()=>listeners.delete(fn);};
        return {buildResult:build,finish,retryAll,retryWeak,subscribe};
    }


    function mountStudentProblemSet(root, rawProblemSet, options = {}) {
        if (!(root instanceof Element)) throw new Error('詰将棋問題セットの表示先が見つかりません。');
        const problemSet = normalizeProblemSet(rawProblemSet || {}, { includeUnpublished: !!options.includeUnpublished });
        if (!(problemSet.problems || []).length) throw new Error('出題できる詰将棋問題がありません。');

        root.innerHTML = '<section class="lle-shogi-set-player">'
            + '<aside class="lle-shogi-set-player__navigator" data-shogi-set-navigator></aside>'
            + '<main class="lle-shogi-set-player__main">'
            + '<div class="lle-shogi-set-player__problem" data-shogi-set-problem></div>'
            + '<div class="lle-shogi-set-player__result" data-shogi-set-result hidden></div>'
            + '</main></section>';

        const navigatorRoot = root.querySelector('[data-shogi-set-navigator]');
        const problemRoot = root.querySelector('[data-shogi-set-problem]');
        const resultRoot = root.querySelector('[data-shogi-set-result]');
        const navigator = createProblemSetNavigator({
            problemSet,
            storageKey: options.storageKey || `lle_shogi_problem_set_${problemSet.id || problemSet.code || 'default'}`,
            lockUntilPreviousComplete: options.lockUntilPreviousComplete !== false,
            autoRestore: options.autoRestore !== false,
            includeUnpublished: !!options.includeUnpublished
        });
        const completion = createProblemSetCompletionController({
            navigator,
            problemSet,
            onSaveLearningResult: options.onSaveLearningResult,
            onCompleteRoutine: options.onCompleteRoutine,
            onUpdateStudentRecord: options.onUpdateStudentRecord
        });
        let player = null;
        let navigatorView = null;
        let resultView = null;
        let destroyed = false;

        const emit = (name, detail = {}) => {
            root.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: deepClone(detail) }));
        };
        const destroyPlayer = () => {
            if (player && typeof player.destroy === 'function') player.destroy();
            player = null;
            problemRoot.innerHTML = '';
        };
        const renderNavigator = () => {
            if (navigatorView && typeof navigatorView.destroy === 'function') navigatorView.destroy();
            navigatorView = mountProblemSetNavigator(navigatorRoot, navigator, {
                onSelect: index => {
                    const moved = navigator.goTo(index);
                    if (moved.ok) renderProblem();
                }
            });
        };
        const showFinalResult = async () => {
            destroyPlayer();
            problemRoot.hidden = true;
            resultRoot.hidden = false;
            const result = completion.buildResult();
            if (resultView && typeof resultView.destroy === 'function') resultView.destroy();
            resultView = mountProblemSetResult(resultRoot, result, {
                onRetry: () => {
                    completion.retryAll();
                    resultRoot.hidden = true;
                    problemRoot.hidden = false;
                    renderNavigator();
                    renderProblem();
                },
                onRetryWeak: data => {
                    const ids = data.weak_problem_ids || [];
                    if (!ids.length) return;
                    const firstIndex = navigator.list().findIndex(row => ids.map(String).includes(String(row.id)));
                    if (firstIndex >= 0) navigator.goTo(firstIndex);
                    resultRoot.hidden = true;
                    problemRoot.hidden = false;
                    renderNavigator();
                    renderProblem();
                },
                onFinish: async () => {
                    const saved = await completion.finish();
                    emit('lle:shogi-problem-set-finished', saved);
                    if (typeof options.onFinish === 'function') options.onFinish(deepClone(saved));
                }
            });
            emit('lle:shogi-problem-set-result', result);
        };
        const renderProblem = () => {
            if (destroyed) return;
            const current = navigator.getCurrent();
            if (!current) return;
            destroyPlayer();
            problemRoot.hidden = false;
            resultRoot.hidden = true;
            player = mountPlayer(problemRoot, current);
            const completeHandler = event => {
                const result = event.detail?.result || {};
                const completed = navigator.complete({
                    ...deepClone(result),
                    score: result.evaluation?.score ?? result.score ?? 0,
                    elapsed_time_ms: result.elapsed_ms ?? result.elapsed_time_ms ?? 0,
                    completed_at: new Date().toISOString()
                });
                renderNavigator();
                emit('lle:shogi-problem-completed', { problem: current, result: completed.result, state: completed.state });
                if (completed.state.completed_count >= completed.state.total) {
                    setTimeout(showFinalResult, Number(options.resultDelayMs || 500));
                }
            };
            const nextHandler = () => {
                const moved = navigator.next();
                if (moved.ok) {
                    renderNavigator();
                    renderProblem();
                } else if (navigator.getState().completed_count >= navigator.getState().total) {
                    showFinalResult();
                }
            };
            problemRoot.addEventListener('lle:component-complete', completeHandler, { once: true });
            problemRoot.addEventListener('lle:shogi-next-problem', nextHandler, { once: true });
            emit('lle:shogi-problem-rendered', { problem: current, state: navigator.getState() });
        };

        renderNavigator();
        if (navigator.getState().completed_count >= navigator.getState().total && options.showResultWhenComplete !== false) {
            showFinalResult();
        } else {
            renderProblem();
        }

        return {
            navigator,
            completion,
            getState: () => navigator.getState(),
            getCurrentProblem: () => navigator.getCurrent(),
            goTo: index => {
                const result = navigator.goTo(index);
                if (result.ok) { renderNavigator(); renderProblem(); }
                return result;
            },
            showResult: showFinalResult,
            reset: () => {
                navigator.reset();
                renderNavigator();
                renderProblem();
                return navigator.getState();
            },
            destroy: () => {
                destroyed = true;
                destroyPlayer();
                if (navigatorView && typeof navigatorView.destroy === 'function') navigatorView.destroy();
                if (resultView && typeof resultView.destroy === 'function') resultView.destroy();
                root.innerHTML = '';
            }
        };
    }


    /**
     * 生徒画面に配置された詰将棋問題セットを data 属性だけで初期化する。
     *
     * 対応例:
     * <div data-lle-shogi-problem-set data-problem-set-id="set-1"></div>
     * <script type="application/json" data-lle-shogi-problem-set-data="set-1">{...}</script>
     *
     * または data-problem-set-json にJSON文字列を直接設定できる。
     */
    function autoMountStudentProblemSets(scope = document, options = {}) {
        const container = scope instanceof Element || scope instanceof Document ? scope : document;
        const mounted = [];
        const errors = [];

        const readProblemSet = root => {
            const direct = root.dataset.problemSetJson;
            if (direct) {
                try { return JSON.parse(direct); }
                catch (error) { throw new Error(`問題セットJSONを解析できませんでした: ${error.message}`); }
            }

            const id = root.dataset.problemSetId || root.id || '';
            let script = null;
            if (id) {
                const escaped = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(id) : id.replace(/["\\]/g, '\\$&');
                script = container.querySelector(`script[type="application/json"][data-lle-shogi-problem-set-data="${escaped}"]`)
                    || document.querySelector(`script[type="application/json"][data-lle-shogi-problem-set-data="${escaped}"]`);
            }
            script ||= root.querySelector('script[type="application/json"][data-lle-shogi-problem-set-data]');
            if (!script) throw new Error('詰将棋問題セットのJSONデータが見つかりません。');
            try { return JSON.parse(script.textContent || '{}'); }
            catch (error) { throw new Error(`問題セットJSONを解析できませんでした: ${error.message}`); }
        };

        container.querySelectorAll('[data-lle-shogi-problem-set]').forEach(root => {
            if (root.dataset.lleShogiMounted === '1') return;
            try {
                const problemSet = readProblemSet(root);
                const instance = mountStudentProblemSet(root, problemSet, {
                    storageKey: root.dataset.storageKey || options.storageKey,
                    lockUntilPreviousComplete: root.dataset.lockUntilPreviousComplete !== 'false',
                    autoRestore: root.dataset.autoRestore !== 'false',
                    showResultWhenComplete: root.dataset.showResultWhenComplete !== 'false',
                    resultDelayMs: Number(root.dataset.resultDelayMs || options.resultDelayMs || 500),
                    includeUnpublished: root.dataset.includeUnpublished === 'true',
                    onSaveLearningResult: options.onSaveLearningResult,
                    onCompleteRoutine: options.onCompleteRoutine,
                    onUpdateStudentRecord: options.onUpdateStudentRecord,
                    onFinish: options.onFinish
                });
                root.dataset.lleShogiMounted = '1';
                delete root.dataset.lleShogiMountError;
                root.__lleShogiProblemSet = instance;
                mounted.push({ root, instance });
                root.dispatchEvent(new CustomEvent('lle:shogi-problem-set-mounted', {
                    bubbles: true,
                    detail: { problem_set_id: problemSet.id || problemSet.code || null }
                }));
            } catch (error) {
                errors.push({ root, error });
                root.dataset.lleShogiMountError = '1';
                root.innerHTML = `<div class="lle-shogi-mount-error" role="alert">`
                    + `<p>${escapeHtml(error.message || '詰将棋問題セットを表示できませんでした。')}</p>`
                    + '<button type="button" data-lle-shogi-retry-mount>再読込</button>'
                    + '</div>';
                root.dispatchEvent(new CustomEvent('lle:shogi-problem-set-mount-error', {
                    bubbles: true,
                    detail: { message: error.message || String(error) }
                }));
            }
        });

        return { mounted, errors };
    }

    function destroyAutoMountedStudentProblemSets(scope = document) {
        const container = scope instanceof Element || scope instanceof Document ? scope : document;
        let destroyed = 0;
        container.querySelectorAll('[data-lle-shogi-problem-set][data-lle-shogi-mounted="1"]').forEach(root => {
            const instance = root.__lleShogiProblemSet;
            if (instance && typeof instance.destroy === 'function') instance.destroy();
            delete root.__lleShogiProblemSet;
            delete root.dataset.lleShogiMounted;
            destroyed += 1;
        });
        return destroyed;
    }


    /**
     * 読込に失敗した詰将棋問題セットを再試行する。
     * AjaxでJSONデータが後から追加された場合にも利用できる。
     */
    function retryFailedStudentProblemSets(scope = document, options = {}) {
        const container = scope instanceof Element || scope instanceof Document ? scope : document;
        const roots = [];
        if (container instanceof Element && container.matches('[data-lle-shogi-problem-set][data-lle-shogi-mount-error="1"]')) {
            roots.push(container);
        }
        container.querySelectorAll?.('[data-lle-shogi-problem-set][data-lle-shogi-mount-error="1"]').forEach(root => roots.push(root));

        roots.forEach(root => {
            delete root.dataset.lleShogiMountError;
            root.innerHTML = '<div class="lle-shogi-mount-loading" role="status" aria-live="polite">詰将棋問題を再読込しています。</div>';
        });

        const result = autoMountStudentProblemSets(container, options);
        document.dispatchEvent(new CustomEvent('lle:shogi-problem-set-retry-complete', {
            detail: { requested: roots.length, mounted: result.mounted.length, errors: result.errors.length }
        }));
        return { requested: roots.length, ...result };
    }

    function handleStudentProblemSetRetryClick(event) {
        const button = event.target instanceof Element ? event.target.closest('[data-lle-shogi-retry-mount]') : null;
        if (!button) return;
        const root = button.closest('[data-lle-shogi-problem-set]');
        if (!root) return;
        event.preventDefault();
        retryFailedStudentProblemSets(root);
    }

    document.addEventListener('click', handleStudentProblemSetRetryClick);


    let studentProblemSetAutoObserver = null;
    let studentProblemSetAutoMountScheduled = false;

    function scheduleStudentProblemSetAutoMount(scope = document, options = {}) {
        if (studentProblemSetAutoMountScheduled) return;
        studentProblemSetAutoMountScheduled = true;
        const run = () => {
            studentProblemSetAutoMountScheduled = false;
            autoMountStudentProblemSets(scope, options);
        };
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(run);
        } else {
            window.setTimeout(run, 0);
        }
    }

    function startStudentProblemSetAutoMount(options = {}) {
        const scope = options.scope instanceof Element || options.scope instanceof Document
            ? options.scope
            : document;

        scheduleStudentProblemSetAutoMount(scope, options);

        if (studentProblemSetAutoObserver || options.observe === false || typeof MutationObserver === 'undefined') {
            return {
                observing: Boolean(studentProblemSetAutoObserver),
                stop: stopStudentProblemSetAutoMount
            };
        }

        const observeTarget = scope instanceof Document ? scope.documentElement : scope;
        if (!observeTarget) {
            return { observing: false, stop: stopStudentProblemSetAutoMount };
        }

        studentProblemSetAutoObserver = new MutationObserver(mutations => {
            let hasRelevantAddition = false;

            mutations.forEach(mutation => {
                if (mutation.type !== 'childList') return;

                Array.from(mutation.removedNodes || []).forEach(node => {
                    if (!(node instanceof Element)) return;
                    const roots = [];
                    if (node.matches?.('[data-lle-shogi-problem-set][data-lle-shogi-mounted="1"]')) roots.push(node);
                    node.querySelectorAll?.('[data-lle-shogi-problem-set][data-lle-shogi-mounted="1"]').forEach(root => roots.push(root));
                    roots.forEach(root => {
                        const instance = root.__lleShogiProblemSet;
                        if (instance && typeof instance.destroy === 'function') instance.destroy();
                        delete root.__lleShogiProblemSet;
                        delete root.dataset.lleShogiMounted;
                    });
                });

                if (!hasRelevantAddition) {
                    hasRelevantAddition = Array.from(mutation.addedNodes || []).some(node => {
                        if (!(node instanceof Element)) return false;
                        return node.matches?.('[data-lle-shogi-problem-set], script[type="application/json"][data-lle-shogi-problem-set-data]')
                            || Boolean(node.querySelector?.('[data-lle-shogi-problem-set], script[type="application/json"][data-lle-shogi-problem-set-data]'));
                    });
                }
            });

            if (hasRelevantAddition) {
                retryFailedStudentProblemSets(scope, options);
                scheduleStudentProblemSetAutoMount(scope, options);
            }
        });

        studentProblemSetAutoObserver.observe(observeTarget, {
            childList: true,
            subtree: true
        });

        document.dispatchEvent(new CustomEvent('lle:shogi-auto-mount-started', {
            detail: { observing: true }
        }));

        return { observing: true, stop: stopStudentProblemSetAutoMount };
    }

    function stopStudentProblemSetAutoMount(options = {}) {
        if (studentProblemSetAutoObserver) {
            studentProblemSetAutoObserver.disconnect();
            studentProblemSetAutoObserver = null;
        }
        if (options.destroyMounted === true) {
            destroyAutoMountedStudentProblemSets(options.scope || document);
        }
        document.dispatchEvent(new CustomEvent('lle:shogi-auto-mount-stopped', {
            detail: { destroyed: options.destroyMounted === true }
        }));
        return true;
    }


    function refreshStudentProblemSet(root, options = {}) {
        if (!(root instanceof Element) || !root.matches('[data-lle-shogi-problem-set]')) {
            throw new Error('再読込する詰将棋問題セットが見つかりません。');
        }
        const instance = root.__lleShogiProblemSet;
        if (instance && typeof instance.destroy === 'function') instance.destroy();
        delete root.__lleShogiProblemSet;
        delete root.dataset.lleShogiMounted;
        delete root.dataset.lleShogiMountError;
        root.innerHTML = '';
        const result = autoMountStudentProblemSets(root.parentElement || document, options);
        const mounted = result.mounted.find(item => item.root === root) || null;
        root.dispatchEvent(new CustomEvent('lle:shogi-problem-set-refreshed', {
            bubbles: true,
            detail: { mounted: Boolean(mounted), error_count: result.errors.length }
        }));
        return mounted?.instance || null;
    }

    function destroyDetachedStudentProblemSets(scope = document) {
        const container = scope instanceof Element || scope instanceof Document ? scope : document;
        let destroyed = 0;
        document.querySelectorAll('[data-lle-shogi-problem-set][data-lle-shogi-mounted="1"]').forEach(root => {
            if (container instanceof Element && container !== root && !container.contains(root)) return;
            if (root.isConnected) return;
            const instance = root.__lleShogiProblemSet;
            if (instance && typeof instance.destroy === 'function') instance.destroy();
            delete root.__lleShogiProblemSet;
            delete root.dataset.lleShogiMounted;
            destroyed += 1;
        });
        return destroyed;
    }

    function initStudentProblemSetAutoMount() {
        if (document.documentElement?.dataset?.lleShogiAutoMount === 'false') return;
        startStudentProblemSetAutoMount();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStudentProblemSetAutoMount, { once: true });
    } else {
        initStudentProblemSetAutoMount();
    }



    function createProblemSetLifecycleManager(options = {}) {
        const registry = new Map();
        const debug = options.debug === true;
        const emit = (name, detail = {}) => {
            document.dispatchEvent(new CustomEvent(name, { detail }));
            if (debug && console?.debug) console.debug(`[LLEShogi] ${name}`, detail);
        };
        const identify = root => {
            if (!(root instanceof Element)) return null;
            if (!root.dataset.lleShogiInstanceId) {
                root.dataset.lleShogiInstanceId = `lle-shogi-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
            }
            return root.dataset.lleShogiInstanceId;
        };
        const get = target => {
            const id = typeof target === 'string' ? target : identify(target);
            return id ? registry.get(id) || null : null;
        };
        const register = (root, instance, meta = {}) => {
            const id = identify(root);
            if (!id) throw new Error('問題セットの登録対象が不正です。');
            const current = registry.get(id);
            if (current && current.state !== 'destroyed') return current;
            const record = {
                id,
                root,
                instance,
                state: 'active',
                mounted_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
                meta: { ...meta },
                error: null
            };
            registry.set(id, record);
            emit('lle:shogi-lifecycle-mounted', { id, meta: record.meta });
            return record;
        };
        const setState = (target, state, error = null) => {
            const record = get(target);
            if (!record) return null;
            record.state = state;
            record.updated_at = new Date().toISOString();
            record.error = error ? String(error?.message || error) : null;
            emit('lle:shogi-lifecycle-state-changed', { id: record.id, state, error: record.error });
            return record;
        };
        const destroy = (target, reason = 'manual') => {
            const record = get(target);
            if (!record || record.state === 'destroyed') return false;
            try {
                if (record.instance && typeof record.instance.destroy === 'function') record.instance.destroy();
            } finally {
                record.state = 'destroyed';
                record.updated_at = new Date().toISOString();
                if (record.root) {
                    delete record.root.__lleShogiProblemSet;
                    delete record.root.dataset.lleShogiMounted;
                }
                emit('lle:shogi-lifecycle-destroyed', { id: record.id, reason });
            }
            return true;
        };
        const cleanupDetached = () => {
            let count = 0;
            registry.forEach(record => {
                if (record.state !== 'destroyed' && record.root && !record.root.isConnected) {
                    if (destroy(record.id, 'detached')) count += 1;
                }
            });
            return count;
        };
        const inspect = () => Array.from(registry.values()).map(record => ({
            id: record.id,
            state: record.state,
            connected: Boolean(record.root?.isConnected),
            mounted_at: record.mounted_at,
            updated_at: record.updated_at,
            error: record.error,
            meta: deepClone(record.meta)
        }));
        const clearDestroyed = () => {
            let count = 0;
            registry.forEach((record, id) => {
                if (record.state === 'destroyed') {
                    registry.delete(id);
                    count += 1;
                }
            });
            return count;
        };
        return { register, get, setState, destroy, cleanupDetached, inspect, clearDestroyed };
    }

    const defaultProblemSetLifecycleManager = createProblemSetLifecycleManager();

    function getMountedProblemSets() {
        return defaultProblemSetLifecycleManager.inspect();
    }

    function destroyProblemSetInstance(target, reason = 'manual') {
        return defaultProblemSetLifecycleManager.destroy(target, reason);
    }

    function createProblemSetHealthMonitor(options = {}) {
        const manager = options.lifecycleManager || defaultProblemSetLifecycleManager;
        const intervalMs = Math.max(1000, Number(options.intervalMs || 5000));
        const autoRecover = options.autoRecover !== false;
        const maxRecoveries = Math.max(1, Number(options.maxRecoveries || 3));
        const recoveries = new Map();
        let timer = null;
        let running = false;

        const diagnose = () => {
            const records = manager.inspect();
            const issues = [];
            records.forEach(record => {
                if (!record.connected && record.state !== 'destroyed') {
                    issues.push({ id: record.id, type: 'detached', severity: 'warning' });
                }
                if (record.state === 'error') {
                    issues.push({ id: record.id, type: 'runtime_error', severity: 'error', message: record.error });
                }
            });
            document.querySelectorAll('[data-lle-shogi-problem-set]').forEach(root => {
                if (root.dataset.lleShogiMounted !== '1' && !root.dataset.lleShogiMountError) {
                    issues.push({ id: root.dataset.lleShogiInstanceId || null, type: 'not_mounted', severity: 'warning', root });
                }
                if (root.dataset.lleShogiMountError) {
                    issues.push({ id: root.dataset.lleShogiInstanceId || null, type: 'mount_error', severity: 'error', root });
                }
            });
            return { checked_at: new Date().toISOString(), total: records.length, issues };
        };

        const recover = report => {
            let recovered = 0;
            if (!autoRecover) return recovered;
            report.issues.forEach(issue => {
                if (!issue.root || !issue.root.isConnected) return;
                const key = issue.id || issue.root.dataset.lleShogiInstanceId || 'unknown';
                const count = recoveries.get(key) || 0;
                if (count >= maxRecoveries) return;
                try {
                    refreshStudentProblemSet(issue.root, options.mountOptions || {});
                    recoveries.set(key, count + 1);
                    recovered += 1;
                } catch (error) {
                    recoveries.set(key, count + 1);
                    issue.recovery_error = String(error?.message || error);
                }
            });
            return recovered;
        };

        const run = () => {
            const report = diagnose();
            const detachedDestroyed = manager.cleanupDetached();
            const recovered = recover(report);
            const detail = { ...report, recovered, detached_destroyed: detachedDestroyed };
            document.dispatchEvent(new CustomEvent('lle:shogi-health-checked', { detail }));
            return detail;
        };

        const start = () => {
            if (running) return false;
            running = true;
            run();
            timer = window.setInterval(run, intervalMs);
            document.dispatchEvent(new CustomEvent('lle:shogi-health-monitor-started', { detail: { interval_ms: intervalMs } }));
            return true;
        };

        const stop = () => {
            if (!running) return false;
            running = false;
            if (timer) window.clearInterval(timer);
            timer = null;
            document.dispatchEvent(new CustomEvent('lle:shogi-health-monitor-stopped'));
            return true;
        };

        return {
            start,
            stop,
            run,
            diagnose,
            getState: () => ({ running, interval_ms: intervalMs, recoveries: Object.fromEntries(recoveries) })
        };
    }


    /**
     * 生徒が問題の途中で画面を離れた場合に、局面・棋譜・経過時間を復元するための保存領域。
     * サーバー保存前の一時データに限定し、学習結果やポイント付与データとは分離して扱う。
     */
    function createStudentSessionDraftStorage(options={}) {
        const storage = options.storage || (typeof window !== 'undefined' ? window.localStorage : null);
        const namespace = String(options.namespace || 'lle_shogi_session_draft');
        const maxAgeMs = Math.max(60_000, Number(options.max_age_ms || 7 * 24 * 60 * 60 * 1000));
        const schemaVersion = 1;
        const keyFor = (studentId, setId, problemId) => [namespace, studentId || 'guest', setId || 'default', problemId || 'unknown'].map(encodeURIComponent).join(':');
        const nowIso = () => new Date().toISOString();
        const clone = value => deepClone(value);

        const normalize = raw => {
            const value = raw && typeof raw === 'object' ? raw : {};
            return {
                schema_version: schemaVersion,
                student_id: value.student_id ?? null,
                problem_set_id: value.problem_set_id ?? null,
                problem_id: value.problem_id ?? null,
                sfen: String(value.sfen || ''),
                moves: Array.isArray(value.moves) ? clone(value.moves) : [],
                elapsed_ms: Math.max(0, Number(value.elapsed_ms || 0)),
                hint_level: Math.max(0, Number(value.hint_level || 0)),
                mistake_count: Math.max(0, Number(value.mistake_count || 0)),
                undo_count: Math.max(0, Number(value.undo_count || 0)),
                started_at: value.started_at || nowIso(),
                updated_at: nowIso(),
                extra: value.extra && typeof value.extra === 'object' ? clone(value.extra) : {}
            };
        };

        const isExpired = draft => {
            const updated = Date.parse(draft?.updated_at || '');
            return !Number.isFinite(updated) || Date.now() - updated > maxAgeMs;
        };

        const save = raw => {
            if (!storage?.setItem) return { saved: false, reason: 'storage_unavailable' };
            const draft = normalize(raw);
            if (!draft.problem_id) return { saved: false, reason: 'problem_id_required' };
            const key = keyFor(draft.student_id, draft.problem_set_id, draft.problem_id);
            storage.setItem(key, JSON.stringify(draft));
            document?.dispatchEvent?.(new CustomEvent('lle:shogi-session-draft-saved', { detail: clone(draft) }));
            return { saved: true, key, draft: clone(draft) };
        };

        const load = ({student_id=null, problem_set_id=null, problem_id=null}={}) => {
            if (!storage?.getItem || !problem_id) return null;
            const key = keyFor(student_id, problem_set_id, problem_id);
            try {
                const draft = JSON.parse(storage.getItem(key) || 'null');
                if (!draft || isExpired(draft)) {
                    storage.removeItem?.(key);
                    return null;
                }
                return clone(draft);
            } catch (_) {
                storage.removeItem?.(key);
                return null;
            }
        };

        const clear = ({student_id=null, problem_set_id=null, problem_id=null}={}) => {
            if (!storage?.removeItem || !problem_id) return false;
            const key = keyFor(student_id, problem_set_id, problem_id);
            const existed = storage.getItem?.(key) !== null;
            storage.removeItem(key);
            if (existed) document?.dispatchEvent?.(new CustomEvent('lle:shogi-session-draft-cleared', { detail: { student_id, problem_set_id, problem_id } }));
            return existed;
        };

        const list = () => {
            if (!storage?.length || !storage?.key) return [];
            const prefix = `${namespace}:`;
            const drafts = [];
            const expiredKeys = [];
            for (let i = 0; i < storage.length; i += 1) {
                const key = storage.key(i);
                if (!key || !key.startsWith(prefix)) continue;
                try {
                    const draft = JSON.parse(storage.getItem(key) || 'null');
                    if (!draft || isExpired(draft)) expiredKeys.push(key);
                    else drafts.push(clone(draft));
                } catch (_) {
                    expiredKeys.push(key);
                }
            }
            expiredKeys.forEach(key => storage.removeItem?.(key));
            return drafts.sort((a,b) => String(b.updated_at).localeCompare(String(a.updated_at)));
        };

        const clearAll = () => {
            const prefix = `${namespace}:`;
            const keys = [];
            if (storage?.length && storage?.key) {
                for (let i = 0; i < storage.length; i += 1) {
                    const key = storage.key(i);
                    if (key?.startsWith(prefix)) keys.push(key);
                }
            }
            keys.forEach(key => storage.removeItem?.(key));
            return keys.length;
        };

        return { save, load, clear, list, clearAll, schemaVersion, maxAgeMs };
    }

    window.LLEShogiMate={
        defaultConfig:()=>deepClone(defaultConfig()),
        normalizeConfig:raw=>deepClone(normalizeConfig(raw)),
        validateSolutionRoute:raw=>deepClone(validateSolutionRoute(raw)),
        mountEditor:(root,config,onChange)=>new ShogiComponentEditor(root,config,onChange),
        mountPlayer,
        parseSfen:sfen=>deepClone(parseSfen(sfen)),
        generateSfen:(board,hands,turn,moveNumber)=>generateSfen(board,hands,turn,moveNumber),
        createEngine:config=>new ShogiEngine(config),
        createPuzzleSession:config=>new ShogiPuzzleSession(config),
        normalizeProblemSet:(raw,options)=>deepClone(normalizeProblemSet(raw,options)),
        validateProblemSet:(raw,options)=>deepClone(validateProblemSet(raw,options)),
        normalizeProblemMetadata:(problem,set,index=0)=>deepClone(normalizeProblemMetadata(problem,set||{},index)),
        abilityDefinitions:()=>deepClone(ABILITY_KEYS.map(key=>({key,label:ABILITY_LABELS[key]}))),
        problemSetSchema:()=>({name:PROBLEM_SET_SCHEMA,version:PROBLEM_SET_SCHEMA_VERSION}),
        createProblemRepository,
        createProblemAdminSession,
        createProblemAdminController,
        createProblemApiSync,
        createProblemPreviewSession,
        createProblemPublicationWorkflow,
        createStudentLearningBridge,
        createLifeForceLearningSync,
        createStudentLearningDashboard,
        createClassroomLearningReport,
        createLearningInterventionPlanner,
        createPersonalizedLearningPlan,
        createGuardianLearningReport,
        createGuardianReportDeliveryManager,
        createGuardianReportBatchProcessor,
        createGuardianReportDeliveryAudit,
        createGuardianReportDeliveryDashboard,
        createGuardianReportDeliveryAlertMonitor,
        createGuardianDeliveryAlertActionManager,
        createGuardianDeliveryOperationsDashboard,
        createGuardianDeliveryOperationsReport,
        createPhase2ReadinessAudit,
        createPhase2ReleaseGate,
        createPhase2ReleasePackage,
        createPhase2AcceptanceTestRunner,
        createProblemSetNavigator,
        mountProblemSetNavigator,
        createProblemSetResult,
        mountProblemSetResult,
        createProblemSetCompletionController,
        mountStudentProblemSet,
        autoMountStudentProblemSets,
        destroyAutoMountedStudentProblemSets,
        startStudentProblemSetAutoMount,
        stopStudentProblemSetAutoMount,
        scheduleStudentProblemSetAutoMount,
        refreshStudentProblemSet,
        retryFailedStudentProblemSets,
        destroyDetachedStudentProblemSets,
        createProblemSetLifecycleManager,
        getMountedProblemSets,
        destroyProblemSetInstance,
        createProblemSetHealthMonitor,
        createStudentSessionDraftStorage,
        loadProblemSet,
        mountProblemSet,
        summarizeProblemResults: (results,total)=>deepClone(summarizeProblemResults(results,total)),
        createProblemSetStorage,
        buildProblemSetAnalytics: (set,results)=>deepClone(buildProblemSetAnalytics(normalizeProblemSet(set),results)),
        evaluateAchievements: (set,results,unlocked)=>deepClone(evaluateAchievements(normalizeProblemSet(set),results,unlocked)),
        achievements: ()=>deepClone(SHOGI_ACHIEVEMENTS.map(({ test, ...achievement }) => achievement)),
        createLearningLogSync,
        formatJapaneseMove,
        createReplaySession,
        exportReplayRecord
    };
})();
