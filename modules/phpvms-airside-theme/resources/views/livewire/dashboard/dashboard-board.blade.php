{{-- The dashboard toolbar and board. State and layout rules: App\Features\Dashboard\Livewire\DashboardBoard;
     the stored layout: airside's dashboardLayout (resources/js/airside/dashboard-layout.ts). --}}
<div class="grid gap-3" x-data="dashboardLayout">
    @if (! $ready)
        <div class="grid place-items-center py-10">
            <x-ts-spinner text="Loading dashboard…" />
        </div>
    @else
        <div class="vms-board-toolbar flex items-center justify-between gap-3 px-1.5 pt-4.5 pb-0.5">
            <h2 class="vms-board-title m-0 text-[22px] font-extrabold tracking-tight">Your board</h2>

            <div class="flex items-center gap-2">
                @if ($editing)
                    <x-ts-dropdown position="bottom-end">
                        <x-slot:action>
                            <x-ts-button round sm icon="phosphor.plus.light" :text="__('ui.add_widget')" light x-on:click="show = !show" />
                        </x-slot:action>
                        @forelse ($this->availableWidgets as $widget)
                            <x-ts-dropdown.items
                                wire:key="add-{{ $widget['id'] }}"
                                :text="$widget['title']"
                                :icon="\Modules\PhpvmsAirsideTheme\Support\NavigationIcon::tallstack($widget['icon'])"
                                wire:click="addWidget('{{ $widget['id'] }}')"
                            />
                        @empty
                            <x-ts-dropdown.items :text="__('ui.all_widgets_placed')" />
                        @endforelse
                    </x-ts-dropdown>

                    <x-ts-button sm round flat type="button" wire:click="resetLayout">{{ __('ui.reset') }}</x-ts-button>
                @endif

                <x-ts-button sm round type="button" wire:click="toggleEditing">
                    {{ $editing ? __('ui.done') : __('ui.customize') }}
                </x-ts-button>
            </div>
        </div>

        @php($zones = ['grid' => $grid, 'sidebar' => $sidebar])

        <div class="vms-board grid grid-cols-1 gap-3.5 min-[68.75rem]:grid-cols-[minmax(0,1fr)_360px] min-[68.75rem]:items-start">
            @foreach ($zones as $zone => $ids)
                {{-- Sorting binds when the element initialises, so the key changes with edit mode to remount the zone. --}}
                <div
                    @if ($editing) wire:sort.ghost="sortWidget" wire:sort:group="widgets" wire:sort:group-id="{{ $zone }}" @endif
                    wire:key="zone-{{ $zone }}-{{ $editing ? 'edit' : 'view' }}"
                    data-zone="{{ $zone }}"
                    @class(['grid min-h-[60px] content-start gap-3.5', 'grid-cols-2' => $zone === 'grid', 'grid-cols-1' => $zone === 'sidebar'])
                >
                    @foreach ($ids as $id)
                        @continue(! isset($catalog[$id]))

                        <div
                            wire:key="widget-{{ $id }}"
                            @if ($editing) wire:sort:item="{{ $id }}" @endif
                            data-widget="{{ $id }}"
                            @class(['vms-widget min-w-0 rounded-(--vms-radius-lg)', 'vms-widget--bare' => $catalog[$id]['bare'] && ! $editing, 'outline-2 -outline-offset-2 outline-dashed outline-dark-300' => $editing])
                            @if ($zone === 'grid') style="grid-column: span {{ $catalog[$id]['span'] }} / span {{ $catalog[$id]['span'] }}" @endif
                        >
                            @php($widgetIcon = \Modules\PhpvmsAirsideTheme\Support\NavigationIcon::tallstack($catalog[$id]['icon'] ?? null))
                            <x-ts-card class="flex min-w-0 flex-col" scope="airside.widget">
                                {{-- A bare widget (catalog `bare`) has no header; Customize mode always shows it for the handle and remove button. --}}
                                @if ($editing || ! $catalog[$id]['bare'])
                                <x-slot:header>
                                    <div class="flex w-full items-center gap-2">
                                        @if ($editing)
                                            <span wire:sort:handle class="inline-flex cursor-grab text-dark-400 active:cursor-grabbing" aria-label="Drag to reorder" title="Drag">
                                                <x-ts-icon name="phosphor.dots-six-vertical.light" sm />
                                            </span>
                                        @endif
                                        @if ($widgetIcon)
                                            <span class="vms-widget-icon grid size-[30px] place-items-center rounded-full bg-(--vms-brand-soft) text-(--vms-brand)">
                                                <x-ts-icon :name="$widgetIcon" class="size-4" />
                                            </span>
                                        @endif
                                        <h3 class="m-0 text-[15px] font-bold">{{ $catalog[$id]['title'] }}</h3>
                                        <div class="flex-1"></div>
                                        @if ($editing)
                                            <div wire:sort:ignore>
                                                <x-ts-button xs flat color="red" icon="phosphor.x.light" aria-label="Remove widget" wire:click="removeWidget('{{ $id }}')" />
                                            </div>
                                        @endif
                                    </div>
                                </x-slot:header>
                                @endif

                                @if ($id === 'route')
                                    @include('livewire.dashboard.route-widget', ['route' => $route])
                                @elseif ($id === 'activity')
                                    <livewire:dashboard::activity-feed :key="'activity-feed'" />
                                @elseif ($id === 'news')
                                    <livewire:dashboard::latest-news :key="'latest-news'" />
                                @elseif ($id === 'next-flight')
                                    <livewire:dashboard::next-flight :key="'next-flight'" />
                                @endif
                            </x-ts-card>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>

