@php
    $customization = $classes();
    // airside eject: id for aria-labelledby, derived from the modal's open event name.
    $titleId = 'modal-title-'.md5((string) $open);
@endphp

<template x-teleport="body">
<div x-cloak
     @if ($id) id="{{ $id }}" @endif
     @class(['relative', $configurations['zIndex']])
     {{-- airside eject: unique title id instead of the hardcoded "modal-title"; only set when there is a title to point at. --}}
     @if ($title) aria-labelledby="{{ $titleId }}" @endif
     role="dialog"
     aria-modal="true"
     @if ($wire)
         x-data="tallstackui_modal(@entangle($entangle), @js($configurations['overflow'] ?? false), @js($configurations['handle']))"
     @else
         x-data="tallstackui_modal(false, @js($configurations['overflow'] ?? false), @js($configurations['handle']))"
     @endif
     x-show="show"
     @if (!$configurations['persistent']) x-on:keydown.escape.window="top_ui && !window.tallstackui_escapeClaimed($event) && (show = false)" @endif
     x-on:modal:{{ $open }}.window="show = true;"
     x-on:modal:{{ $close }}.window="show = false;"
        {{ $attributes->whereStartsWith('x-on:') }}>
    <div x-show="show"
         @if (!$ts_ui__flash)
             x-transition:enter="ease-emphasized-decelerate duration-400"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-emphasized-accelerate duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
            @endif
            @class([$customization['wrapper.first'], ($configurations['blur'] ? $customization['blur.'.($configurations['blur'] === true ? 'sm' : $configurations['blur'])] : '') => (bool) $configurations['blur']])></div>
    <div class="{{ $customization['wrapper.second'] }}">
        <div @class([
                $customization['wrapper.third'],
                $configurations['size'],
                $customization['positions.' . $configurations['position']],
                $customization['wrapper.third-centered-padding'] => $configurations['center'] === true,
            ])>
            <div x-show="show"
                 @if (!$configurations['persistent']) x-on:mousedown.away="top_ui && (show = false)" @endif
                 @if (!$ts_ui__flash)
                     x-transition:enter="ease-emphasized-decelerate duration-400"
                 x-transition:leave="ease-emphasized-accelerate duration-200"
                 @if ($configurations['center'] === true)
                     x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 @else
                     x-transition:enter-start="translate-y-full motion-reduce:translate-y-0 motion-reduce:opacity-0 sm:translate-y-0 sm:opacity-0 sm:scale-95"
                 x-transition:enter-end="translate-y-0 motion-reduce:opacity-100 sm:opacity-100 sm:scale-100"
                 x-transition:leave-start="translate-y-0 motion-reduce:opacity-100 sm:opacity-100 sm:scale-100"
                 x-transition:leave-end="translate-y-full motion-reduce:translate-y-0 motion-reduce:opacity-0 sm:translate-y-0 sm:opacity-0 sm:scale-95"
                 @endif
                 @endif
                 {{-- airside eject: focus trap over the whole panel (title, body, footer). --}}
                 x-trap.noscroll="show"
                 @if ($configurations['handle'])
                     x-ref="panel"
                 x-bind:style="handleStyle"
                 @endif
                    @class([$customization['wrapper.fourth'], $configurations['size'], $customization['wrapper.scrollable'] => $configurations['scrollable'], $customization['wrapper.fourth-centered-rounded'] => $configurations['center'] === true])>
                @if ($configurations['handle'])
                    <div class="{{ $customization['handle.wrapper'] }}"
                         dusk="tallstackui_modal_handle"
                         x-on:pointerdown="handleStart($event)"
                         x-on:pointermove="handleMove($event)"
                         x-on:pointerup="handleEnd()"
                         x-on:pointercancel="handleEnd()">
                        <div class="{{ $customization['handle.bar'] }}"></div>
                    </div>
                @endif
                @if ($title)
                    <div class="{{ $customization['title.wrapper'] }}">
                        {{-- airside eject: id for aria-labelledby; the title takes the focus when the modal opens
                             (x-trap honours [autofocus]), so the close button does not; tabindex -1 keeps it out of the Tab order. --}}
                        <h3 id="{{ $titleId }}" tabindex="-1" autofocus class="{{ $customization['title.text'] }} focus:outline-none">{{ $title }}</h3>
                        {{-- airside eject: aria-label on the icon-only close button. --}}
                        <button type="button" x-on:click="show = false" aria-label="Close" dusk="tallstackui_modal_close">
                            <x-dynamic-component :component="TallStackUi::prefix('icon')"
                                                 :icon="TallStackUi::icon('x-mark')"
                                                 internal
                                                 class="{{ $customization['title.close'] }}" />
                        </button>
                    </div>
                @endif
                <div @class([
                        $customization['body'],
                        $customization['body.paddingless'] => $paddingless,
                        $customization['body.scrollable'] => $configurations['scrollable'],
                        $customization['scrollbar.thin'] => $configurations['scrollable'] && $configurations['scrollbar'] === 'thin',
                        $customization['scrollbar.thick'] => $configurations['scrollable'] && $configurations['scrollbar'] === 'thick',
                    ])>
                    {{ $slot }}
                </div>
                @if ($footer)
                    <div {{ $bag->class([$customization['footer.wrapper'], $customization['footer.scrollable'] => $configurations['scrollable']]) }}>
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
</template>
