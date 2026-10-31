{{-- The dashboard toolbar and board. State and layout rules: App\Features\Dashboard\Livewire\DashboardBoard. --}}
<div class="grid gap-3">
    @if (! $ready)
        <div class="grid place-items-center py-10">
            <x-ts-spinner text="Loading dashboard…" />
        </div>
    @else
        <div class="flex items-center justify-between">
            <h2 class="m-0 text-base font-semibold">{{ __('common.dashboard') }}</h2>

            <div class="flex items-center gap-2">
                @if ($editing)
                    <x-ts-dropdown :text="__('ui.add_widget')" position="bottom-end" sm>
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

                    <x-ts-button sm flat type="button" wire:click="resetLayout">{{ __('ui.reset') }}</x-ts-button>
                @endif

                <x-ts-button sm :outline="! $editing" type="button" wire:click="toggleEditing">
                    {{ $editing ? __('ui.done') : __('ui.customize') }}
                </x-ts-button>
            </div>
        </div>

        @php($zones = ['grid' => $grid, 'sidebar' => $sidebar])

        <div class="grid grid-cols-1 gap-4 min-[1100px]:grid-cols-[1fr_320px] min-[1100px]:items-start">
            @foreach ($zones as $zone => $ids)
                <div
                    wire:key="zone-{{ $zone }}"
                    data-zone="{{ $zone }}"
                    @class(['grid min-h-[60px] content-start gap-3', 'grid-cols-2' => $zone === 'grid', 'grid-cols-1' => $zone === 'sidebar'])
                >
                    @foreach ($ids as $id)
                        @continue(! isset($catalog[$id]))

                        <div
                            wire:key="widget-{{ $id }}"
                            data-widget="{{ $id }}"
                            class="min-w-0"
                            @if ($zone === 'grid') style="grid-column: span {{ $catalog[$id]['span'] }} / span {{ $catalog[$id]['span'] }}" @endif
                        >
                            @php($widgetIcon = \Modules\PhpvmsAirsideTheme\Support\NavigationIcon::tallstack($catalog[$id]['icon'] ?? null))
                            <x-ts-card class="flex min-w-0 flex-col">
                                <x-slot:header>
                                    <div class="flex w-full items-center gap-2">
                                        @if ($editing)
                                            <span data-handle draggable="true" class="inline-flex cursor-grab text-dark-400 active:cursor-grabbing" aria-label="Drag to reorder" title="Drag">
                                                <x-ts-icon name="phosphor.dots-six-vertical.light" sm />
                                            </span>
                                        @endif
                                        @if ($widgetIcon)
                                            <x-ts-icon :name="$widgetIcon" sm gray />
                                        @endif
                                        <h3 class="m-0 text-sm font-semibold">{{ $catalog[$id]['title'] }}</h3>
                                        <div class="flex-1"></div>
                                        @if ($editing)
                                            <x-ts-button xs flat color="red" icon="phosphor.x.light" aria-label="Remove widget" wire:click="removeWidget('{{ $id }}')" />
                                        @endif
                                    </div>
                                </x-slot:header>

                                @if ($id === 'route')
                                    @include('livewire.dashboard.route-widget', ['route' => $route])
                                @elseif ($id === 'activity')
                                    <livewire:dashboard::activity-feed :key="'activity-feed'" />
                                @endif
                            </x-ts-card>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>

@script
<script>
    const STORAGE_KEY = 'airside.dashboard.layout.v1'
    const root = $wire.$el
    let dragged = null

    const read = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY)

            return raw ? JSON.parse(raw) : null
        } catch (error) {
            return null
        }
    }

    const write = (layout) => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(layout))
        } catch (error) {
            // Storage can be blocked; the layout then lasts for this page only.
        }
    }

    $wire.$on('dashboard-layout-changed', ({ grid, sidebar }) => write({ grid, sidebar }))
    setTimeout(() => $wire.loadLayout(read()), 0)

    const zoneAt = (target) => (target instanceof Element ? target.closest('[data-zone]') : null)

    // Where a dragged widget lands in a zone, counted without the dragged widget itself.
    const indexIn = (zone, x, y) => {
        const cells = [...zone.querySelectorAll(':scope > [data-widget]')].filter((cell) => cell.dataset.widget !== dragged)
        const zoneWidth = zone.getBoundingClientRect().width

        for (let index = 0; index < cells.length; index++) {
            const rect = cells[index].getBoundingClientRect()
            const fullRow = rect.width > zoneWidth * 0.9

            if (y < rect.top) {
                return index
            }

            if (y <= rect.bottom && (fullRow ? y < rect.top + rect.height / 2 : x < rect.left + rect.width / 2)) {
                return index
            }
        }

        return cells.length
    }

    const finish = () => {
        dragged = null
        root.querySelectorAll('.is-dragging').forEach((el) => el.classList.remove('is-dragging'))
        root.querySelectorAll('[data-over]').forEach((el) => el.removeAttribute('data-over'))
    }

    root.addEventListener('dragstart', (event) => {
        const handle = event.target instanceof Element ? event.target.closest('[data-handle]') : null
        const frame = handle?.closest('[data-widget]')

        if (!frame) {
            return
        }

        dragged = frame.dataset.widget
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/plain', dragged)
        event.dataTransfer.setDragImage(frame, 16, 16)
        requestAnimationFrame(() => frame.classList.add('is-dragging'))
    })

    root.addEventListener('dragover', (event) => {
        const zone = dragged ? zoneAt(event.target) : null

        if (!zone) {
            return
        }

        event.preventDefault()
        event.dataTransfer.dropEffect = 'move'
        root.querySelectorAll('[data-over]').forEach((el) => el !== zone && el.removeAttribute('data-over'))
        zone.setAttribute('data-over', '')
    })

    root.addEventListener('drop', (event) => {
        const zone = dragged ? zoneAt(event.target) : null

        if (!zone) {
            return
        }

        event.preventDefault()
        $wire.moveWidget(dragged, zone.dataset.zone, indexIn(zone, event.clientX, event.clientY))
        finish()
    })

    root.addEventListener('dragend', finish)

    // Widgets are rendered after the layout arrives, so an island that appears later
    // (the route map) is mounted by the same event the islands use on navigation.
    const seen = new WeakSet()
    new MutationObserver(() => {
        const fresh = [...root.querySelectorAll('[data-airside-island]')].filter((el) => !seen.has(el))

        if (fresh.length) {
            fresh.forEach((el) => seen.add(el))
            document.dispatchEvent(new CustomEvent('livewire:navigated'))
        }
    }).observe(root, { childList: true, subtree: true })
</script>
@endscript
