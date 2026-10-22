@php
    /**
     * @var \Filament\Panel[] $panels
     * @var \Filament\Panel   $current
     */
    $panelLabel = static function (\Filament\Panel $panel): string {
        return $panel->getId() === 'admin'
            ? __('common.administration')
            : \Illuminate\Support\Str::headline($panel->getId());
    };

    $brandName ??= 'phpvms';
    $addonsUrl ??= null;
    // Null means no airline logo uploaded: both marks below keep their
    // built-in artwork.
    $brandMark ??= null;

    // Colours for non-admin panel marks, from the v1 mockup; cycles if there
    // are more panels than colours.
    $markColors = ['#6a4ddb', '#0e8585', '#b8365f'];
@endphp

{{--
    The console's brand button: mark, product name and current panel, doubling
    as the panel switcher. Replaces the vendor logo, which theme.css hides.
    With a single accessible panel there is nothing to switch to, so the same
    button renders static, without the chevron or dropdown.
--}}
@capture($brandButtonContent, $withChevron = true)
    <span @class(['fi-brandbtn-mark', 'fi-brandmark-custom' => $brandMark !== null])>
        {{-- Same built-in mark as the dropdown's admin row, so the two match. --}}
        <img src="{{ $brandMark ?? public_asset('/assets/img/logo_blue.svg') }}" alt="" />
    </span>
    <span class="fi-brandbtn-text">
        <span class="fi-brandbtn-name">{{ $brandName }}</span>
        <span class="fi-brandbtn-panel">{{ $panelLabel($current) }}</span>
    </span>
    @if ($withChevron)
        <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::CaretDownLight" class="fi-brandbtn-chev" />
    @endif
@endcapture

@if (count($panels) > 1)
    <x-filament::dropdown placement="bottom-start" :offset="2" width="none" class="fi-brand-switcher">
        <x-slot name="trigger">
            <button type="button" class="fi-brandbtn">
                {{ $brandButtonContent() }}
            </button>
        </x-slot>

        <div class="fi-brandmenu-label">{{ __('common.panels') }}</div>

        @php($nonAdminIndex = 0)
        @foreach ($panels as $panel)
            @php($isCurrent = $panel->getId() === $current->getId())
            @php($isAdmin = $panel->getId() === 'admin')
            <a
                href="{{ $panel->getUrl() ?? url($panel->getPath()) }}"
                class="fi-brandmenu-item"
                @if ($isCurrent) aria-current="true" @endif
            >
                @if ($isAdmin)
                    <span @class(['fi-brandmenu-item-mark', 'fi-brandmark-custom' => $brandMark !== null])>
                        <img src="{{ $brandMark ?? public_asset('/assets/img/logo_blue.svg') }}" alt="" />
                    </span>
                @else
                    <span class="fi-brandmenu-item-mark" style="background-color: {{ $markColors[$nonAdminIndex++ % count($markColors)] }}">
                        {{ mb_strtoupper(mb_substr($panelLabel($panel), 0, 2)) }}
                    </span>
                @endif
                <span class="fi-brandmenu-item-text">
                    <strong>{{ $panelLabel($panel) }}</strong>
                    <span>{{ $isAdmin ? 'Core phpvms' : '/'.$panel->getPath() }}</span>
                </span>
                @if ($isCurrent)
                    <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::CheckLight" class="fi-brandmenu-check" />
                @endif
            </a>
        @endforeach

        @if ($addonsUrl !== null)
            <a class="fi-brandmenu-foot" href="{{ $addonsUrl }}">
                <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::PuzzlePieceLight" />
                {{ __('common.manage_addons') }}
            </a>
        @endif
    </x-filament::dropdown>
@else
    <div class="fi-brandbtn">
        {{ $brandButtonContent(withChevron: false) }}
    </div>
@endif
