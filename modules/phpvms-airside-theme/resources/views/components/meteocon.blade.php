@props(['name' => Modules\PhpvmsAirsideTheme\Support\Meteocons::FALLBACK])

{{--
  A Meteocons weather icon (Bas Milius, MIT; see ATTRIBUTIONS.md). The icons are SMIL-animated, which only plays
  in an <img>, so each one is a standalone file the build writes to build/meteocons/<name>.svg (vite.config.js).
  Props: name (a Meteocons icon name, e.g. "partly-cloudy-day-fill"; an unknown name shows "not-available-fill").
  Pass the size with `class`. Without a build (no icon files) it shows a Phosphor icon instead.
--}}
@php
    $icon = Modules\PhpvmsAirsideTheme\Support\Meteocons::resolve($name);
@endphp
@if ($icon)
    <img src="{{ Modules\PhpvmsAirsideTheme\Support\Meteocons::url($icon) }}" alt="" aria-hidden="true" {{ $attributes->class('vms-meteocon') }} />
@else
    <x-ts-icon name="phosphor.cloud-slash.light" {{ $attributes->class('vms-meteocon text-dark-400') }} aria-hidden="true" />
@endif
