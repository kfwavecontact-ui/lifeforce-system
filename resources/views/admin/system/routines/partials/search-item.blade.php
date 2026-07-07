<form class="routine-search" method="GET" action="{{ route('admin.system.routines') }}">
    <input type="hidden" name="tab" value="items">
    <div class="search-card-header">
        <h3>検索条件</h3>
    </div>
    <div class="routine-search-grid item-grid">
        <label class="routine-keyword">
            <span>キーワード</span>
            <i class="fas fa-search"></i>
            <input name="item_keyword" value="{{ request('item_keyword') }}" placeholder="ID・名称・説明で検索">
        </label>
        <label><span>カテゴリ</span><select name="item_category"><option value="all">すべて</option>@foreach($categoryOptions as $option)<option value="{{ $option }}" @selected(request('item_category') === $option)>{{ $option }}</option>@endforeach</select></label>
        <label><span>対象学年</span><select name="item_grade"><option value="all">すべて</option>@foreach($gradeOptions as $option)<option value="{{ $option }}" @selected(request('item_grade') === $option)>{{ $option }}</option>@endforeach</select></label>
        <label><span>難易度</span><select name="item_difficulty"><option value="all">すべて</option>@foreach($difficultyOptions as $option)<option value="{{ $option }}" @selected((string) request('item_difficulty') === (string) $option)>★{{ $option }}</option>@endforeach</select></label>
        <label><span>学習ページ状態</span><select name="item_learning_status"><option value="all">すべて</option>@foreach($learningPageStatuses as $option)<option value="{{ $option }}" @selected(request('item_learning_status') === $option)>{{ $option }}</option>@endforeach</select></label>
        <label><span>お気に入り</span><select name="item_favorite"><option value="all">すべて</option><option value="yes" @selected(request('item_favorite') === 'yes')>登録済み</option></select></label>
        <label><span>よく使う</span><select name="item_frequent"><option value="all">すべて</option><option value="yes" @selected(request('item_frequent') === 'yes')>登録済み</option></select></label>
        <label><span>有効</span><select name="item_active"><option value="all">すべて</option><option value="active" @selected(request('item_active') === 'active')>有効</option><option value="inactive" @selected(request('item_active') === 'inactive')>無効</option></select></label>
    </div>
    <div class="routine-search-actions"><button type="submit">検索</button><a href="{{ route('admin.system.routines', ['tab' => 'items']) }}">リセット</a></div>
</form>
