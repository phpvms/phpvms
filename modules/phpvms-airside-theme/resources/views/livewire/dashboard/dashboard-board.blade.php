{{-- The dashboard toolbar and board. State and layout rules: App\Features\Dashboard\Livewire\DashboardBoard. --}}
<div class="pv-dashboard-board-root grid gap-3">
    @if (! $ready)
        <div class="grid place-items-center py-10">
            <x-airside::loading text="Loading dashboard…" />
        </div>
    @else
        <div class="pv-dashboard-toolbar flex items-center justify-between">
            <h2 class="m-0 text-[15px] font-semibold text-ink">{{ __('common.dashboard') }}</h2>

            <div class="flex items-center gap-2">
                @if ($editing)
                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                        <x-ts-button sm outline type="button" x-on:click="open = ! open" aria-haspopup="menu" x-bind:aria-expanded="open ? 'true' : 'false'">
                            {{ __('ui.add_widget') }}
                        </x-ts-button>

                        <div x-show="open" x-cloak role="menu" class="absolute top-[calc(100%+6px)] right-0 z-40 min-w-[200px] rounded-lg border border-line bg-panel p-1 shadow-lg">
                            @forelse ($this->availableWidgets as $widget)
                                <button
                                    wire:key="add-{{ $widget['id'] }}"
                                    type="button"
                                    role="menuitem"
                                    class="flex h-8 w-full items-center gap-2 rounded-sm px-2 text-xs text-ink hover:bg-panel-inset"
                                    wire:click="addWidget('{{ $widget['id'] }}')"
                                    x-on:click="open = false"
                                >
                                    <x-airside::icon :name="$widget['icon']" class="size-[15px] text-ink-dim" />
                                    {{ $widget['title'] }}
                                </button>
                            @empty
                                <div class="p-2 text-xs text-ink-dim">{{ __('ui.all_widgets_placed') }}</div>
                            @endforelse
                        </div>
                    </div>

                    <x-ts-button sm flat type="button" wire:click="resetLayout">{{ __('ui.reset') }}</x-ts-button>
                @endif

                <x-ts-button sm :outline="! $editing" type="button" wire:click="toggleEditing">
                    {{ $editing ? __('ui.done') : __('ui.customize') }}
                </x-ts-button>
            </div>
        </div>

        @php($zones = ['grid' => $grid, 'sidebar' => $sidebar])

        <div class="pv-dashboard-board grid grid-cols-1 gap-4 min-[1100px]:grid-cols-[1fr_320px] min-[1100px]:items-start">
            @foreach ($zones as $zone => $ids)
                <div
                    wire:key="zone-{{ $zone }}"
                    data-zone="{{ $zone }}"
                    @class(['pv-dashboard-zone grid min-h-[60px] content-start gap-3', 'grid-cols-2' => $zone === 'grid', 'grid-cols-1' => $zone === 'sidebar'])
                >
                    @foreach ($ids as $id)
                        @continue(! isset($catalog[$id]))

                        <div
                            wire:key="widget-{{ $id }}"
                            data-widget="{{ $id }}"
                            class="min-w-0"
                            @if ($zone === 'grid') style="grid-column: span {{ $catalog[$id]['span'] }} / span {{ $catalog[$id]['span'] }}" @endif
                        >
                            <x-airside::dashboard-widget-frame :title="$catalog[$id]['title']" :icon="$catalog[$id]['icon']" :widget-id="$id" :editing="$editing">
                                @if ($id === 'route')
                                    <x-airside::dashboard-route-widget :route="$route" />
                                @elseif ($id === 'activity')
                                    <livewire:dashboard::activity-feed :key="'activity-feed'" />
                                @endif
                            </x-airside::dashboard-widget-frame>
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
