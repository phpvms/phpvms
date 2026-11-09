@php
    $customization = $classes();
@endphp

<div x-data="Object.assign(tallstackui_tab(@if (!$selected) {!! TallStackUi::blade($attributes, $livewire)->entangle() !!} @else @js($selected) @endif), {
         move(step, edge) {
             const last = this.tabs.length - 1;
             if (last < 0) return;
             const current = this.tabs.findIndex((item) => item.tab === this.selected);
             const next = edge === 'first' ? 0 : edge === 'last' ? last : (current + step + last + 1) % (last + 1);
             this.select(this.tabs[next]);
             this.$nextTick(() => this.$refs.ul.querySelectorAll('[role=tab]')[next]?.focus());
         },
     })"
     @class([$customization['base.wrapper'], $customization['shadowless'] => $shadowless, $customization['bordered'] => $bordered])>
    @if (!$scrollOnMobile)
        <div class="{{ $customization['base.padding'] }}">
            <select x-model="selected" class="{{ $customization['base.select'] }}" aria-label="Select a tab"
                    x-on:change="change()">
                <template x-for="item in tabs">
                    <option x-bind:value="item.tab" x-text="item.title ?? item.tab"
                            x-bind:selected="item.tab === selected">
                    </option>
                </template>
            </select>
        </div>
    @endif
    <ul role="tablist"
        @class([$customization['base.body'], 'hidden ' . $customization['base.body-mobile-show'] => ! $scrollOnMobile, $customization['base.centered'] => $centered]) {{ $attributes->only('x-on:navigate') }} x-ref="ul"
        x-on:keydown.left.prevent="move(-1)"
        x-on:keydown.right.prevent="move(1)"
        x-on:keydown.home.prevent="move(0, 'first')"
        x-on:keydown.end.prevent="move(0, 'last')">
        <template x-for="item in tabs">
            <li role="tab"
                x-bind:id="item.id"
                x-bind:aria-controls="item.panel"
                x-bind:tabindex="selected === item.tab ? 0 : -1"
                x-on:click="select(item)"
                x-on:keypress.enter="select(item)"
                x-on:mouseenter="prefetch(item)"
                x-bind:aria-selected="selected === item.tab ? 'true' : 'false'"
                x-bind:class="{
                    '{{ $customization['item.select'] }}' : selected === item.tab,
                    '{{ $customization['item.unselect'] }}' : selected !== item.tab,
                    'hidden {{ $customization['item.mobile-show'] }}': selected !== item.tab && ! @js($scrollOnMobile),
                }">
                <div class="{{ $customization['item.wrapper'] }}">
                    <template x-if="item.left">
                        <div x-html="item.left"></div>
                    </template>
                    <span x-text="item.title ?? item.tab"></span>
                    <template x-if="item.right">
                        <div x-html="item.right"></div>
                    </template>
                </div>
            </li>
        </template>
    </ul>
    <hr @class([$customization['base.divider'], 'hidden ' . $customization['base.divider-mobile-show'] => ! $scrollOnMobile])>
    <div @class([$customization['base.content'], $customization['base.content-paddingless'] => $paddingless])>
        {{ $slot }}
    </div>
</div>
