{{-- Markup the live map island clones: flight row, empty state, list error and selected-flight detail. Slot `empty` replaces the default empty state. It lives in a template so the theme build sees its classes. --}}
<template data-tpl="row">
    <button type="button" role="option" aria-selected="false" class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm">
        <span data-field="ident" class="font-mono font-semibold"></span>
        <span data-field="route" class="min-w-0 flex-1 truncate font-mono"></span>
        <span data-field="phase" class="rounded-md px-1.5 py-0.5 text-[11px]"></span>
    </button>
</template>

<template data-tpl="empty">
    @isset($empty)
        {{ $empty }}
    @else
        <x-vms::ui.empty icon="paper-airplane" title="No flights are live right now" description="Flights appear here as pilots depart." />
    @endisset
</template>

<template data-tpl="list-error">
    <p class="m-0 text-sm text-red-600" role="alert">Live flights could not be loaded. Retrying automatically.</p>
</template>

<template data-tpl="detail">
    <div class="pointer-events-auto max-h-[min(50vh,22rem)] overflow-y-auto rounded-md p-3" aria-label="Selected flight">
        <p data-field="status" class="m-0 text-sm" hidden></p>
        <p data-field="error" class="m-0 text-sm" role="alert" hidden>Flight details could not be loaded.</p>
        <a data-field="link" class="flex min-w-0 max-w-full items-center gap-2.5 hover:underline" hidden wire:navigate>
            <span class="grid size-8 shrink-0 place-items-center overflow-hidden rounded-md text-[11px] font-semibold">VA</span>
            <span class="flex min-w-0 flex-col items-start leading-tight">
                <span data-field="callsign" class="max-w-full truncate font-mono font-bold"></span>
                <span class="mt-0.5 inline-flex min-w-0 items-baseline gap-1.5 truncate font-mono font-medium">
                    <span data-field="dpt"></span><span aria-hidden="true">→</span><span class="sr-only">to</span><span data-field="arr"></span>
                </span>
            </span>
        </a>
    </div>
</template>
