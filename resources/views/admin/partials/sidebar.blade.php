<aside class="sidebar">

    <div class="sidebar-logo">LifeForce Core</div>

    <div class="sidebar-search">
        <input
            type="text"
            id="sidebarSearchInput"
            placeholder="画面検索..."
            class="sidebar-search-input"
            autocomplete="off"
        >

        <div id="sidebarSearchPanel" class="sidebar-search-panel">
            <div id="sidebarSearchResults" class="sidebar-search-results"></div>

            <div id="sidebarRecentArea" class="sidebar-search-section">
                <div class="sidebar-search-section-title">最近開いた項目</div>

                <a href="#" class="sidebar-search-row">
                    <span class="sidebar-search-muted">Core ＞ ダッシュボード ＞ </span><span class="sidebar-search-strong">今日の教室</span>
                </a>
                <a href="#" class="sidebar-search-row">
                    <span class="sidebar-search-muted">Core ＞ 教室 ＞ </span><span class="sidebar-search-strong">教室一覧</span>
                </a>
                <a href="#" class="sidebar-search-row">
                    <span class="sidebar-search-muted">人 ＞ 生徒 ＞ </span><span class="sidebar-search-strong">生徒一覧</span>
                </a>
                <a href="#" class="sidebar-search-row">
                    <span class="sidebar-search-muted">Core ＞ スケジュール ＞ </span><span class="sidebar-search-strong">スケジュール</span>
                </a>
                <a href="#" class="sidebar-search-row">
                    <span class="sidebar-search-muted">人 ＞ フォロー ＞ </span><span class="sidebar-search-strong">フォロー一覧</span>
                </a>
                <a href="#" class="sidebar-search-row">
                    <span class="sidebar-search-muted">教育 ＞ ルーティン ＞ </span><span class="sidebar-search-strong">ルーティン割当</span>
                </a>
                <a href="#" class="sidebar-search-row">
                    <span class="sidebar-search-muted">運営 ＞ 会計 ＞ </span><span class="sidebar-search-strong">請求情報</span>
                </a>
                <a href="#" class="sidebar-search-row">
                    <span class="sidebar-search-muted">わくわく ＞ 商品交換所 ＞ </span><span class="sidebar-search-strong">申請情報</span>
                </a>
                <a href="#" class="sidebar-search-row">
                    <span class="sidebar-search-muted">システム ＞ 設定 ＞ </span><span class="sidebar-search-strong">バッジ管理</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Core --}}
    <div class="sidebar-group">
        <div class="sidebar-group-title">Core</div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-home"></i><span>ダッシュボード</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="Core ＞ ダッシュボード ＞ 今日の教室" data-info="今日の授業・予定・対応事項を確認します">
                <span>今日の教室</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="Core ＞ ダッシュボード ＞ 教室分析" data-info="教室運営状況を分析します">
                <span>教室分析</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="Core ＞ ダッシュボード ＞ 講師分析" data-info="講師ごとの成果を分析します">
                <span>講師分析</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="Core ＞ ダッシュボード ＞ 生徒分析" data-info="生徒の成長状況を分析します">
                <span>生徒分析</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="Core ＞ ダッシュボード ＞ マーケティング分析" data-info="集客活動の成果を分析します">
                <span>マーケティング分析</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="Core ＞ ダッシュボード ＞ 営業分析" data-info="体験・入会状況を分析します">
                <span>営業分析</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="Core ＞ ダッシュボード ＞ 経営分析" data-info="売上や利益を分析します">
                <span>経営分析</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="Core ＞ ダッシュボード ＞ カスタマーサクセス分析" data-info="満足度やフォロー状況を分析します">
                <span>カスタマーサクセス分析</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>

        <a href="#" class="sidebar-item" data-search-name="Core ＞ 教室" data-info="教室一覧を表示し、教室カルテへ移動します">
            <i class="fas fa-school"></i><span>教室</span>
        </a>

        <a href="#" class="sidebar-item" data-search-name="Core ＞ スケジュール ＞ スケジュール" data-info="教室・講師・生徒の予定を管理します">
            <i class="fas fa-calendar-alt"></i><span>スケジュール</span>
        </a>
    </div>

    {{-- 人 --}}
    <div class="sidebar-group">
        <div class="sidebar-group-title">人</div>

        <a href="#" class="sidebar-item" data-search-name="人 ＞ 生徒" data-info="生徒一覧を表示し、生徒カルテへ移動します">
            <i class="fas fa-user-graduate"></i><span>生徒</span>
        </a>

        <a href="#" class="sidebar-item" data-search-name="人 ＞ 講師" data-info="講師一覧を表示し、講師カルテへ移動します">
            <i class="fas fa-chalkboard-teacher"></i><span>講師</span>
        </a>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-lightbulb"></i><span>フォロー</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="人 ＞ フォロー ＞ 新規フォロー" data-info="新しいフォローを登録します">
                <span>新規フォロー</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="人 ＞ フォロー ＞ フォロー一覧" data-info="対応中のフォローを管理します">
                <span>フォロー一覧</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="人 ＞ フォロー ＞ フォロー履歴" data-info="過去の対応履歴を確認します">
                <span>フォロー履歴</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>
    </div>

    {{-- 教育 --}}
    <div class="sidebar-group">
        <div class="sidebar-group-title">教育</div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-sync-alt"></i><span>ルーティン</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem"
                data-search-name="教育 ＞ ルーティン ＞ 教室の取組状況"
                data-info="教室全体の実施状況を確認します">
                <span>教室の取組状況</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="#" class="sidebar-subitem"
                data-search-name="教育 ＞ ルーティン ＞ ルーティン履歴"
                data-info="過去の取組履歴を確認します">
                <span>ルーティン履歴</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="#" class="sidebar-subitem"
                data-search-name="教育 ＞ ルーティン ＞ ルーティン割当"
                data-info="生徒へルーティンを割り当てます">
                <span>ルーティン割当</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-book-open"></i><span>計画学習</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="教育 ＞ 計画学習 ＞ 教室の取組状況" data-info="計画学習の実施状況を確認します">
                <span>教室の取組状況</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="教育 ＞ 計画学習 ＞ 計画学習履歴" data-info="過去の計画学習を確認します">
                <span>計画学習履歴</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="教育 ＞ 計画学習 ＞ モデルプラン" data-info="全国の達成例を参考にします">
                <span>モデルプラン</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-flag"></i><span>イベント</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="教育 ＞ イベント ＞ 新規イベント" data-info="新しいイベントを作成します">
                <span>新規イベント</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="教育 ＞ イベント ＞ イベント一覧" data-info="開催予定イベントを管理します">
                <span>イベント一覧</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="教育 ＞ イベント ＞ イベント履歴" data-info="過去のイベントを確認します">
                <span>イベント履歴</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-trophy"></i><span>チャレンジ</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="教育 ＞ チャレンジ ＞ チャレンジ一覧" data-info="受験可能なチャレンジを確認します">
                <span>チャレンジ一覧</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="教育 ＞ チャレンジ ＞ チャレンジ予約" data-info="チャレンジ受験を予約します">
                <span>チャレンジ予約</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="教育 ＞ チャレンジ ＞ チャレンジ履歴" data-info="過去の受験結果を確認します">
                <span>チャレンジ履歴</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>
    </div>

    {{-- わくわく --}}
    <div class="sidebar-group">
        <div class="sidebar-group-title">わくわく</div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-coins"></i><span>ポイント</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ ポイント ＞ ポイント残高" data-info="現在のポイントを確認します">
                <span>ポイント残高</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ ポイント ＞ ポイント履歴" data-info="獲得・利用履歴を確認します">
                <span>ポイント履歴</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ ポイント ＞ ポイント調整" data-info="ポイントを追加・減算します">
                <span>ポイント調整</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-medal"></i><span>バッジ</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ バッジ ＞ バッジ一覧" data-info="バッジ一覧を確認します">
                <span>バッジ一覧</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ バッジ ＞ バッジ履歴" data-info="獲得履歴を確認します">
                <span>バッジ履歴</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-crown"></i><span>称号</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ 称号 ＞ 称号一覧" data-info="称号一覧を確認します">
                <span>称号一覧</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ 称号 ＞ 称号履歴" data-info="獲得履歴を確認します">
                <span>称号履歴</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-chart-bar"></i><span>ランキング</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ ランキング ＞ 生徒ランキング" data-info="生徒ランキングを確認します">
                <span>生徒ランキング</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ ランキング ＞ 講師ランキング" data-info="講師ランキングを確認します">
                <span>講師ランキング</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ ランキング ＞ 教室ランキング" data-info="教室ランキングを確認します">
                <span>教室ランキング</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-gift"></i><span>商品交換所</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ 商品交換所 ＞ 商品一覧" data-info="交換商品を確認します">
                <span>商品一覧</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ 商品交換所 ＞ 申請情報" data-info="交換申請を管理します">
                <span>申請情報</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ 商品交換所 ＞ 交換履歴" data-info="交換履歴を確認します">
                <span>交換履歴</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-shopping-cart"></i><span>ショップ</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ ショップ ＞ 商品一覧" data-info="販売商品を確認します">
                <span>商品一覧</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ ショップ ＞ 注文情報" data-info="注文状況を管理します">
                <span>注文情報</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="#" class="sidebar-subitem" data-search-name="わくわく ＞ ショップ ＞ 購入情報" data-info="購入履歴を確認します">
                <span>購入情報</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>
    </div>

    {{-- 運営 --}}
    <div class="sidebar-group">
        <div class="sidebar-group-title">運営</div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label">
                <i class="fas fa-yen-sign"></i>
                <span>教室会計</span>
            </span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="{{ route('admin.operations.classroom-accounting.tuition-enrollment-sales.index') }}"
                class="sidebar-subitem sidebar-income-item"
                data-search-name="運営 ＞ 教室会計 ＞ 授業料・入会金売上"
                data-info="授業料・入会金の売上を管理します">
                <span>授業料・入会金売上</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="{{ route('admin.operations.classroom-accounting.shop-sales.index') }}"
                class="sidebar-subitem sidebar-income-item"
                data-search-name="運営 ＞ 教室会計 ＞ ショップ売上"
                data-info="ショップ商品の売上を管理します">
                <span>ショップ売上</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="{{ route('admin.operations.classroom-accounting.event-sales.index') }}"
                class="sidebar-subitem sidebar-income-item"
                data-search-name="運営 ＞ 教室会計 ＞ イベント売上"
                data-info="イベントの売上を管理します">
                <span>イベント売上</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="{{ route('admin.operations.classroom-accounting.spot-sales.index') }}"
                class="sidebar-subitem sidebar-income-item"
                data-search-name="運営 ＞ 教室会計 ＞ スポット売上"
                data-info="教材費・検定費・特別講習などのスポット売上を管理します">
                <span>スポット売上</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="{{ route('admin.operations.classroom-accounting.refunds.index') }}"
                class="sidebar-subitem sidebar-expense-item"
                data-search-name="運営 ＞ 教室会計 ＞ 返金"
                data-info="返金情報を管理します">
                <span>返金</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="{{ route('admin.operations.classroom-accounting.point-product-costs.index') }}"
                class="sidebar-subitem sidebar-expense-item"
                data-search-name="運営 ＞ 教室会計 ＞ ポイント商品費用"
                data-info="ポイント交換商品の仕入・交換費用を管理します">
                <span>ポイント商品費用</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="{{ route('admin.operations.classroom-accounting.expenses.index') }}"
                class="sidebar-subitem sidebar-expense-item"
                data-search-name="運営 ＞ 教室会計 ＞ 経費"
                data-info="教室運営に関する経費を管理します">
                <span>経費</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="{{ route('admin.operations.classroom-accounting.transactions.index') }}"
                class="sidebar-subitem sidebar-account-book"
                data-search-name="運営 ＞ 教室会計 ＞ 会計台帳"
                data-info="収益・費用・返金・経費をまとめて管理します">
                <span>会計台帳</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label">
                <i class="fas fa-comment-dots"></i>
                <span>連絡</span>
            </span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="#"
                class="sidebar-subitem"
                data-search-name="運営 ＞ 連絡 ＞ 掲示板"
                data-info="教室全体・教室別・学年別・コース別・生徒個別のお知らせを管理します">
                <span>掲示板</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="#"
                class="sidebar-subitem"
                data-search-name="運営 ＞ 連絡 ＞ 一斉通知"
                data-info="生徒・保護者・講師へ一斉通知を配信します">
                <span>一斉通知</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>
    </div>

    {{-- システム --}}
    <div class="sidebar-group">
        <div class="sidebar-group-title">システム</div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-cog"></i><span>設定</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="{{ route('admin.system.courses') }}"
                class="sidebar-subitem"
                data-search-name="システム ＞ 設定 ＞ コース管理"
                data-info="コースと料金を管理します">
                <span>コース管理</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>

            <a href="{{ route('admin.system.challenges') }}" class="sidebar-subitem" data-search-name="システム ＞ 設定 ＞ チャレンジ管理" data-info="チャレンジ試験を管理します"><span>チャレンジ管理</span><i class="fas fa-circle-info sidebar-info"></i></a>
            <a href="{{ url('/admin/system/routine-management') }}"
                class="sidebar-subitem"
                data-search-name="システム ＞ 設定 ＞ ルーティン管理"
                data-info="共通ルーティン・ルーティンアイテムを管理します">
                <span>ルーティン管理</span>
                <i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="{{ route('admin.system.badges') }}" class="sidebar-subitem" data-search-name="システム ＞ 設定 ＞ バッジ管理" data-info="バッジ情報を管理します"><span>バッジ管理</span><i class="fas fa-circle-info sidebar-info"></i></a>


            <a href="{{ route('admin.system.titles') }}" class="sidebar-subitem" data-search-name="システム ＞ 設定 ＞ 称号管理" data-info="称号情報を管理します"><span>称号管理</span><i class="fas fa-circle-info sidebar-info"></i></a>
            <a href="{{ route('admin.system.permissions') }}" class="sidebar-subitem" data-search-name="システム ＞ 設定 ＞ 権限管理" data-info="ロール権限を管理します"><span>権限管理</span><i class="fas fa-circle-info sidebar-info"></i></a>
            <a href="{{ route('admin.system.master') }}" class="sidebar-subitem" data-search-name="システム ＞ 設定 ＞ マスタ管理" data-info="各種マスタを管理します"><span>マスタ管理</span><i class="fas fa-circle-info sidebar-info"></i></a>
        </div>

        <button class="sidebar-item sidebar-toggle" type="button">
            <span class="sidebar-toggle-label"><i class="fas fa-bell"></i><span>通知管理</span></span>
            <i class="fas fa-chevron-down sidebar-arrow"></i>
        </button>

        <div class="sidebar-submenu">
            <a href="{{ route('admin.system.notification-masters') }}"
                class="sidebar-subitem"
                data-search-name="システム ＞ 通知管理 ＞ 通知マスタ"
                data-info="通知の種類を管理します">
                    <span>通知マスタ</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="{{ route('admin.system.role-notification-settings') }}"
                class="sidebar-subitem"
                data-search-name="システム ＞ 通知管理 ＞ ロール別通知設定"
                data-info="ロールごとの通知表示を管理します">
                <span>ロール別通知設定</span><i class="fas fa-circle-info sidebar-info"></i>
            </a>
            <a href="{{ route('admin.system.notification-histories') }}"
                class="sidebar-subitem"
                data-search-name="システム ＞ 通知管理 ＞ 通知履歴"
                data-info="通知送信履歴を確認します">
                    <span>通知履歴</span>
                    <i class="fas fa-circle-info sidebar-info"></i>
            </a>
        </div>
    </div>

</aside>