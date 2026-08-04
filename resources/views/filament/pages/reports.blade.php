<x-filament-panels::page>
    <x-filament::section
        :heading="__('filament.reports_intro_heading')"
        :description="__('filament.reports_intro_description')"
    >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-filament::section :heading="__('filament.reports_pireps_heading')" :description="__('filament.reports_pireps_description')" />
            <x-filament::section :heading="__('filament.reports_finance_heading')" :description="__('filament.reports_finance_description')" />
            <x-filament::section :heading="__('filament.reports_fleet_heading')" :description="__('filament.reports_fleet_description')" />
        </div>
    </x-filament::section>
</x-filament-panels::page>
