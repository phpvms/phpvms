{{--
    The catalog rows, ten to a page.

    Official add-ons lead the shelf and carry a star; the weighting is on browse
    order only — once someone types, results rank by match, because a search is a
    lookup and not a shelf (see Addons::listing()).

    The monogram plate's tint marks the add-on's CATEGORY, which holds still.
    Colour on this page is otherwise spent only on selection and on state.
--}}
@php
    $paginator = $this->paginator();
    $selected = $this->selected();
    // Fixed map, so a category always gets the same plate wherever it appears.
    // The hues are the theme's shared category tints (see "Category tints" in
    // theme.css) — the same set the overview strip's icons use.
    $tints = [
        'operations'   => 'overview__icon--blue',
        'dispatch'     => 'overview__icon--blue',
        'acars'        => 'overview__icon--blue',
        'pilots'       => 'overview__icon--teal',
        'awards'       => 'overview__icon--teal',
        'finance'      => 'overview__icon--amber',
        'integration'  => 'overview__icon--amber',
        'integrations' => 'overview__icon--amber',
        'system'       => 'overview__icon--violet',
        'reporting'    => 'overview__icon--rose',
    ];
@endphp

<div class="addon-list">
    @forelse ($paginator as $row)
        <button type="button" wire:key="addon-{{ $row['id'] }}" wire:click="select(@js($row['id']))"
            @class(['addon-row', 'addon-row--dim' => $row['installed'] && ! $row['enabled']])
            aria-current="{{ $selected && $selected['id'] === $row['id'] ? 'true' : 'false' }}">
            <span @class(['addon-tile', $tints[\Illuminate\Support\Str::lower($row['category'])] ?? ''])>
                @if ($row['icon'])
                    <img src="{{ $row['icon'] }}" alt="">
                @else
                    {{ $row['monogram'] }}
                @endif
            </span>

            <span class="addon-row__body">
                <span class="addon-row__top">
                    <span class="addon-row__title">
                        @if ($row['official'])
                            <x-filament::icon :icon="\Daljo25\FilamentTablerIcons\Enums\TablerIcon::StarFilled"
                                class="official" role="img"
                                :aria-label="__('addon-manager::addons.official_hint')" />
                        @endif
                        <span class="addon-row__name">{{ $row['name'] }}</span>
                    </span>

                    @if (! $row['compatible'])
                        <span class="chip chip--bad">{{ $row['incompatible_reason'] }}</span>
                    @elseif ($row['update_available'])
                        <span class="chip chip--warn">{{ __('addon-manager::addons.updates') }}</span>
                    @elseif ($row['bundled'])
                        <span class="chip chip--plain chip--mute">{{ __('addon-manager::addons.bundled_short') }}</span>
                    @elseif ($row['installed'] && ! $row['enabled'])
                        <span class="chip chip--mute">{{ __('addon-manager::addons.state_disabled') }}</span>
                    @endif
                </span>

                @if ($row['description'])
                    <span class="addon-row__desc">{{ $row['description'] }}</span>
                @endif

                @php $progress = \Modules\AddonManager\Support\InstallProgress::get($row['id']); @endphp

                @if ($progress && ! in_array($progress['status'], ['done', 'error'], true))
                    {{-- Mid-install. The bar replaces the row's meta line rather
                         than opening a modal over the list. --}}
                    <span class="addon-row__progress" wire:poll.3s>
                        <span class="dist__track">
                            <span class="dist__fill" style="width: {{ $progress['pct'] }}%"></span>
                        </span>
                        <span class="addon-row__meta">{{ $progress['message'] }}, {{ $progress['pct'] }}%</span>
                    </span>
                @else
                    <span class="addon-row__meta">
                        @php $version = $row['installed_version'] ?: $row['latest_version']; @endphp
                        <span><span class="id">{{ $row['id'] }}</span>{{ $version ? ',' : '' }}</span>
                        @if ($version)
                            <span class="id">{{ $version }}</span>
                        @endif
                        @if ($row['installed_version'] && $row['update_available'])
                            &rarr;<span class="id">{{ $row['latest_version'] }}</span>
                        @endif
                    </span>
                @endif
            </span>
        </button>
    @empty
        <div class="empty">
            <x-filament::icon :icon="\Daljo25\FilamentTablerIcons\Enums\TablerIcon::Puzzle" />
            <strong>{{ __('addon-manager::addons.no_results_title') }}</strong>
            <span>{{ __('addon-manager::addons.no_results_hint') }}</span>
        </div>
    @endforelse
</div>

@if ($paginator->total() > 0)
    <div class="panel__foot">
        <span class="result-count">
            {{ __('addon-manager::addons.showing_range', [
                'from'  => $paginator->firstItem() ?? 0,
                'to'    => $paginator->lastItem() ?? 0,
                'total' => $paginator->total(),
            ]) }}
        </span>

        <span class="fi-ac">
            <x-filament::button color="gray" size="sm" :disabled="$paginator->onFirstPage()"
                wire:click="$set('page', {{ max(1, $this->page - 1) }})">
                {{ __('addon-manager::addons.previous') }}
            </x-filament::button>
            <x-filament::button color="gray" size="sm" :disabled="! $paginator->hasMorePages()"
                wire:click="$set('page', {{ $this->page + 1 }})">
                {{ __('addon-manager::addons.next') }}
            </x-filament::button>
        </span>
    </div>
@endif
