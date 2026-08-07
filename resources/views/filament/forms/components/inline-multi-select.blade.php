{{--
    InlineMultiSelect (see the component class for the design brief).
    Alpine owns the dropdown; state entangles with the field's state path,
    so Filament validation/relationships behave as with the stock Select.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="ims"
        x-data="{
            open: false,
            search: '',
            items: @js($getItems()),
            state: $wire.$entangle('{{ $getStatePath() }}'),
            get selected() {
                return (Array.isArray(this.state) ? this.state : []).map(String);
            },
            get filtered() {
                const q = this.search.trim().toLowerCase();
                if (q === '') {
                    return this.items;
                }

                return this.items.filter((item) =>
                    item.label.toLowerCase().includes(q)
                    || (item.meta ?? '').toLowerCase().includes(q));
            },
            get summary() {
                const picked = this.items.filter((item) => this.selected.includes(item.value));
                const tokens = picked.map((item) => item.meta ?? item.label);
                const shown = tokens.slice(0, 3).join(', ');

                return tokens.length > 3 ? `${shown} +${tokens.length - 3}` : shown;
            },
            toggle(value) {
                this.state = this.selected.includes(value)
                    ? this.selected.filter((v) => v !== value)
                    : [...this.selected, value];
            },
        }"
        x-effect="if (open) $nextTick(() => $refs.search.focus())"
        @click.outside="open = false"
        @keydown.escape.stop="open = false"
    >
        <button
            type="button"
            class="ims__trigger"
            :class="{ 'ims__trigger--empty': summary === '' }"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open = ! open"
            @if ($isDisabled()) disabled @endif
        >
            <span x-text="summary === '' ? @js($getPlaceholder() ?? __('common.select_prompt')) : summary"></span>
            @svg('tabler-chevron-down')
        </button>

        <div class="ims__panel" x-show="open" x-cloak>
            <div class="ims__search">
                @svg('tabler-search')
                <label class="sr-only" for="{{ $getId() }}-search">{{ __('common.search') }}</label>
                <input
                    id="{{ $getId() }}-search"
                    type="text"
                    x-model="search"
                    x-ref="search"
                    placeholder="{{ __('common.search') }}"
                    autocomplete="off"
                />
            </div>

            <ul class="ims__list" role="listbox" aria-multiselectable="true">
                {{-- Items arrive group-sorted; emit a header whenever the
                     group differs from the previous item's. --}}
                <template x-for="(item, index) in filtered" :key="item.value">
                    <li>
                        <div
                            class="ims__group"
                            x-show="item.group !== null && (index === 0 || filtered[index - 1].group !== item.group)"
                            x-text="item.group"
                        ></div>
                        <button
                            type="button"
                            role="option"
                            :aria-selected="selected.includes(item.value) ? 'true' : 'false'"
                            @click="toggle(item.value)"
                        >
                            <span class="ims__check">@svg('tabler-check')</span>
                            <span class="ims__label" x-text="item.label"></span>
                            <span class="ims__meta" x-text="item.meta ?? ''"></span>
                        </button>
                    </li>
                </template>
                <li class="ims__empty" x-show="filtered.length === 0">{{ __('common.no_results') }}</li>
            </ul>
        </div>
    </div>
</x-dynamic-component>
