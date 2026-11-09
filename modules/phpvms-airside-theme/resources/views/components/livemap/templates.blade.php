@props(['detailed' => false])

{{--
  Markup the live map island clones: flight row, empty and no-match states, list error and
  selected-flight detail. Slot `empty` replaces the default empty state. `detailed` swaps the
  compact detail (callsign and route link, as the dashboard widget shows it) for the full one
  the live map page shows. It lives in a template so the theme build sees its classes.
--}}
<template data-tpl="row">
    <button type="button" role="option" aria-selected="false" class="vms-live-row grid w-full grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5 px-3 py-2.5 text-left">
        <span class="min-w-0">
            <span class="flex min-w-0 items-baseline gap-2">
                <span data-field="ident" class="truncate text-sm font-bold"></span>
                <span data-field="route" class="vms-live-row-route shrink-0 font-mono text-xs"></span>
            </span>
            <span data-field="meta" class="vms-live-muted block truncate text-xs"></span>
        </span>
        <span class="grid justify-items-end gap-1">
            <span data-field="phase" class="vms-live-phase rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap"></span>
            <span data-field="altitude" class="vms-live-muted text-xs whitespace-nowrap tabular-nums"></span>
        </span>
        <progress data-field="progress" max="100" value="0" class="vms-live-progress col-span-2 h-[3px] w-full" aria-label="Progress"></progress>
    </button>
</template>

<template data-tpl="empty">
    @isset($empty)
        {{ $empty }}
    @else
        <x-vms::ui.empty icon="paper-airplane" title="No flights are live right now" description="Flights appear here as pilots depart." />
    @endisset
</template>

<template data-tpl="no-match">
    <p class="vms-live-muted m-0 px-3 py-6 text-center text-sm">No live flights match. Change the search or the filter.</p>
</template>

<template data-tpl="list-error">
    <p class="m-0 text-sm text-red-600" role="alert">Live flights could not be loaded. Retrying automatically.</p>
</template>

@if ($detailed)
    <template data-tpl="detail">
        <div class="vms-live-detail grid max-h-[calc(100dvh-15rem)] gap-4 overflow-y-auto p-5 max-md:max-h-[45dvh] max-md:gap-3 max-md:p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="vms-live-eyebrow m-0 text-[11px] font-bold tracking-[0.14em] uppercase">Selected flight</p>
                <button type="button" data-action="close" class="vms-live-close grid size-8 cursor-pointer place-items-center rounded-full" aria-label="Close flight details">
                    <x-ts-icon name="phosphor.x.light" class="size-4" />
                </button>
            </div>

            <p data-field="status" class="vms-live-muted m-0 text-sm" hidden>Loading…</p>
            <p data-field="error" class="vms-live-error m-0 text-sm" role="alert" hidden>Flight details could not be loaded.</p>

            <div data-field="body" class="grid gap-4" hidden>
                <div class="flex min-w-0 items-center gap-3">
                    <span class="vms-live-logo grid size-10 shrink-0 place-items-center overflow-hidden rounded-full text-[11px] font-bold">
                        <img data-field="logo" alt="" class="size-full object-contain" hidden />
                        <span data-field="logo-text"></span>
                    </span>
                    <span class="min-w-0">
                        <span data-field="callsign" class="block truncate text-xl font-bold tracking-tight"></span>
                        <span data-field="airline" class="vms-live-muted block truncate text-xs"></span>
                    </span>
                </div>

                <div class="vms-live-route grid gap-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <span class="min-w-0">
                            <span data-field="dpt" class="block font-mono text-xl font-semibold"></span>
                            <span data-field="dpt-name" class="vms-live-muted block truncate text-xs"></span>
                        </span>
                        <span class="min-w-0 text-right">
                            <span data-field="arr" class="block font-mono text-xl font-semibold"></span>
                            <span data-field="arr-name" class="vms-live-muted block truncate text-xs"></span>
                        </span>
                    </div>
                    <progress data-field="progress" max="100" value="0" class="vms-live-progress h-1 w-full" aria-label="Progress"></progress>
                    <div class="vms-live-muted flex justify-between gap-2 text-xs tabular-nums">
                        <span data-field="off"></span>
                        <span data-field="flown"></span>
                        <span data-field="eta"></span>
                    </div>
                </div>

                <ol class="vms-live-stages m-0 grid list-none grid-cols-4 gap-1 p-0" aria-label="Flight stage">
                    <li data-stage="ground" class="grid gap-1.5 text-[11px]"><span class="vms-live-stage-bar h-1 rounded-full"></span>Ground</li>
                    <li data-stage="departing" class="grid gap-1.5 text-[11px]"><span class="vms-live-stage-bar h-1 rounded-full"></span>Departing</li>
                    <li data-stage="enroute" class="grid gap-1.5 text-[11px]"><span class="vms-live-stage-bar h-1 rounded-full"></span>En route</li>
                    <li data-stage="arriving" class="grid gap-1.5 text-[11px]"><span class="vms-live-stage-bar h-1 rounded-full"></span>Arriving</li>
                </ol>

                <dl class="vms-live-facts m-0 grid grid-cols-3 gap-x-3 gap-y-3.5">
                    <div><dt class="text-[10px] font-bold tracking-widest uppercase">Phase</dt><dd data-field="phase" class="m-0 mt-1 truncate text-sm font-semibold"></dd></div>
                    <div><dt class="text-[10px] font-bold tracking-widest uppercase">Altitude</dt><dd data-field="altitude" class="m-0 mt-1 text-sm font-semibold tabular-nums"></dd></div>
                    <div><dt class="text-[10px] font-bold tracking-widest uppercase">Heading</dt><dd data-field="heading" class="m-0 mt-1 text-sm font-semibold tabular-nums"></dd></div>
                    <div><dt class="text-[10px] font-bold tracking-widest uppercase">Cruise</dt><dd data-field="cruise" class="m-0 mt-1 text-sm font-semibold tabular-nums"></dd></div>
                    <div><dt class="text-[10px] font-bold tracking-widest uppercase">Flown</dt><dd data-field="flight-time" class="m-0 mt-1 text-sm font-semibold tabular-nums"></dd></div>
                    <div><dt class="text-[10px] font-bold tracking-widest uppercase">Planned</dt><dd data-field="planned-time" class="m-0 mt-1 text-sm font-semibold tabular-nums"></dd></div>
                </dl>

                <div class="vms-live-pilot flex min-w-0 items-center gap-3 pt-3.5">
                    <span data-field="avatar" class="vms-live-avatar grid size-8 shrink-0 place-items-center rounded-full text-[11px] font-bold"></span>
                    <span class="min-w-0 flex-1">
                        <span data-field="pilot" class="block truncate text-sm font-semibold"></span>
                        <span data-field="aircraft" class="vms-live-muted block truncate text-xs"></span>
                    </span>
                    <a data-field="link" class="vms-live-open shrink-0 rounded-full px-3.5 py-1.5 text-xs font-semibold" wire:navigate>Open PIREP</a>
                </div>
            </div>
        </div>
    </template>
@else
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
@endif
