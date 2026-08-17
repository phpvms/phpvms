{{--
    The facts an operator checks before pressing Update: what is installed, what
    is on offer, whether this install can actually run it, and where the code
    comes from. Both link rows come from the registry and are often absent, so
    each is only rendered when there is somewhere to send people.
--}}
@php
    $sel = $this->selected();
    $website = $sel['product_url'] ?: $sel['repository_url'];
    $requires = array_filter([
        $sel['min_php'] ? 'PHP ≥ '.$sel['min_php'] : null,
        $sel['min_phpvms'] ? 'phpVMS ≥ '.$sel['min_phpvms'] : null,
    ]);
@endphp

<dl class="dl">
    <div>
        <dt>{{ __('addon-manager::addons.label_installed') }}</dt>
        <dd>
            @if ($sel['installed_version'])
                <span class="id">{{ $sel['installed_version'] }}</span>
            @else
                <span class="fi-text-tertiary">&mdash;</span>
            @endif
        </dd>
    </div>

    @if ($sel['latest_version'])
        <div>
            <dt>{{ __('addon-manager::addons.label_latest') }}</dt>
            <dd>
                <span class="id">{{ $sel['latest_version'] }}</span>
                @if ($sel['release']['channel'] ?? false)
                    <span class="chip chip--plain chip--mute">{{ $sel['release']['channel'] }}</span>
                @endif
                @if ($sel['update_available'])
                    <span class="chip chip--info">{{ __('addon-manager::addons.update_available') }}</span>
                @endif
            </dd>
        </div>
    @endif

    @if ($requires !== [])
        <div>
            <dt>{{ __('addon-manager::addons.label_requires') }}</dt>
            <dd>
                {{ implode(', ', $requires) }}
                @if ($sel['compatible'])
                    <span class="chip chip--ok chip--plain">{{ __('addon-manager::addons.compatible') }}</span>
                @else
                    <span class="chip chip--bad">{{ $sel['incompatible_reason'] }}</span>
                @endif
            </dd>
        </div>
    @endif

    @if ($website)
        <div>
            <dt>{{ __('addon-manager::addons.website') }}</dt>
            <dd>
                <a class="dl__link" href="{{ $website }}" target="_blank" rel="noopener">
                    {{ \Illuminate\Support\Str::of($website)->after('://')->rtrim('/') }}
                    <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::ArrowSquareOutLight" />
                </a>
            </dd>
        </div>
    @endif

    @if ($sel['changelog_url'])
        <div>
            <dt>{{ __('addon-manager::addons.changelog') }}</dt>
            <dd>
                {{-- Leading icon, no arrow: a markdown changelog opens in a panel
                     here, and an external-link glyph would promise a trip off
                     the page. --}}
                <a class="dl__link dl__link--inline" href="{{ $sel['changelog_url'] }}" target="_blank" rel="noopener">
                    <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::FileTextLight" />
                    {{ \Illuminate\Support\Str::afterLast($sel['changelog_url'], '/') }}
                </a>
            </dd>
        </div>
    @endif
</dl>
