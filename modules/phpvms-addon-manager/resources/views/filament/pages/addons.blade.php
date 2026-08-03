{{-- Addon manager split-catalog page. All layout CSS is scoped to `.am-page`
     and shipped inline (module views are NOT in the app's Tailwind @source set),
     which also scopes the Hanken Grotesk font trial to this page only (§6.6). --}}
@php
    $catalog = $this->catalogState();
    $rows = $this->listing();
    $counts = $this->tabCounts();
    $sel = $this->selected();
    $cats = $this->categories();
    $registryHost = parse_url((string) config('addon-manager.registry_url'), PHP_URL_HOST) ?: 'the registry';
@endphp

<x-filament-panels::page>
    {{-- Condensed system-font stack (no web font needed), scoped to this page. --}}
    <style>
        .am-page { font-family: Bahnschrift, "DIN Alternate", "Franklin Gothic Medium", "Nimbus Sans Narrow", sans-serif-condensed, sans-serif; font-weight:normal; font-size:1rem; line-height:1.5; }
        .am-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; margin-bottom:.75rem; }
        .am-sync { font-size:.9rem; color:var(--color-gray-500); }
        .am-link { color:var(--color-primary-600); }
        .am-link:hover { text-decoration:underline; }
        .am-stale { color:var(--color-gray-400); }
        .am-tabs { display:flex; gap:1.5rem; border-bottom:1px solid var(--color-gray-200); margin-bottom:0; }
        .dark .am-tabs { border-bottom-color:rgb(255 255 255 / 8%); }
        .am-tab { padding-bottom:.625rem; font-size:1rem; color:var(--color-gray-500); border-bottom:2px solid transparent; margin-bottom:-1px; }
        .am-tab:hover { color:var(--color-gray-800); }
        .dark .am-tab:hover { color:var(--color-gray-200); }
        .am-tab-active { color:var(--color-primary-600); border-bottom-color:var(--color-primary-500); font-weight:600; }
        .dark .am-tab-active { color:var(--color-primary-300); }
        .am-count { font-size:.78rem; padding:.05rem .4rem; border-radius:9999px; background:var(--color-gray-100); color:var(--color-gray-500); margin-left:.25rem; }
        .dark .am-count { background:rgb(255 255 255 / 8%); color:var(--color-gray-400); }

        .am-split { display:grid; grid-template-columns:minmax(0,24rem) minmax(0,1fr); border:1px solid var(--color-gray-200); border-top:0; border-radius:0 0 .5rem .5rem; overflow:hidden; }
        .dark .am-split { border-color:rgb(255 255 255 / 8%); }
        @media (max-width:1024px){ .am-split { grid-template-columns:1fr; } }

        .am-list-col { border-right:1px solid var(--color-gray-200); }
        .dark .am-list-col { border-right-color:rgb(255 255 255 / 8%); }
        .am-list-controls { display:flex; gap:.5rem; padding:.75rem; border-bottom:1px solid var(--color-gray-100); }
        .dark .am-list-controls { border-bottom-color:rgb(255 255 255 / 5%); }
        .am-input, .am-select { font-size:.95rem; padding:.5rem .625rem; border-radius:.5rem; border:1px solid var(--color-gray-200); background:var(--color-gray-50); color:inherit; }
        .dark .am-input, .dark .am-select { background:var(--color-gray-800); border-color:rgb(255 255 255 / 8%); }
        .am-input { flex:1; min-width:0; }

        .am-rows { list-style:none; margin:0; padding:0; }
        .am-row { display:flex; gap:.75rem; padding:.75rem 1rem; border-bottom:1px solid var(--color-gray-100); cursor:pointer; }
        .dark .am-row { border-bottom-color:rgb(255 255 255 / 5%); }
        .am-row:hover { background:var(--color-gray-50); }
        .dark .am-row:hover { background:rgb(255 255 255 / 3%); }
        .am-row-selected, .dark .am-row-selected { background:var(--color-primary-50); box-shadow:inset 0 0 0 1px color-mix(in srgb, var(--color-primary-500) 30%, transparent); }
        .dark .am-row-selected { background:color-mix(in srgb, var(--color-primary-500) 8%, transparent); }
        .am-row-dim { opacity:.6; }

        .am-tile { width:2.5rem; height:2.5rem; border-radius:.5rem; flex-shrink:0; display:grid; place-items:center; font-weight:700; font-size:1rem; color:#fff; background:linear-gradient(135deg, var(--color-primary-500), var(--color-primary-800)); overflow:hidden; }
        .am-tile img { width:100%; height:100%; object-fit:cover; }
        .am-tile-lg { width:3.25rem; height:3.25rem; font-size:1.3rem; border-radius:.75rem; }

        .am-row-body { min-width:0; flex:1; }
        .am-row-top { display:flex; align-items:baseline; justify-content:space-between; gap:.5rem; }
        .am-name { font-weight:600; font-size:1.1rem; }
        .am-ver { font-size:.9rem; color:var(--color-gray-500); font-variant-numeric:tabular-nums; }
        .am-desc { font-size:.95rem; color:var(--color-gray-500); margin-top:.15rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .am-row-meta { font-size:.82rem; color:var(--color-gray-400); margin-top:.35rem; }
        .am-status-update { color:rgb(217 119 6); font-weight:600; }
        .am-status-installed { color:rgb(5 150 105); font-weight:600; }
        .am-status-disabled { color:var(--color-gray-500); font-weight:600; }
        .am-status-incompat { color:rgb(225 29 72); font-weight:600; }

        .am-detail { padding:1.5rem; }
        .am-detail-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
        .am-detail-title { display:flex; gap:.75rem; align-items:center; }
        .am-detail-name { font-weight:700; font-size:1.6rem; }
        .am-detail-by { font-size:.95rem; color:var(--color-gray-500); }
        .am-detail-id { font-family:var(--font-mono), monospace; color:var(--color-gray-600); }
        .dark .am-detail-id { color:var(--color-gray-400); }
        .am-actions { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
        .am-desc-full { margin-top:1rem; font-size:1.05rem; line-height:1.6; color:var(--color-gray-700); max-width:65ch; }
        .dark .am-desc-full { color:var(--color-gray-300); }

        .am-facts { margin-top:1.25rem; display:grid; grid-template-columns:repeat(4,1fr); gap:1px; border:1px solid var(--color-gray-200); background:var(--color-gray-200); border-radius:.75rem; overflow:hidden; }
        .dark .am-facts { border-color:rgb(255 255 255 / 8%); background:rgb(255 255 255 / 8%); }
        @media (max-width:640px){ .am-facts { grid-template-columns:repeat(2,1fr); } }
        .am-fact { background:#fff; padding:.75rem 1rem; }
        .dark .am-fact { background:var(--color-gray-900); }
        .am-fact dt { font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; color:var(--color-gray-400); }
        .am-fact dd { margin-top:.25rem; font-family:var(--font-mono), monospace; font-size:.95rem; }
        .am-ok { color:rgb(5 150 105); }
        .am-warn { color:rgb(217 119 6); }

        .am-section-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; color:var(--color-gray-400); margin:1.5rem 0 .5rem; }
        .am-releases { border:1px solid var(--color-gray-200); border-radius:.75rem; overflow:hidden; }
        .dark .am-releases { border-color:rgb(255 255 255 / 8%); }
        .am-release { display:flex; align-items:center; justify-content:space-between; padding:.6rem 1rem; border-bottom:1px solid var(--color-gray-100); font-size:.95rem; }
        .dark .am-release { border-bottom-color:rgb(255 255 255 / 5%); }
        .am-release:last-child { border-bottom:0; }
        .am-release-ver { font-family:var(--font-mono), monospace; font-weight:600; }
        .am-release-note { color:var(--color-gray-500); margin-left:.75rem; }
        .am-release-date { font-size:.85rem; color:var(--color-gray-400); }

        .am-footer { margin-top:1.5rem; font-size:.85rem; color:var(--color-gray-400); }
        .am-empty { padding:2rem 1rem; text-align:center; color:var(--color-gray-400); font-size:1rem; }

        .am-progress { width:100%; }
        .am-progress-bar { height:.5rem; border-radius:9999px; background:var(--color-gray-200); overflow:hidden; }
        .dark .am-progress-bar { background:var(--color-gray-700); }
        .am-progress-fill { height:100%; background:var(--color-primary-500); transition:width .3s ease; }
        .am-progress-msg { font-size:.85rem; color:var(--color-gray-500); margin-top:.35rem; }
        .am-err { color:rgb(225 29 72); font-size:.95rem; }

        .am-btn { font-size:.95rem; padding:.5rem .875rem; border-radius:.5rem; border:1px solid var(--color-gray-300); color:var(--color-gray-700); background:#fff; }
        .dark .am-btn { background:transparent; border-color:rgb(255 255 255 / 12%); color:var(--color-gray-300); }
        .am-btn:hover { background:var(--color-gray-50); }
        .dark .am-btn:hover { background:rgb(255 255 255 / 5%); }
        .am-bundled { font-size:.88rem; color:var(--color-gray-500); padding:.4rem .7rem; border:1px dashed var(--color-gray-300); border-radius:.5rem; }
        .dark .am-bundled { border-color:rgb(255 255 255 / 12%); }

        /* Full-height split browser on desktop: fill from under the Filament chrome down to the
           viewport bottom, then scroll each pane on its own so a long list/detail doesn't clip.
           Scoped to the width where the split is two columns; below that it collapses to one
           column and stays content-height (natural page scroll).
           ponytail: 10rem is the eyeballed topbar + page-heading + padding offset — nudge it if
           there's a gap or an extra scrollbar on a given theme. */
        @media (min-width:1025px) {
            .am-page { display:flex; flex-direction:column; min-height:calc(100dvh - 10rem); }
            .am-split { flex:1; min-height:0; }
            .am-list-col { display:flex; flex-direction:column; min-height:0; }
            .am-rows { flex:1; overflow-y:auto; }
            .am-detail { overflow-y:auto; min-height:0; }
        }
    </style>

    <div class="am-page">
        {{-- Header: sync status, refresh, upload --}}
        <div class="am-head">
            <p class="am-sync">
                @if ($catalog['synced_at'])
                    {{ __('Registry synced :when', ['when' => \Illuminate\Support\Carbon::parse($catalog['synced_at'])->diffForHumans()]) }}
                @else
                    {{ __('Registry not synced yet') }}
                @endif
                · <button type="button" wire:click="refreshCatalog" class="am-link">{{ __('refresh') }}</button>
                @if ($catalog['stale'])
                    <span class="am-stale">({{ __('showing cached') }})</span>
                @endif
            </p>
            <div>{{ $this->uploadZipAction }}</div>
        </div>

        {{-- Tabs --}}
        <div class="am-tabs">
            @foreach (['browse' => __('Browse registry'), 'updates' => __('Updates'), 'installed' => __('Installed')] as $key => $label)
                <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                    class="am-tab @if ($activeTab === $key) am-tab-active @endif">
                    {{ $label }}<span class="am-count">{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </div>

        <div class="am-split">
            {{-- List --}}
            <div class="am-list-col">
                <div class="am-list-controls">
                    <input type="search" wire:model.live.debounce.300ms="search" class="am-input"
                        placeholder="{{ __('Search :count addons…', ['count' => $counts['browse']]) }}">
                    <select wire:model.live="category" class="am-select">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($cats as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>

                <ul class="am-rows">
                    @forelse ($rows as $row)
                        <li wire:key="{{ $row['id'] }}" wire:click="select(@js($row['id']))"
                            class="am-row @if ($sel && $sel['id'] === $row['id']) am-row-selected @endif @unless ($row['compatible']) am-row-dim @endunless">
                            <div class="am-tile">
                                @if ($row['icon'])
                                    <img src="{{ $row['icon'] }}" alt="">
                                @else
                                    {{ $row['monogram'] }}
                                @endif
                            </div>
                            <div class="am-row-body">
                                <div class="am-row-top">
                                    <span class="am-name">{{ $row['name'] }}</span>
                                    @if ($row['latest_version'])
                                        <span class="am-ver">v{{ $row['latest_version'] }}</span>
                                    @endif
                                </div>
                                @if ($row['description'])
                                    <p class="am-desc">{{ $row['description'] }}</p>
                                @endif
                                <div class="am-row-meta">
                                    @if (! $row['compatible'])
                                        <span class="am-status-incompat">{{ $row['incompatible_reason'] }}</span>
                                    @elseif ($row['update_available'])
                                        <span class="am-status-update">↑ {{ __('update available') }}</span> · {{ $row['id'] }}
                                    @elseif ($row['installed'] && ! $row['enabled'])
                                        <span class="am-status-disabled">◯ {{ __('disabled') }}</span> · {{ $row['id'] }}
                                    @elseif ($row['installed'])
                                        <span class="am-status-installed">✓ {{ __('installed') }}</span> · {{ $row['id'] }}
                                    @else
                                        {{ $row['id'] }}@if ($row['installs']) · {{ $row['installs'] }} {{ __('installs') }}@endif
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="am-empty">{{ __('No addons found.') }}</li>
                    @endforelse
                </ul>
            </div>

            {{-- Detail --}}
            <div class="am-detail">
                @if ($sel)
                    <div class="am-detail-head">
                        <div class="am-detail-title">
                            <div class="am-tile am-tile-lg">
                                @if ($sel['icon'])<img src="{{ $sel['icon'] }}" alt="">@else{{ $sel['monogram'] }}@endif
                            </div>
                            <div>
                                <h2 class="am-detail-name">{{ $sel['name'] }}</h2>
                                <p class="am-detail-by">
                                    <span class="am-detail-id">{{ $sel['id'] }}</span>
                                    @if ($sel['publisher']) · {{ __('by') }} <strong>{{ $sel['publisher'] }}</strong>@endif
                                    @if ($sel['category']) · {{ $sel['category'] }}@endif
                                    @if ($sel['license']) · {{ $sel['license'] }}@endif
                                </p>
                            </div>
                        </div>

                        {{-- Primary action + progress. Poll only while a job is
                             actually mid-flight — otherwise the page is static. --}}
                        @php $progress = $sel['progress']; @endphp
                        <div class="am-actions" @if ($progress && ! in_array($progress['status'], ['done', 'error'], true)) wire:poll.3s @endif>
                            @if ($progress && ! in_array($progress['status'], ['done', 'error'], true))
                                <div class="am-progress">
                                    <div class="am-progress-bar"><div class="am-progress-fill" style="width: {{ $progress['pct'] }}%"></div></div>
                                    <p class="am-progress-msg">{{ $progress['message'] }}</p>
                                </div>
                            @else
                                @if (! $sel['installed'] || $sel['update_available'])
                                    {{ $this->installAction }}
                                @endif
                                @if ($sel['installed'])
                                    @if ($sel['bundled'])
                                        <span class="am-bundled">{{ __('bundled with phpVMS') }}</span>
                                    @else
                                        @if ($sel['enabled'])
                                            <button type="button" wire:click="disable(@js($sel['installed_key']))" class="am-btn">{{ __('common.disable') }}</button>
                                        @else
                                            <button type="button" wire:click="enable(@js($sel['installed_key']))" class="am-btn">{{ __('common.enable') }}</button>
                                        @endif
                                        {{ ($this->deleteAction)(['key' => $sel['installed_key']]) }}
                                    @endif
                                @endif
                                @if ($progress && $progress['status'] === 'error')
                                    <p class="am-err">{{ $progress['message'] }}</p>
                                @endif
                            @endif
                        </div>
                    </div>

                    @if ($sel['description'])
                        <p class="am-desc-full">{{ $sel['description'] }}</p>
                    @endif

                    {{-- Facts strip --}}
                    <dl class="am-facts">
                        <div class="am-fact">
                            <dt>{{ __('installed') }}</dt>
                            <dd>{{ $sel['installed_version'] ? 'v'.$sel['installed_version'] : '—' }}</dd>
                        </div>
                        <div class="am-fact">
                            <dt>{{ __('latest') }}</dt>
                            <dd class="@if ($sel['update_available']) am-warn @endif">
                                {{ $sel['latest_version'] ? 'v'.$sel['latest_version'] : '—' }}@if ($sel['update_available']) ↑@endif
                            </dd>
                        </div>
                        <div class="am-fact">
                            <dt>{{ __('requires') }}</dt>
                            <dd>
                                @if ($sel['min_php']) php ≥{{ $sel['min_php'] }} @endif
                                @if ($sel['min_phpvms']) · vms ≥{{ $sel['min_phpvms'] }} @endif
                                @if ($sel['min_php'] || $sel['min_phpvms'])
                                    <span class="@if ($sel['compatible']) am-ok @else am-warn @endif">{{ $sel['compatible'] ? '✓' : '✕' }}</span>
                                @else — @endif
                            </dd>
                        </div>
                        <div class="am-fact">
                            <dt>{{ __('channel') }}</dt>
                            <dd>{{ $sel['release']['channel'] ?? 'stable' }}</dd>
                        </div>
                    </dl>

                    {{-- Release history (lazy; omitted when the fetch fails) --}}
                    @php $releases = is_array($sel['release']['releases'] ?? null) ? $sel['release']['releases'] : []; @endphp
                    @if ($releases !== [])
                        <h3 class="am-section-label">{{ __('Releases') }}</h3>
                        <ul class="am-releases">
                            @foreach ($releases as $r)
                                <li class="am-release">
                                    <span>
                                        <span class="am-release-ver">v{{ $r['version'] ?? '?' }}</span>
                                        <span class="am-release-note">{{ $r['summary'] ?? ($r['notes'] ?? '') }}</span>
                                    </span>
                                    @if (! empty($r['released_at']))
                                        <span class="am-release-date">{{ \Illuminate\Support\Carbon::parse($r['released_at'])->diffForHumans() }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Verification footer --}}
                    <p class="am-footer">
                        {{ __('sha256 verified · signed by :host', ['host' => $registryHost]) }}
                        @if ($sel['repository_url'] ?? false)
                            · <a class="am-link" href="{{ $sel['repository_url'] }}" target="_blank" rel="noopener">{{ __('repository') }} ↗</a>
                        @endif
                    </p>
                @else
                    <p class="am-empty">{{ __('Select an addon to see its details.') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
