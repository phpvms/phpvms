{{--
    Category list for the Settings page: a search box above a vertical, sticky
    jump nav beside the stacked category sections. Same scrollspy as
    filament/shared/section-nav.blade.php, laid out as a column.

    The filter hides rows by toggling `.is-filtered-out`. Livewire morphs drop
    classes it did not render, so it is re-applied after every morph.
--}}
@php
    $ids = array_column($links, 'id');
@endphp

<div
    class="settings-nav"
    x-data="{
        active: @js($ids[0] ?? ''),
        query: '',
        ids: @js($ids),
        unhook: null,
        init() {
            const spy = () => {
                const ids = this.visibleIds();
                let current = ids[0];
                for (const id of ids) {
                    const el = document.getElementById(id);
                    if (el && el.getBoundingClientRect().top <= 120) {
                        current = id;
                    }
                }
                // A short last category never reaches the top; at the foot of
                // the page it is the one in view. scrollY > 0: on init the
                // page has not laid out yet and reads as already at the foot.
                if (window.scrollY > 0 && window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
                    current = ids[ids.length - 1];
                }
                this.active = current ?? '';
            };
            this.spy = spy;
            this.onScroll = spy;
            window.addEventListener('scroll', this.onScroll, { passive: true });
            // Re-apply after Livewire morphs the DOM (a save re-renders the form).
            this.unhook = window.Livewire?.hook('morphed', () => this.filter());
            this.filter();
        },
        destroy() {
            window.removeEventListener('scroll', this.onScroll);
            if (typeof this.unhook === 'function') this.unhook();
        },
        sectionOf(id) {
            const el = document.getElementById(id);
            return el ? (el.closest('.fi-sc-section') ?? el) : null;
        },
        visibleIds() {
            const visible = this.ids.filter((id) => !this.sectionOf(id)?.classList.contains('is-filtered-out'));
            return visible.length ? visible : this.ids;
        },
        filter() {
            const q = this.query.trim().toLowerCase();
            let shown = 0;
            for (const id of this.ids) {
                const section = this.sectionOf(id);
                if (!section) continue;
                let matches = 0;
                section.querySelectorAll('.fi-fo-field').forEach((row) => {
                    const hit = q === '' || row.textContent.toLowerCase().includes(q);
                    (row.closest('.fi-grid-col') ?? row).classList.toggle('is-filtered-out', !hit);
                    if (hit) matches++;
                });
                section.classList.toggle('is-filtered-out', matches === 0);
                this.$root.querySelector(`[data-nav-id='${id}']`)?.classList.toggle('is-filtered-out', matches === 0);
                shown += matches;
            }
            document.querySelectorAll('[data-settings-empty]').forEach((el) => {
                el.classList.toggle('is-filtered-out', shown > 0);
            });
            this.spy?.();
        },
        clear() {
            this.query = '';
            this.filter();
        },
        go(id) {
            const el = document.getElementById(id);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                this.active = id;
            }
        },
    }"
>
    <label class="settings-nav__search">
        <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::MagnifyingGlassLight" />
        <input
            type="search"
            x-model="query"
            x-on:input="filter()"
            x-on:keydown.escape.prevent="clear()"
            placeholder="Search settings"
            aria-label="Search settings"
            autocomplete="off"
        />
    </label>

    <nav class="settings-nav__list" aria-label="{{ __('filament.page_sections') }}">
        @foreach ($links as $link)
            <a
                href="#{{ $link['id'] }}"
                class="settings-nav__item"
                data-nav-id="{{ $link['id'] }}"
                @click.prevent="go('{{ $link['id'] }}')"
                x-bind:aria-current="active === '{{ $link['id'] }}' ? 'true' : 'false'"
            >
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
</div>
