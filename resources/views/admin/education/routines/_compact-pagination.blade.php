@if($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, $current - 2);
        $end = min($last, $current + 2);
    @endphp
    <nav class="er-pagination" aria-label="ページ移動">
        <span class="er-pagination-summary">
            全{{ number_format($paginator->total()) }}件中
            {{ number_format($paginator->firstItem() ?? 0) }}〜{{ number_format($paginator->lastItem() ?? 0) }}件
        </span>
        <div class="er-pagination-links">
            @if($paginator->onFirstPage())
                <span class="disabled">＜</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">＜</a>
            @endif

            @if($start > 1)
                <a href="{{ $paginator->url(1) }}">1</a>
                @if($start > 2)<span class="ellipsis">…</span>@endif
            @endif

            @for($page = $start; $page <= $end; $page++)
                @if($page === $current)
                    <span class="active" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}">{{ $page }}</a>
                @endif
            @endfor

            @if($end < $last)
                @if($end < $last - 1)<span class="ellipsis">…</span>@endif
                <a href="{{ $paginator->url($last) }}">{{ $last }}</a>
            @endif

            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">＞</a>
            @else
                <span class="disabled">＞</span>
            @endif
        </div>
    </nav>
@else
    <div class="er-pagination single">
        <span class="er-pagination-summary">全{{ number_format($paginator->total()) }}件</span>
    </div>
@endif
