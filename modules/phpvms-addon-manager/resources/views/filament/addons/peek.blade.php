{{--
    Add-on slide-over. Rendered as the modal content of the table's `peek`
    record action; reuses the PIREP panel's .pp-* hooks. The buttons re-mount
    the card's own table actions, so authorization and modals are shared.
    The first button is the primary action (header); the rest go in the footer.
--}}
@php
    /** @var array<string, mixed> $row */
    $mount = fn (string $name): string => "replaceMountedAction('".$name."', {}, { table: true, recordKey: ".\Illuminate\Support\Js::from($row['id'], JSON_UNESCAPED_SLASHES).' })';
    $dash = '—';
    $eyebrow = implode(' · ', array_filter([$row['publisher'], $row['category']])) ?: $row['id'];
    $source = $row['product_url'] ?: $row['repository_url'];
    $requires = implode(', ', array_filter([
        $row['min_phpvms'] ? 'phpvms ≥ '.$row['min_phpvms'] : null,
        $row['min_php'] ? 'php ≥ '.$row['min_php'] : null,
    ]));
    $props = array_filter([
        __('addon-manager::addons.publisher') => $row['publisher'],
        __('addon-manager::addons.license')   => $row['license'],
        __('addon-manager::addons.label_requires') => $requires,
    ], filled(...));
    $stats = array_values(array_filter([
        ($row['installed_version'] || $row['latest_version']) ? ['label' => __('addon-manager::addons.label_installed'), 'value' => $row['installed_version'] ?: $dash, 'text' => false] : null,
        ($row['installed_version'] || $row['latest_version']) ? ['label' => __('addon-manager::addons.label_latest'), 'value' => $row['latest_version'] ?: $dash, 'text' => false] : null,
        $row['installs'] > 0 ? ['label' => __('addon-manager::addons.installs'), 'value' => number_format($row['installs']), 'text' => false] : null,
        filled($row['category']) ? ['label' => __('addon-manager::addons.category'), 'value' => $row['category'], 'text' => true] : null,
    ]));
    $primary = $buttons[0] ?? null;
    $secondary = array_slice($buttons, 1);
@endphp

<div class="pp addon-peek">
    <header class="pp-strip">
        <span class="addon-plate addon-plate--{{ $row['tint'] }}">
            @if ($row['icon'])
                <img src="{{ $row['icon'] }}" alt="">
            @else
                {{ $row['monogram'] }}
            @endif
        </span>

        <div class="pp-strip-title">
            <div class="pp-eyebrow">{{ $eyebrow }}</div>
            <h2 class="pp-heading">
                @if ($row['official'])
                    <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::StarFill"
                        class="official" role="img" :aria-label="__('addon-manager::addons.official_hint')" />
                @endif
                <span class="pp-ident">{{ $row['name'] }}</span>
                @foreach ($badges as $badge)
                    <x-filament::badge :color="$badge['color']" size="sm">{{ $badge['label'] }}</x-filament::badge>
                @endforeach
            </h2>
        </div>

        @if ($primary)
            <x-filament::button size="sm" :color="$primary['color']" :icon="$primary['icon']"
                :disabled="$primary['disabled']" :tooltip="$primary['tooltip']"
                wire:click="{{ $mount($primary['name']) }}">
                {{ $primary['label'] }}
            </x-filament::button>
        @endif

        <button type="button" class="pp-iconbtn" aria-label="{{ __('filament::components/modal.actions.close.label') }}"
            x-on:click="$dispatch('close-modal', { id: $root.closest('[id]')?.id })">
            @svg('phosphor-x-light')
        </button>
    </header>

    <div class="pp-body">
        @if ($stats !== [])
            <dl class="pp-stats" style="grid-template-columns: repeat({{ count($stats) }}, minmax(0, 1fr))">
                @foreach ($stats as $stat)
                    <div><dt>{{ $stat['label'] }}</dt><dd @class(['addon-peek__text' => $stat['text']])>{{ $stat['value'] }}</dd></div>
                @endforeach
            </dl>
        @endif

        @if ($row['description'])
            <p class="addon-peek__lede">{{ $row['description'] }}</p>
        @endif

        <div @class(['addon-peek__callout', 'addon-peek__callout--ok' => $row['compatible'], 'addon-peek__callout--warn' => ! $row['compatible']])>
            <x-filament::icon :icon="$row['compatible'] ? \Filafly\Icons\Phosphor\Enums\Phosphor::CheckCircleLight : \Filafly\Icons\Phosphor\Enums\Phosphor::WarningLight" />
            <span>{{ $row['compatible'] ? __('addon-manager::addons.compat_ok') : $row['incompatible_reason'] }}</span>
        </div>

        @if ($props !== [] || $source)
            <dl class="pp-props">
                @foreach ($props as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
                @if ($source)
                    <div>
                        <dt>{{ __('addon-manager::addons.source') }}</dt>
                        <dd><a href="{{ $source }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::of($source)->after('://')->rtrim('/') }}</a></dd>
                    </div>
                @endif
            </dl>
        @endif

        @if ($releases !== [])
            <section class="pp-section">
                <h3>{{ __('addon-manager::addons.releases') }}</h3>
                <dl class="pp-props addon-peek__releases">
                    @foreach ($releases as $release)
                        <div>
                            <dt>
                                {{ $release['version'] ?? $dash }}
                                @if (($release['version'] ?? null) === $row['installed_version'])
                                    <small>{{ __('addon-manager::addons.installed') }}</small>
                                @endif
                            </dt>
                            <dd>
                                {{ implode(' · ', array_filter([
                                    $release['channel'] ?? 'stable',
                                    isset($release['size']) ? \Illuminate\Support\Number::fileSize((int) $release['size'], 1) : null,
                                    isset($release['released_at']) ? \Illuminate\Support\Carbon::parse($release['released_at'])->isoFormat('D MMM YYYY') : null,
                                ])) }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($progress)
            <div class="addon-card__progress" wire:poll.3s>
                <span class="dist__track"><span class="dist__fill" style="width: {{ $progress['pct'] }}%"></span></span>
                <span>{{ $progress['message'] }}, {{ $progress['pct'] }}%</span>
            </div>
        @endif

        <p class="addon-peek__verify">{{ __('addon-manager::addons.verify_note', ['host' => $host]) }}</p>
    </div>

    @if ($secondary !== [] || $row['repository_url'])
        <footer class="addon-peek__foot">
            @foreach ($secondary as $button)
                <x-filament::button size="sm" :color="$button['color']" :icon="$button['icon']" outlined
                    :disabled="$button['disabled']" :tooltip="$button['tooltip']"
                    wire:click="{{ $mount($button['name']) }}">
                    {{ $button['label'] }}
                </x-filament::button>
            @endforeach

            @if ($row['repository_url'])
                <x-filament::button size="sm" color="gray" outlined tag="a" href="{{ $row['repository_url'] }}" target="_blank" rel="noopener"
                    :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::ArrowSquareOutLight">
                    {{ __('addon-manager::addons.repository_link') }}
                </x-filament::button>
            @endif
        </footer>
    @endif
</div>
