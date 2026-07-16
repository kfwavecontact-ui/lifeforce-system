@if ($paginator->hasPages())
    @once
        <style>
            .routine-custom-pagination {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 14px 12px;
                border-top: 1px solid #dbe5f3;
                background: #ffffff;
            }

            .routine-custom-pagination__count {
                flex-shrink: 0;
                color: #344054;
                font-size: 13px;
                font-weight: 600;
                white-space: nowrap;
            }

            .routine-custom-pagination__nav {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                flex-wrap: wrap;
                gap: 6px;
                margin-left: auto;
            }

            .routine-custom-pagination__link,
            .routine-custom-pagination__current,
            .routine-custom-pagination__disabled,
            .routine-custom-pagination__dots {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 34px;
                height: 34px;
                padding: 0 10px;
                border-radius: 7px;
                font-size: 13px;
                font-weight: 600;
                line-height: 1;
                text-decoration: none;
                box-sizing: border-box;
            }

            .routine-custom-pagination__link {
                border: 1px solid #cbdaf0;
                background: #ffffff;
                color: #155eef;
                transition:
                    background-color 0.15s ease,
                    border-color 0.15s ease,
                    color 0.15s ease;
            }

            .routine-custom-pagination__link:hover {
                border-color: #155eef;
                background: #eff6ff;
                color: #004eeb;
            }

            .routine-custom-pagination__current {
                border: 1px solid #155eef;
                background: #155eef;
                color: #ffffff;
            }

            .routine-custom-pagination__disabled {
                border: 1px solid #e4e7ec;
                background: #f8fafc;
                color: #98a2b3;
                cursor: not-allowed;
            }

            .routine-custom-pagination__dots {
                min-width: 24px;
                padding: 0 4px;
                color: #667085;
            }

            @media (max-width: 768px) {
                .routine-custom-pagination {
                    align-items: flex-start;
                    flex-direction: column;
                }

                .routine-custom-pagination__nav {
                    justify-content: flex-start;
                    margin-left: 0;
                }
            }
        </style>
    @endonce

    <div class="routine-custom-pagination">
        <div class="routine-custom-pagination__count">
            全 {{ number_format($paginator->total()) }} 件
        </div>

        <nav
            class="routine-custom-pagination__nav"
            role="navigation"
            aria-label="ページネーション"
        >
            @if ($paginator->onFirstPage())
                <span
                    class="routine-custom-pagination__disabled"
                    aria-disabled="true"
                >
                    前へ
                </span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    class="routine-custom-pagination__link"
                    rel="prev"
                >
                    前へ
                </a>
            @endif

            @php
                $currentPage = $paginator->currentPage();
                $lastPage = $paginator->lastPage();
                $startPage = max(1, $currentPage - 1);
                $endPage = min($lastPage, $currentPage + 1);
            @endphp

            @if ($startPage > 1)
                <a
                    href="{{ $paginator->url(1) }}"
                    class="routine-custom-pagination__link"
                    aria-label="1ページ目へ移動"
                >
                    1
                </a>

                @if ($startPage > 2)
                    <span class="routine-custom-pagination__dots">…</span>
                @endif
            @endif

            @for ($page = $startPage; $page <= $endPage; $page++)
                @if ($page === $currentPage)
                    <span
                        class="routine-custom-pagination__current"
                        aria-current="page"
                    >
                        {{ $page }}
                    </span>
                @else
                    <a
                        href="{{ $paginator->url($page) }}"
                        class="routine-custom-pagination__link"
                        aria-label="{{ $page }}ページ目へ移動"
                    >
                        {{ $page }}
                    </a>
                @endif
            @endfor

            @if ($endPage < $lastPage)
                @if ($endPage < $lastPage - 1)
                    <span class="routine-custom-pagination__dots">…</span>
                @endif

                <a
                    href="{{ $paginator->url($lastPage) }}"
                    class="routine-custom-pagination__link"
                    aria-label="{{ $lastPage }}ページ目へ移動"
                >
                    {{ $lastPage }}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    class="routine-custom-pagination__link"
                    rel="next"
                >
                    次へ
                </a>
            @else
                <span
                    class="routine-custom-pagination__disabled"
                    aria-disabled="true"
                >
                    次へ
                </span>
            @endif
        </nav>
    </div>
@endif