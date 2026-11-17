{{--
    One card body: head, description, state badges, a meta line pinned to the
    bottom, and the install bar while a job runs. The record actions (the card
    footer) are rendered by the table, under this. Classes are the
    .addon-card__* block in theme.css.
--}}
@php
    $row = $getRecord();
    $card = $getState();
    $version = $row['installed_version'] ?: $row['latest_version'];
    $meta = implode(' · ', array_filter([$row['publisher'], $row['category']])) ?: $row['id'];
@endphp

<div @class(['addon-card', 'addon-card--dim' => $row['installed'] && ! $row['enabled']])>
    <div class="addon-card__head">
        <span @class(['addon-plate', 'addon-plate--'.$row['tint'] => ! $row['icon'], 'addon-plate--icon' => $row['icon']])>
            @if ($row['icon'])
                <img src="{{ $row['icon'] }}" alt="">
            @else
                {{ $row['monogram'] }}
            @endif
        </span>

        <span class="addon-card__title">
            <span class="addon-card__name">
                <span class="addon-card__name-text">{{ $row['name'] }}</span>
                @if ($row['official'])
                    <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::ShieldCheckLight"
                        class="addon-card__official" role="img" :aria-label="__('addon-manager::addons.official_hint')" />
                @endif
            </span>
            <span class="addon-card__meta">{{ $meta }}</span>
        </span>
    </div>

    @if ($row['description'])
        <p class="addon-card__desc">{{ $row['description'] }}</p>
    @endif

    @if ($card['badges'] !== [])
        <div class="addon-card__badges">
            @foreach ($card['badges'] as $badge)
                <x-filament::badge :color="$badge['color']" size="sm">{{ $badge['label'] }}</x-filament::badge>
            @endforeach
        </div>
    @endif

    <div class="addon-card__facts">
        <span class="addon-card__version">
            @if ($row['update_available'])
                {{ $row['installed_version'] }} &rarr; <b>{{ $row['latest_version'] }}</b>
            @else
                {{ $version }}
            @endif
        </span>

        @if ($card['installs'])
            <span>&middot; {{ $card['installs'] }}</span>
        @endif
    </div>

    @if ($card['progress'])
        <div class="addon-card__progress" wire:poll.3s>
            <span class="dist__track">
                <span class="dist__fill" style="width: {{ $card['progress']['pct'] }}%"></span>
            </span>
            <span>{{ $card['progress']['message'] }}, {{ $card['progress']['pct'] }}%</span>
        </div>
    @endif
</div>
