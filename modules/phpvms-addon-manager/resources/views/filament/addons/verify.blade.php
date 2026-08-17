{{--
    The one thing an operator should read before running someone else's code.
    Closes the detail section, bleeding to the card edge on three sides.
--}}
@php
    $host = parse_url((string) config('addon-manager.registry_url'), PHP_URL_HOST) ?: 'the registry';
@endphp

<p class="verify">
    <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::ShieldCheckLight" />
    <span>{{ __('addon-manager::addons.verify_note', ['host' => $host]) }}</span>
</p>
