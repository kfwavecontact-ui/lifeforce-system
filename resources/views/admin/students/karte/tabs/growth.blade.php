{{-- 成長タブ --}}

<div class="growth-page">
    <div class="growth-layout">

        {{-- 左側：成長の軌跡 --}}
        <div class="growth-main">

            {{-- 脳開発 --}}
            <section class="growth-timeline-card brain">
                <div class="timeline-header">
                    <div class="timeline-title">
                        <span class="timeline-icon">🧠</span>
                        <span>脳開発　成長の軌跡</span>
                    </div>
                    <span class="timeline-count">過去20件</span>
                </div>

                <div class="timeline-list">
                    @foreach([
                        ['2026/06/10', '脳開発レベルが <strong>Lv.42</strong> になりました'],
                        ['2026/06/01', 'マトリクス <strong>Lv.4</strong> を達成！'],
                        ['2026/05/21', 'ストループ <strong>Lv.6</strong> を達成！'],
                        ['2026/05/10', '絵探し集中問題に取り組み成功！'],
                        ['2026/05/01', '連続30日ルーティンを達成！'],
                        ['2026/04/20', '数列推理 <strong>Lv.3</strong> を達成！'],
                        ['2026/04/05', '画像瞬間記憶 <strong>Lv.4</strong> を達成！'],
                        ['2026/03/15', '脳開発レベルが <strong>Lv.39</strong> になりました'],
                        ['2026/03/01', '聴覚選択注意 <strong>Lv.4</strong> を達成！'],
                        ['2026/02/15', 'フラッシュ暗算 <strong>Lv.5</strong> を達成！'],
                        ['2026/02/01', '集中力が <strong>45秒 → 60秒</strong> に向上！'],
                        ['2026/01/15', '位置記憶 <strong>Lv.4</strong> を達成！'],
                        ['2026/01/01', '連続20日ルーティンを達成！'],
                        ['2025/12/15', '数字瞬間記憶 <strong>Lv.5</strong> を達成！'],
                        ['2025/12/01', '脳開発レベルが <strong>Lv.36</strong> になりました'],
                        ['2025/11/15', 'シルエット <strong>Lv.4</strong> を達成！'],
                        ['2025/11/01', '数列推理 <strong>Lv.2</strong> を達成！'],
                        ['2025/10/15', '画像瞬間記憶 <strong>Lv.3</strong> を達成！'],
                        ['2025/10/01', '脳開発レベルが <strong>Lv.33</strong> になりました'],
                        ['2025/09/15', '数字瞬間記憶 <strong>Lv.4</strong> を達成！'],
                    ] as [$date, $text])
                        <div class="timeline-item">
                            <span class="timeline-dot"></span>
                            <div class="timeline-date">{{ $date }}</div>
                            <div class="timeline-text">{!! $text !!}</div>
                        </div>
                    @endforeach
                </div>

                <div class="timeline-more">
                    <a href="#">すべての履歴を見る　〉</a>
                </div>
            </section>

            {{-- 将棋 --}}
            <section class="growth-timeline-card shogi">
                <div class="timeline-header">
                    <div class="timeline-title">
                        <span class="timeline-icon">王</span>
                        <span>将棋　成長の軌跡</span>
                    </div>
                    <span class="timeline-count">過去20件</span>
                </div>

                <div class="timeline-list">
                    @foreach([
                        ['2026/06/10', '将棋の棋力が <strong>3級</strong> に上がりました'],
                        ['2026/04/20', '詰パズルを突破！'],
                        ['2026/03/10', '将棋大会でベスト8！'],
                        ['2026/01/05', '将棋の棋力が <strong>4級</strong> に上がりました'],
                        ['2025/12/10', '詰将棋で3手詰を10問連続正解！'],
                        ['2025/11/01', '将棋の棋力が <strong>6級</strong> に上がりました'],
                        ['2025/09/20', '初めて大会に出場！'],
                        ['2025/09/01', '将棋の棋力が <strong>8級</strong> に上がりました'],
                        ['2025/07/15', '初めて対局で勝利！'],
                        ['2025/06/01', '将棋の棋力が <strong>10級</strong> に上がりました'],
                        ['2025/05/01', '将棋の学習を開始！'],
                        ['2025/04/15', '駒の動かし方をすべてマスター！'],
                        ['2025/04/01', '将棋入門コースを開始！'],
                        ['2025/03/20', '詰将棋1手詰をクリア！'],
                        ['2025/03/01', '対局ルールを理解！'],
                        ['2025/02/15', '駒の動かし方を学習開始！'],
                        ['2025/02/01', '将棋アプリで練習開始！'],
                        ['2025/01/15', '将棋に興味を持つ！'],
                        ['2025/01/01', '将棋の学習を検討し始める！'],
                        ['2024/12/20', '将棋の体験授業に参加！'],
                    ] as [$date, $text])
                        <div class="timeline-item">
                            <span class="timeline-dot"></span>
                            <div class="timeline-date">{{ $date }}</div>
                            <div class="timeline-text">{!! $text !!}</div>
                        </div>
                    @endforeach
                </div>

                <div class="timeline-more orange">
                    <a href="#">すべての履歴を見る　〉</a>
                </div>
            </section>

            {{-- 資格取得 --}}
            <section class="growth-timeline-card qualification">
                <div class="timeline-header">
                    <div class="timeline-title">
                        <span class="timeline-icon">📖</span>
                        <span>資格取得　成長の軌跡</span>
                    </div>
                    <span class="timeline-count">過去20件</span>
                </div>

                <div class="timeline-list">
                    @foreach([
                        ['2026/06/15', '英検 <strong>5級</strong> に合格しました'],
                        ['2026/04/20', '漢検 <strong>8級</strong> に合格しました'],
                        ['2026/02/01', '英検5級の一次試験に合格！'],
                        ['2025/12/10', '漢検8級の勉強を開始！'],
                        ['2025/11/15', '英検5級の勉強を開始！'],
                        ['2025/10/05', '漢検9級に合格しました'],
                        ['2025/09/01', '英単語学習を開始！'],
                        ['2025/08/01', '漢検9級の勉強を開始！'],
                        ['2025/07/01', '計算力トレーニングを開始！'],
                        ['2025/06/01', '資格学習を開始！'],
                        ['2025/05/10', '英単語アプリを開始！'],
                        ['2025/04/20', '漢字の読み書きを強化開始！'],
                        ['2025/04/01', '学習計画を立てる！'],
                        ['2025/03/15', '目標を設定！'],
                        ['2025/03/01', '英語学習に興味を持つ！'],
                        ['2025/02/15', '漢字学習に興味を持つ！'],
                        ['2025/01/15', '保護者と目標を確認！'],
                        ['2025/01/01', '将来のために勉強を頑張ると宣言！'],
                        ['2024/12/20', '本格的に学習を始めることを決意！'],
                        ['2024/12/01', '勉強に前向きになり始める！'],
                    ] as [$date, $text])
                        <div class="timeline-item">
                            <span class="timeline-dot"></span>
                            <div class="timeline-date">{{ $date }}</div>
                            <div class="timeline-text">{!! $text !!}</div>
                        </div>
                    @endforeach
                </div>

                <div class="timeline-more green">
                    <a href="#">すべての履歴を見る　〉</a>
                </div>
            </section>

        </div>

        {{-- 右側：総合成長サマリー --}}
        <aside class="growth-summary">

            <div class="summary-title">
                <span>🌱</span>
                <span>総合成長サマリー</span>
            </div>

            <section class="summary-card">
                <h4>基本情報</h4>

                <div class="summary-row">
                    <span class="summary-label">📅 入会日</span>
                    <strong>2023年4月10日</strong>
                </div>

                <div class="summary-row">
                    <span class="summary-label">⏱ 在籍期間</span>
                    <strong>1年2ヶ月</strong>
                </div>
            </section>

            <section class="summary-card">
                <h4>分野別の現在の状況</h4>

                <div class="status-row">
                    <span class="status-icon brain-icon">🧠</span>
                    <div>
                        <p>脳開発レベル</p>
                        <strong>Lv.42</strong>
                        <small>前回比 +3 ↑</small>
                    </div>
                </div>

                <div class="status-row">
                    <span class="status-icon shogi-icon">王</span>
                    <div>
                        <p>将棋の棋力</p>
                        <strong>3級</strong>
                        <small>前回比 +2級 ↑</small>
                    </div>
                </div>

                <div class="status-row">
                    <span class="status-icon qualification-icon">📖</span>
                    <div>
                        <p>取得資格</p>
                        <strong>英検5級<br>漢検8級</strong>
                        <small>前回比 +1個 ↑</small>
                    </div>
                </div>
            </section>

            <section class="summary-card">
                <h4>今月のハイライト</h4>

                <div class="highlight-item">
                    <span>🧠</span>
                    <p>脳開発レベル <strong>Lv.42</strong> 達成！</p>
                </div>

                <div class="highlight-item">
                    <span>王</span>
                    <p>将棋の棋力が <strong>3級</strong> に！</p>
                </div>

                <div class="highlight-item">
                    <span>📖</span>
                    <p>英検5級に合格！</p>
                </div>

                <div class="highlight-item">
                    <span>📅</span>
                    <p>ルーティン継続 <strong>96日</strong>！</p>
                </div>
            </section>

        </aside>

    </div>
</div>