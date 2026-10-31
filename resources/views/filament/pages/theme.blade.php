{{-- The tabs use the shared admin subtabs component, the PIREP view's subtabs pattern made reusable. --}}
<x-filament-panels::page>
    <x-admin.subtabs default="customize" :label="__('filament.theme')">
        <x-slot:tabs>
            <x-admin.subtabs.tab key="customize" icon="phosphor-paint-brush-light">
                {{ __('filament.theme_customize_tab') }}
            </x-admin.subtabs.tab>
        </x-slot:tabs>

        <x-admin.subtabs.panel key="customize">
            {{ $this->content }}
        </x-admin.subtabs.panel>
    </x-admin.subtabs>
</x-filament-panels::page>
