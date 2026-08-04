{{-- Addon manager split-catalog page. Layout CSS is scoped to `.am-page` and
     lives in resources/css/addons.css, registered as a Filament asset by
     AddonManagerServiceProvider (plain custom-property CSS, no Tailwind build). --}}
@php
    $catalog = $this->catalogState();
    $rows = $this->listing();
    $counts = $this->tabCounts();
    $sel = $this->selected();
    $cats = $this->categories();
    $registryHost = parse_url((string) config('addon-manager.registry_url'), PHP_URL_HOST) ?: 'the registry';
@endphp

<x-filament-panels::page>

    <div class="am-page">
        {{-- Header: sync status, refresh, upload --}}
        <div class="am-head">
            <p class="am-sync">
                @if ($catalog['synced_at'])
                    {{ __('addon-manager::addons.synced_when', ['when' => \Illuminate\Support\Carbon::parse($catalog['synced_at'])->diffForHumans()]) }}
                @else
                    {{ __('addon-manager::addons.not_synced') }}
                @endif
                · <button type="button" wire:click="refreshCatalog" class="am-link">{{ __('addon-manager::addons.refresh') }}</button>
                @if ($catalog['stale'])
                    <span class="am-stale">({{ __('addon-manager::addons.showing_cached') }})</span>
                @endif
            </p>
            <div>{{ $this->uploadZipAction }}</div>
        </div>

        {{-- Tabs --}}
        <div class="am-tabs">
            @foreach (['browse' => __('addon-manager::addons.browse_registry'), 'updates' => __('addon-manager::addons.updates'), 'installed' => __('addon-manager::addons.installed_tab')] as $key => $label)
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
                        placeholder="{{ __('addon-manager::addons.search_addons', ['count' => $counts['browse']]) }}">
                    <select wire:model.live="category" class="am-select">
                        <option value="">{{ __('addon-manager::addons.all_categories') }}</option>
                        @foreach ($cats as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>

                <ul class="am-rows">
                    @forelse ($rows as $row)
                        <li wire:key="{{ $row['id'] }}" wire:click="select(@js($row['id']))"
                            wire:keydown.enter="select(@js($row['id']))" wire:keydown.space.prevent="select(@js($row['id']))"
                            tabindex="0" role="button" aria-label="{{ $row['name'] }}"
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
                                        <span class="am-status-update">↑ {{ __('addon-manager::addons.update_available') }}</span> · {{ $row['id'] }}
                                    @elseif ($row['installed'] && ! $row['enabled'])
                                        <span class="am-status-disabled">◯ {{ __('addon-manager::addons.disabled') }}</span> · {{ $row['id'] }}
                                    @elseif ($row['installed'])
                                        <span class="am-status-installed">✓ {{ __('addon-manager::addons.installed') }}</span> · {{ $row['id'] }}
                                    @else
                                        {{ $row['id'] }}@if ($row['installs']) · {{ $row['installs'] }} {{ __('addon-manager::addons.installs') }}@endif
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="am-empty">{{ __('addon-manager::addons.no_addons_found') }}</li>
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
                                    @if ($sel['publisher']) · {{ __('addon-manager::addons.by') }} <strong>{{ $sel['publisher'] }}</strong>@endif
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
                                        <span class="am-bundled">{{ __('addon-manager::addons.bundled') }}</span>
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
                            <dt>{{ __('addon-manager::addons.installed') }}</dt>
                            <dd>{{ $sel['installed_version'] ? 'v'.$sel['installed_version'] : '—' }}</dd>
                        </div>
                        <div class="am-fact">
                            <dt>{{ __('addon-manager::addons.latest') }}</dt>
                            <dd class="@if ($sel['update_available']) am-warn @endif">
                                {{ $sel['latest_version'] ? 'v'.$sel['latest_version'] : '—' }}@if ($sel['update_available']) ↑@endif
                            </dd>
                        </div>
                        <div class="am-fact">
                            <dt>{{ __('addon-manager::addons.requires') }}</dt>
                            <dd>
                                @if ($sel['min_php']) php ≥{{ $sel['min_php'] }} @endif
                                @if ($sel['min_phpvms']) · vms ≥{{ $sel['min_phpvms'] }} @endif
                                @if ($sel['min_php'] || $sel['min_phpvms'])
                                    <span class="@if ($sel['compatible']) am-ok @else am-warn @endif">{{ $sel['compatible'] ? '✓' : '✕' }}</span>
                                @else — @endif
                            </dd>
                        </div>
                        <div class="am-fact">
                            <dt>{{ __('addon-manager::addons.channel') }}</dt>
                            <dd>{{ $sel['release']['channel'] ?? 'stable' }}</dd>
                        </div>
                    </dl>

                    {{-- Release history (lazy; omitted when the fetch fails) --}}
                    @php $releases = is_array($sel['release']['releases'] ?? null) ? $sel['release']['releases'] : []; @endphp
                    @if ($releases !== [])
                        <h3 class="am-section-label">{{ __('addon-manager::addons.releases') }}</h3>
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
                        {{ __('addon-manager::addons.verified_note', ['host' => $registryHost]) }}
                        @if ($sel['repository_url'] ?? false)
                            · <a class="am-link" href="{{ $sel['repository_url'] }}" target="_blank" rel="noopener">{{ __('addon-manager::addons.repository') }} ↗</a>
                        @endif
                    </p>
                @else
                    <p class="am-empty">{{ __('addon-manager::addons.select_an_addon') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
