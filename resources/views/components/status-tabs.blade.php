@once
    @push('styles')
        <style>
            .status-tabs {
                display: inline-flex;
                max-width: 100%;
                padding: 5px;
                border: 1px solid #e2e5eb;
                border-radius: 14px;
                background: #fff;
                box-shadow: 0 2px 4px rgba(17, 24, 39, .03);
            }

            .status-tabs__list {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
                padding: 0;
                margin: 0;
                list-style: none;
            }

            .status-tabs__link {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 9px;
                min-height: 42px;
                padding: 10px 16px;
                border-radius: 9px;
                color: #626975;
                font-size: 13px;
                font-weight: 600;
                line-height: 1.4;
                text-decoration: none;
                white-space: nowrap;
                transition: background-color .18s ease, color .18s ease, box-shadow .18s ease;
            }

            .status-tabs__icon { color: #8a919d; font-size: 13px; }

            .status-tabs__count {
                min-width: 24px;
                padding: 2px 7px;
                border-radius: 6px;
                background: #eef0f4;
                color: #626975;
                font-size: 11px;
                font-weight: 700;
                line-height: 18px;
                text-align: center;
                font-variant-numeric: tabular-nums;
            }

            .status-tabs__link:hover { background: #f3f4f6; color: #111827; }
            .status-tabs__link:focus-visible { outline: 2px solid #a17d2b; outline-offset: 2px; }

            .status-tabs__link[aria-current="page"] {
                background: #18181b;
                color: #fff;
                box-shadow: 0 2px 5px rgba(17, 24, 39, .15);
            }

            .status-tabs__link[aria-current="page"] .status-tabs__icon { color: #e4c878; }
            .status-tabs__link[aria-current="page"] .status-tabs__count { background: #39393c; color: #fff; }
            .status-tabs__link--danger:hover { background: #fff1f2; color: #b42338; }
            .status-tabs__link--danger[aria-current="page"] { background: #a82438; }
            .status-tabs__link--danger[aria-current="page"] .status-tabs__icon { color: #ffd6dc; }
            .status-tabs__link--danger[aria-current="page"] .status-tabs__count { background: rgba(255, 255, 255, .18); }

            @media (max-width: 575.98px) {
                .status-tabs, .status-tabs__list { width: 100%; }
                .status-tabs__list > li { flex: 1 1 auto; }
                .status-tabs__link { gap: 6px; padding: 10px; font-size: 12px; }
            }

            @media (prefers-reduced-motion: reduce) {
                .status-tabs__link { transition: none; }
            }
        </style>
    @endpush
@endonce

<nav class="status-tabs" aria-label="{{ $label }}">
    <ul class="status-tabs__list">
        @foreach($tabs as $item)
            <li>
                <a class="status-tabs__link {{ ($item['tone'] ?? '') === 'danger' ? 'status-tabs__link--danger' : '' }}"
                    href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif>
                    <i class="fas fa-fw {{ $item['icon'] }} status-tabs__icon" aria-hidden="true"></i>
                    <span>{{ $item['label'] }}</span>
                    @if(isset($item['count']))
                        <span class="status-tabs__count">{{ number_format($item['count'], 0, ',', '.') }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>
