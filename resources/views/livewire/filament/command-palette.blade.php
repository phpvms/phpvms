{{--
    Command palette: cmd/ctrl+K overlay. Behaviour lives in
    resources/js/apps/admin/command-palette.js (phpvmsCommandPalette). Result
    rows carry data-cmdk-item so the keyboard handler can walk them whether
    they came from the server (query) or from localStorage (recents).
--}}
@php
    use Filafly\Icons\Phosphor\Enums\Phosphor;

    $index = 0;
@endphp

<div
    class="cmdk"
    x-data="phpvmsCommandPalette({ spa: @js($isSpa), user: @js(auth()->id()) })"
    x-cloak
    x-show="open"
    x-on:cmdk-open.window="show()"
    x-on:keydown.escape.window="open && close()"
    role="dialog"
    aria-modal="true"
    aria-label="{{ __('filament.palette_label') }}"
>
    <div class="cmdk-backdrop" x-on:click="close()"></div>

    <div class="cmdk-panel" x-trap.noscroll="open" x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.enter.prevent="activate()">
        <div class="cmdk-field">
            <x-filament::icon :icon="Phosphor::MagnifyingGlassLight" class="cmdk-field-icon" />
            <input
                x-ref="input"
                type="text"
                class="cmdk-input"
                wire:model.live.debounce.250ms="query"
                placeholder="{{ __('filament.palette_placeholder') }}"
                autocomplete="off"
                spellcheck="false"
            />
            <kbd class="cmdk-kbd">esc</kbd>
        </div>

        <div class="cmdk-results" x-ref="results">
            @forelse ($groups as $category => $results)
                <div class="cmdk-group">
                    <div class="cmdk-group-label">{{ $category }}</div>
                    @foreach ($results as $result)
                        <a
                            href="{{ $result['url'] }}"
                            class="cmdk-item"
                            data-cmdk-item
                            data-title="{{ $result['title'] }}"
                            data-url="{{ $result['url'] }}"
                            data-category="{{ $category }}"
                            x-bind:class="{ 'is-active': active === {{ $index }} }"
                            x-on:mousemove="active = {{ $index }}"
                            x-on:focus="active = {{ $index }}"
                            x-on:click="choose($event, $el)"
                        >
                            <span class="cmdk-item-title">{{ $result['title'] }}</span>
                            @if ($result['details'] !== '')
                                <span class="cmdk-item-details">{{ $result['details'] }}</span>
                            @endif
                        </a>
                        @php $index++; @endphp
                    @endforeach
                </div>
            @empty
                @if (trim($query) !== '')
                    <div class="cmdk-empty" wire:loading.remove wire:target="query">{{ __('filament.palette_no_results') }}</div>
                @endif
            @endforelse

            @if (trim($query) === '')
                <div class="cmdk-group" x-show="recents.length > 0">
                    <div class="cmdk-group-label">{{ __('filament.palette_recent') }}</div>
                    <template x-for="(entry, i) in recents" x-bind:key="entry.url">
                        <a
                            x-bind:href="entry.url"
                            class="cmdk-item"
                            data-cmdk-item
                            x-bind:data-title="entry.title"
                            x-bind:data-url="entry.url"
                            x-bind:data-category="entry.category"
                            x-bind:class="{ 'is-active': active === i }"
                            x-on:mousemove="active = i"
                            x-on:focus="active = i"
                            x-on:click="choose($event, $el)"
                        >
                            <span class="cmdk-item-title" x-text="entry.title"></span>
                            <span class="cmdk-item-details" x-text="entry.category"></span>
                        </a>
                    </template>
                </div>
                <div class="cmdk-empty" x-show="recents.length === 0">{{ __('filament.palette_hint') }}</div>
            @endif
        </div>
    </div>
</div>
