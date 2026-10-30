@props(['title', 'widgetId', 'icon' => null, 'editing' => false, 'removable' => true])

{{--
  Card chrome around a dashboard widget (WidgetFrame). Props: title, widgetId, icon, editing (shows the drag
  handle and the remove button), removable. Slot: default = the widget body.
--}}
<section {{ $attributes->class(['pv-widget-frame flex min-w-0 flex-col overflow-hidden rounded-xl border border-line bg-panel', 'is-editing' => $editing]) }}>
    <header class="flex items-center gap-2 border-b border-line px-3 py-[9px]">
        @if ($editing)
            <span data-handle draggable="true" class="inline-flex cursor-grab text-ink-faint active:cursor-grabbing" aria-label="Drag to reorder" title="Drag">
                <svg viewBox="0 0 24 24" class="size-[15px]" fill="currentColor" aria-hidden="true">
                    <circle cx="9" cy="6" r="1.4" />
                    <circle cx="15" cy="6" r="1.4" />
                    <circle cx="9" cy="12" r="1.4" />
                    <circle cx="15" cy="12" r="1.4" />
                    <circle cx="9" cy="18" r="1.4" />
                    <circle cx="15" cy="18" r="1.4" />
                </svg>
            </span>
        @endif
        @if ($icon)
            <x-airside::icon :name="$icon" class="size-[15px] text-ink-dim" />
        @endif
        <h3 class="m-0 text-xs font-semibold text-ink">{{ $title }}</h3>
        <div class="flex-1"></div>
        @if ($editing && $removable)
            <button type="button" class="inline-flex rounded-sm p-0.5 text-ink-dim hover:bg-red-500/10 hover:text-red-600" aria-label="Remove widget" wire:click="removeWidget('{{ $widgetId }}')">
                <svg viewBox="0 0 24 24" class="size-[15px]" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M18 6 6 18M6 6l12 12" />
                </svg>
            </button>
        @endif
    </header>
    <div class="flex-1 p-3">{{ $slot }}</div>
</section>
