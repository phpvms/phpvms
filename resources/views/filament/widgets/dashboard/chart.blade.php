<x-filament-widgets::widget class="fi-widget">
    <x-filament::section :heading="$heading">
        <div
            data-dashboard-chart="{{ $chartType }}"
            data-chart-payload="{{ $json }}"
            class="w-full text-gray-500 dark:text-gray-400"
        ></div>
    </x-filament::section>
</x-filament-widgets::widget>
