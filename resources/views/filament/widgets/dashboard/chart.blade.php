<x-filament-widgets::widget class="fi-widget dashboard-chart-widget">
    <x-filament::section :heading="$heading">
        <x-slot name="afterHeader">
            <x-filament::modal
                :heading="$heading"
                width="7xl"
                :id="'chart-modal-' . $this->getId()"
            >
                <x-slot name="trigger">
                    <x-filament::icon-button
                        :icon="\Daljo25\FilamentTablerIcons\Enums\TablerIcon::ArrowsMaximize"
                        :label="__('filament.dashboard.expand_chart')"
                        color="gray"
                        size="sm"
                    />
                </x-slot>

                {{-- Same chart, rendered large. bootstrap() picks this up via
                     the MutationObserver when the modal opens. --}}
                <div
                    data-dashboard-chart="{{ $chartType }}"
                    data-chart-payload="{{ $json }}"
                    class="w-full text-gray-500 dark:text-gray-400"
                ></div>
            </x-filament::modal>
        </x-slot>

        <div
            data-dashboard-chart="{{ $chartType }}"
            data-chart-payload="{{ $json }}"
            class="w-full text-gray-500 dark:text-gray-400"
        ></div>
    </x-filament::section>
</x-filament-widgets::widget>
