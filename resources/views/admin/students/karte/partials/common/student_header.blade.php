<div class="karte-actions">
    <a href="{{ route('admin.students.karte.edit', $student) }}"
        class="btn btn-primary">
        編集
    </a>
    <button class="btn btn-outline">✉️ 通知を送る</button>
    <button class="btn btn-warning">⏸️ 休会処理</button>
    <button class="btn btn-danger">👤 退会処理</button>
</div>

<div class="karte-student-header">

    <div class="karte-profile-area">

        <div class="karte-photo">👦</div>

        <div class="karte-info-area">
            <div class="karte-name-row">
                <h2>{{ $student->last_name }} {{ $student->first_name }}</h2>
                <span>やまだ たろう</span>
                <strong>在籍中</strong>
            </div>

            <div class="karte-info-grid">
                <div><span>生徒ID</span><b>{{ $student->student_code }}</b></div>
                <div><span>入会日</span><b>{{ optional($student->enrolled_at)->format('Y/m/d') }}</b></div>

                <div><span>学年</span><b>小学4年生</b></div>
                <div><span>在籍期間</span><b>1年2ヶ月</b></div>

                <div><span>クラス</span><b>小4スタンダードクラス</b></div>
                <div><span>生年月日</span><b>2014/05/15（10歳）</b></div>

                <div><span>所属教室</span><b>柏校</b></div>
                <div><span>担当講師</span><b>田中 花子</b></div>
            </div>
        </div>

    </div>

    <div class="karte-kpi-area">

        <div class="karte-kpi">
            <span>📅 出席率</span>
            <strong>92.5%</strong>
            <small>出席37 / 欠席3</small>
        </div>

        <div class="karte-kpi">
            <span>🪙 保有ポイント</span>
            <strong>1,250pt</strong>
            <small class="green">今月 +150pt</small>
        </div>

        <div class="karte-kpi">
            <span>🏅 獲得バッジ数</span>
            <strong>14個</strong>
            <small class="green">今月 +2</small>
        </div>

        <div class="karte-kpi">
            <span>👑 現在の称号</span>
            <strong class="title">瞬間記憶王</strong>
            <small>最高ランク</small>
        </div>

    </div>

</div>