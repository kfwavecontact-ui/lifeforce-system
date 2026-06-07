@php
    $planName = match((int)$planId) {
        2 => '読書50冊達成',
        3 => '将棋100勝達成',
        default => '英検4級合格',
    };

    $progress = match((int)$planId) {
        2 => '42%',
        3 => '81%',
        default => '68%',
    };
@endphp

<div class="plan-detail-card">

    <div class="plan-detail-header">
        <div>
            <a href="#" class="back-link">← 一覧に戻る</a>
            <h3>{{ $planName }} <span class="plan-status">進行中</span></h3>
        </div>

        <div class="plan-actions">
            <button class="btn-sm">＋ マイルストーン追加</button>
            <button class="btn-sm">編集</button>
        </div>
    </div>

    <div class="plan-detail-grid">

        <div class="plan-info-box">
            <h4>計画情報</h4>
            <div class="plan-progress-circle">
                {{ $progress }}
            </div>
            <div class="info-row"><span>開始日</span><strong>2024/03/01</strong></div>
            <div class="info-row"><span>終了予定日</span><strong>2024/07/31</strong></div>
            <div class="info-row"><span>残り</span><strong class="danger-text">あと66日</strong></div>
        </div>

        <div class="milestone-box">
            <h4>マイルストーン（6件）</h4>

            <div class="milestone-line">
                <div class="milestone-step done">1</div>
                <div class="milestone-step done">2</div>
                <div class="milestone-step done">3</div>
                <div class="milestone-step current">4</div>
                <div class="milestone-step">5</div>
                <div class="milestone-step">6</div>
            </div>

            <div class="milestone-labels">
                <span>単語1周<br><small class="ms-complete">完了</small></span>
                <span>単語2周<br><small class="ms-complete">完了</small></span>
                <span>過去問1回目<br><small class="ms-complete">完了</small></span>
                <span>過去問2回目<br><small class="ms-current">進行中</small></span>
                <span>模試<br><small class="ms-pending">未着手</small></span>
                <span>本試験<br><small class="ms-pending">未着手</small></span>
            </div>
        </div>

    </div>

    <div class="task-section">
        <div class="card-header">
            <h4>タスク一覧</h4>
            <button class="btn-sm">＋ タスク追加</button>
        </div>

        <table class="plan-table">
            <thead>
                <tr>
                    <th>日付</th>
                    <th>タスク内容</th>
                    <th>ステータス</th>
                    <th>完了者</th>
                    <th>完了日時</th>
                    <th>メモ</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>5/15（水）</td>
                    <td>過去問2回目（1回目）</td>
                    <td><span class="task-status done">● 完了</span></td>
                    <td>山田 太郎</td>
                    <td>2024/05/15 19:30</td>
                    <td>正答率68%</td>
                </tr>
                <tr>
                    <td>5/22（水）</td>
                    <td>過去問2回目（2回目）</td>
                    <td><span class="task-status pending">● 未着手</span></td>
                    <td>—</td>
                    <td>—</td>
                    <td>時間を計って解く</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>