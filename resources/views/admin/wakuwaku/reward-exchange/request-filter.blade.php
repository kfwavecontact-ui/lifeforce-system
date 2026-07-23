<section class="rex-filter-card">
    <form method="GET">
        <input name="keyword" value="{{ request('keyword') }}" placeholder="申請番号・生徒・商品コード・商品名">
        <select name="status">
            <option value="">すべての状態</option>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @isset($categories)
            <select name="category_id">
                <option value="">すべてのカテゴリ</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        @endisset
        <select name="delivery_method">
            <option value="">すべての受渡方法</option>
            <option value="classroom" @selected(request('delivery_method') === 'classroom')>教室受渡</option>
            <option value="shipping" @selected(request('delivery_method') === 'shipping')>発送</option>
        </select>
        <input type="date" name="from" value="{{ request('from') }}" aria-label="開始日">
        <input type="date" name="to" value="{{ request('to') }}" aria-label="終了日">
        <select name="per_page" aria-label="表示件数">
            @foreach([20, 30, 50] as $size)
                <option value="{{ $size }}" @selected((int) ($perPage ?? request('per_page', 30)) === $size)>{{ $size }}件表示</option>
            @endforeach
        </select>
        <button>検索</button>
        <a href="{{ url()->current() }}">クリア</a>
    </form>
</section>
