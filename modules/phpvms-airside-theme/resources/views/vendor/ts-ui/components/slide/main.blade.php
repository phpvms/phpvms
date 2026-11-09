@php
    $customization = $classes();
    // airside eject: id for aria-labelledby, derived from the slide's open event name.
    $titleId = 'slide-title-'.md5((string) $open);
@endphp

<template x-teleport="body">
<div x-cloak
     @if ($wire)
         x-data="tallstackui_slide(@entangle($entangle), @js($configurations['overflow'] ?? false))"
     @else
         x-data="tallstackui_slide(false, @js($configurations['overflow'] ?? false))"
     @endif
     x-show="show"
     @if (!$configurations['persistent']) x-on:keydown.escape.window="top_ui && !window.tallstackui_escapeClaimed($event) && (show = false)" @endif
     x-on:slide:{{ $open }}.window="show = true;"
     x-on:slide:{{ $close }}.window="show = false;"
        @class(['relative', $configurations['zIndex']])
        {{ $attributes->whereStartsWith('x-on:') }}>
    <div x-show="show"
         @if (!$ts_ui__flash)
             x-transition:enter="ease-in-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in-out duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
            @endif
            @class([$customization['wrapper.first'], ($configurations['blur'] ? $customization['blur.'.($configurations['blur'] === true ? 'sm' : $configurations['blur'])] : '') => (bool) $configurations['blur']])></div>
    <div class="{{ $customization['wrapper.second'] }}">
        <div class="{{ $customization['wrapper.third'] }}">
            <div @class([
                    $customization['wrapper.fourth'],
                    $customization['wrapper.panel.inset-y'] => !$configurations['bottom'],
                    $customization['wrapper.panel.bottom'] => $configurations['bottom'],
                    $customization['wrapper.panel.left'] => $configurations['left'],
                    $customization['wrapper.panel.pr-10'] => $configurations['left'] && $configurations['size'] !== 'full',
                    $customization['wrapper.panel.right'] => $configurations['left'] === false,
                    $customization['wrapper.panel.pl-10'] =>
                        $configurations['left'] === false &&
                        $configurations['size'] !== 'full' &&
                        $configurations['top'] === false &&
                        $configurations['bottom'] === false,
                    $configurations['size'] => $configurations['top'] || $configurations['bottom'],
                    $customization['wrapper.panel.h-full'] => !$configurations['top'] || !$configurations['bottom'],
                    $customization['wrapper.panel.w-full-dvw'] => $configurations['top'] || $configurations['bottom'],
                ])>
                <div x-show="show"
                     @if (!$ts_ui__flash)
                         x-transition:enter="transform transition ease-in-out duration-700"
                     x-transition:enter-start="@if ($configurations['left']) -translate-x-full @elseif ($configurations['top']) -translate-y-full @elseif ($configurations['bottom']) translate-y-full @else translate-x-full @endif"
                     x-transition:enter-end="@if ($configurations['left']) translate-x-0 @elseif ($configurations['top']) translate-y-0 @elseif ($configurations['bottom']) translate-y-0 @else translate-x-0 @endif"
                     x-transition:leave="transform transition ease-in-out duration-700"
                     x-transition:leave-start="@if ($configurations['left']) translate-x-0 @elseif ($configurations['top']) translate-y-0 @elseif ($configurations['bottom']) translate-y-0 @else translate-x-0 @endif"
                     x-transition:leave-end="@if ($configurations['left']) -translate-x-full @elseif ($configurations['top']) -translate-y-full @elseif ($configurations['bottom']) translate-y-full @else translate-x-full @endif"
                     @endif
                     @class([$customization['wrapper.inner.horizontal'], $configurations['size'],  $customization['wrapper.inner.h-full'] => !$configurations['top'] || !$configurations['bottom']])
                     @if (!$configurations['persistent']) x-on:mousedown.away="top_ui && (show = false)" @endif>
                    <div role="dialog"
                         aria-modal="true"
                         @if ($title) aria-labelledby="{{ $titleId }}" @endif
                         x-trap.noscroll="show"
                         @class([
                            $customization['wrapper.fifth'],
                            $configurations['size'],
                            $customization['wrapper.inner.h-full'] => !$configurations['top'] || !$configurations['bottom']
                        ])>
                        <div @class([
                                $customization['header.base'],
                                $customization['header.divider'] => $title !== null,
                            ])>
                            <div @class([$customization['header.layout.base'], $customization['header.layout.with-title'] => $title !== null, $customization['header.layout.no-title'] => $title === null])>
                                @if ($title)
                                    {{-- airside eject: the title takes the focus when the slide opens (x-trap honours [autofocus]),
                                         so the close button does not; tabindex -1 keeps it out of the Tab order. --}}
                                    <h2 id="{{ $titleId }}" tabindex="-1" autofocus @if ($title instanceof \Illuminate\View\ComponentSlot)
                                            {{ $title->attributes->class([$customization['title.text'], 'focus:outline-none']) }}
                                        @else
                                            class="{{ $customization['title.text'] }} focus:outline-none"
                                            @endif>{{ $title }}</h2>
                                @endif
                                <button type="button" x-on:click="show = false" aria-label="Close" dusk="tallstackui_slide_close">
                                    <x-dynamic-component :component="TallStackUi::prefix('icon')"
                                                         :icon="TallStackUi::icon('x-mark')"
                                                         internal
                                                         class="{{ $customization['title.close'] }}" />
                                </button>
                            </div>
                        </div>
                        <div @class([$customization['body'], $customization['body.paddingless'] => $paddingless])>
                            {{ $slot }}
                        </div>
                        @if ($footer)
                            <div {{ $bag->class([$customization['footer.wrapper']]) }}>
                                @if ($align)
                                    <div @class([$customization['footer.base'], $customization['footer.'.$align]])>
                                        {{ $footer }}
                                    </div>
                                @else
                                    {{ $footer }}
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</template>
