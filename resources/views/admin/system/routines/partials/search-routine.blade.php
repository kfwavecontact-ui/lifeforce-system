<form class="routine-search" method="GET" action="{{ route('admin.system.routines') }}">
    <input type="hidden" name="tab" value="routines">
    <div class="search-card-header">
        <h3>検索条件</h3>
    </div>
    <div class="routine-search-grid routine-grid">
        <label class="routine-keyword">
            <span>キーワード</span>
            <i class="fas fa-search"></i>
            <input name="routine_keyword" value="{{ request('routine_keyword') }}" placeholder="ID・名称・説明で検索">
        </label>
        <label><span>対象学年</span><select name="routine_grade"><option value="all">すべて</option>@foreach($gradeOptions as $option)<option value="{{ $option }}" @selected(request('routine_grade') === $option)>{{ $option }}</option>@endforeach</select></label>
        <label><span>難易度</span><select name="routine_difficulty"><option value="all">すべて</option>@foreach($difficultyOptions as $option)<option value="{{ $option }}" @selected((string) request('routine_difficulty') === (string) $option)>★{{ $option }}</option>@endforeach</select></label>
        <label><span>使用中フラグ</span><select name="usage"><option value="all">すべて</option><option value="used" @selected(request('usage')==='used')>使用中</option><option value="unused" @selected(request('usage')==='unused')>未使用</option></select></label>
        <label><span>有効</span><select name="routine_active"><option value="all">すべて</option><option value="active" @selected(request('routine_active')==='active')>有効</option><option value="inactive" @selected(request('routine_active')==='inactive')>無効</option></select></label>
        <label><span>割当生徒数 下限</span><input type="number" name="assigned_min" value="{{ request('assigned_min') }}" placeholder="下限"></label>
        <label><span>割当生徒数 上限</span><input type="number" name="assigned_max" value="{{ request('assigned_max') }}" placeholder="上限"></label>
            <div class="routine-search-actions routine-search-actions-in-grid"><button type="submit">検索</button><a href="{{ route('admin.system.routines', ['tab' => 'routines']) }}">リセット</a></div>
    </div>
</form>
