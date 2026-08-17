{{--
    PIREP list with the mockup's hover preview (pireps.html:652-735): the
    table and a sticky 350px context aside share the .with-context grid.
    Hovering a row for 500ms selects it — the panel and the accent row
    highlight follow. The payload rides in a JSON <script> so Livewire
    morphs (pagination, filters) refresh it without re-initing Alpine.
--}}
<x-filament-panels::page>
    <div
        class="with-context"
        x-data="{
            selected: null,
            timer: null,
            get records() {
                try {
                    return JSON.parse(this.$refs.payload.textContent);
                } catch {
                    return {};
                }
            },
            get row() {
                return this.selected ? (this.records[this.selected] ?? null) : null;
            },
            init() {
                this.$nextTick(() => this.select(Object.keys(this.records)[0] ?? null));

                const wrap = this.$refs.tableWrap;

                wrap.addEventListener('mouseover', (event) => {
                    const tr = event.target.closest('.fi-ta-row');
                    if (! tr || ! wrap.contains(tr)) {
                        return;
                    }

                    // wire:key is '{component}.table.records.{key}'.
                    const key = (tr.getAttribute('wire:key') ?? '').split('.records.').pop();
                    if (! key || key === this.selected) {
                        clearTimeout(this.timer);
                        return;
                    }

                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.select(key), 500);
                });

                wrap.addEventListener('mouseleave', () => clearTimeout(this.timer));
            },
            select(key) {
                this.selected = key;

                this.$refs.tableWrap.querySelectorAll('.fi-ta-row[aria-selected]')
                    .forEach((row) => row.removeAttribute('aria-selected'));

                if (key) {
                    this.$refs.tableWrap
                        .querySelector(`.fi-ta-row[wire\\:key$='.records.${CSS.escape(key)}']`)
                        ?.setAttribute('aria-selected', 'true');
                }
            },
        }"
    >
        <script type="application/json" x-ref="payload">{!! json_encode($this->getPreviewData(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) !!}</script>

        <div class="min-w-0" x-ref="tableWrap">
            {{ $this->table }}
        </div>

        <aside class="context flex min-w-0 flex-col gap-3.5">
        <section class="panel" aria-label="{{ __('pireps.selected_report') }}" x-cloak x-show="row">
            <template x-if="row">
                <div>
                    <div class="context__head">
                        <div>
                            <div class="context__eyebrow">{{ __('pireps.flight_report') }}</div>
                            <div class="context__title" x-text="row.ident"></div>
                            <div class="context__sub" x-text="row.sub"></div>
                        </div>
                        <span class="chip" :class="row.chip" x-text="row.state"></span>
                    </div>

                    <dl class="dl">
                        <template x-for="[label, field] in [
                            ['{{ __('flights.route') }}', 'route'],
                            ['{{ trans_choice('common.aircraft', 1) }}', 'aircraft'],
                            ['{{ __('pireps.block') }}', 'block'],
                            ['{{ __('common.distance') }}', 'distance'],
                            ['{{ __('pireps.landing') }}', 'landing'],
                            ['{{ __('pireps.fuel_used') }}', 'fuel'],
                            ['{{ __('common.source') }}', 'source'],
                            ['{{ __('flights.flighttype') }}', 'type'],
                        ]" :key="field">
                            <div x-show="row[field]">
                                <dt x-text="label"></dt>
                                <dd><span class="id" x-text="row[field]"></span></dd>
                            </div>
                        </template>
                    </dl>

                    <div class="context__actions">
                        <a class="fi-btn fi-color-gray" :href="row.url">{{ __('pireps.full_report') }}</a>
                    </div>
                </div>
            </template>
        </section>

        {{-- Full filter set, always visible — replaces the funnel dropdown
             (the table runs FiltersLayout::Hidden). Same tableFilters state
             the quick bar above the table binds to. --}}
        <section class="panel" aria-label="{{ __('common.filters') }}">
            <div class="panel__head rounded-t-[5px]">
                <h3 class="panel__title">@svg('phosphor-funnel-light') {{ __('common.filters') }}</h3>
            </div>
            <div class="panel__body">
                {{ $this->getTableFiltersForm() }}
            </div>
        </section>
        </aside>
    </div>
</x-filament-panels::page>
