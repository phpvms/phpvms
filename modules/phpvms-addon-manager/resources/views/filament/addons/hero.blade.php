{{--
    The selection's identity band, flush at the top of the detail section.

    The actions sit here with the add-on's name rather than at the foot of the
    panel: the reader should not have to scroll past a release table to reach
    the button the page exists for.
--}}
@php
    $sel = $this->selected();
    $facts = array_filter([
        $sel['publisher'] ?: null,
        $sel['category'] ?: null,
        $sel['license'] ?: null,
    ]);
@endphp

<div class="addon-hero">
    <span class="addon-tile addon-tile--lg overview__icon--{{ $sel['tint'] }}">
        @if ($sel['icon'])
            <img src="{{ $sel['icon'] }}" alt="">
        @else
            {{ $sel['monogram'] }}
        @endif
    </span>

    <span class="fi-min-w-0">
        <h2 class="addon-hero__name">
            @if ($sel['official'])
                <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::StarFill"
                    class="official" role="img" :aria-label="__('addon-manager::addons.official_hint')" />
            @endif
            {{ $sel['name'] }}
        </h2>

        <p class="addon-hero__id">
            @if ($sel['official'])
                <span class="chip chip--plain chip--mute">{{ __('addon-manager::addons.official') }}</span>
            @endif
            {{-- Says why Disable and Remove are absent below. Without it a
                 bundled add-on just looks like one whose buttons went missing. --}}
            @if ($sel['bundled'])
                <span class="chip chip--plain chip--mute">{{ __('addon-manager::addons.bundled') }}</span>
            @endif
            <span><span class="id">{{ $sel['id'] }}</span>{{ $facts === [] ? '' : ',' }}</span>
            @foreach ($facts as $fact)
                <span>{{ $fact }}{{ $loop->last ? '' : ',' }}</span>
            @endforeach
        </p>
    </span>

    <span class="addon-hero__actions">
        @if (! $sel['installed'] || $sel['update_available'])
            {{ $this->installAction }}
        @endif

        @if ($sel['installed'] && ! $sel['bundled'])
            @if ($sel['enabled'])
                <x-filament::button color="gray" size="sm"
                    :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::PowerLight"
                    wire:click="disable({!! \Illuminate\Support\Js::from($sel['installed_key'], JSON_UNESCAPED_SLASHES) !!})">
                    {{ __('addon-manager::addons.disable') }}
                </x-filament::button>
            @else
                <x-filament::button color="gray" size="sm"
                    :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::PowerLight"
                    wire:click="enable({!! \Illuminate\Support\Js::from($sel['installed_key'], JSON_UNESCAPED_SLASHES) !!})">
                    {{ __('addon-manager::addons.enable') }}
                </x-filament::button>
            @endif

            {{ ($this->deleteAction)(['key' => $sel['installed_key']]) }}
        @endif
    </span>
</div>

@if ($sel['description'])
    <p class="addon-lede">{{ $sel['description'] }}</p>
@endif
