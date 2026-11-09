@props(['name', 'class' => 'size-4'])

{{-- Icon from the Phosphor pack (see NavigationIcon for the tabler/heroicon aliases); renders nothing for an unknown name. Props: name, class. --}}
@php
    $iconHtml = \Modules\PhpvmsAirsideTheme\Support\NavigationIcon::html($name, $class);
@endphp
@if ($iconHtml)
    <span {{ $attributes->class('inline-flex shrink-0') }} aria-hidden="true">{!! $iconHtml !!}</span>
@endif
