{{-- The briefing page's actions. State comes from App\Features\Ofp\Livewire\BriefingActions. --}}
@php
    $briefing = $this->briefing;
    $prefileLinks = array_filter($briefing->prefileLinks);
@endphp
<x-ts-card header="Actions" class="min-w-0" aria-label="Actions">
    <div class="grid content-start gap-3">
        @if ($failure)
            <x-ts-alert color="red" light shadowless role="alert">{{ $failure }}</x-ts-alert>
        @endif

        <x-render-hook :fills="$this->actionFills" />
        @if (\App\View\Components\RenderHook::renderable($this->actionFills) !== [])
            <hr />
        @endif

        <div class="grid gap-2" x-data="{ editorOpen: false, editorLoading: true }">
            @if ($briefing->editorUrl)
                <x-ts-button type="button" class="w-full" x-on:click="editorLoading = true; editorOpen = true">Edit OFP</x-ts-button>
            @endif

            @if ($briefing->canRegenerate)
                <x-ts-button type="button" color="slate" light class="w-full" wire:click="regenerate" wire:loading.attr="disabled" wire:target="regenerate">Regenerate OFP</x-ts-button>
            @endif

            @if ($briefing->canCancel)
                <x-ts-button
                    type="button"
                    color="red"
                    light
                    class="w-full"
                    data-prompt="Cancel this unused briefing? This cannot be undone."
                    data-confirm="Confirm cancellation"
                    data-keep="Keep briefing"
                    x-on:click="$tsui.interaction('dialog').wireable($wire.$id).question($escapeHtml($el.dataset.prompt)).confirm($el.dataset.confirm, 'cancel').cancel($el.dataset.keep).send()"
                >Cancel unused briefing</x-ts-button>
            @endif

            @if (count($briefing->downloads))
                <x-vms::ui.drawer name="ofp-downloads" title="Downloads" description="Files generated with this SimBrief briefing.">
                    <x-slot:trigger>
                        <x-ts-button type="button" color="slate" light class="w-full">
                            <x-vms::ui.icon name="arrow-down-tray" />
                            Downloads
                        </x-ts-button>
                    </x-slot:trigger>

                    <section class="grid gap-2" aria-label="Downloads">
                        <p>Briefing downloads</p>
                        @foreach ($briefing->downloads as $download)
                            <a class="text-sm hover:underline" href="{{ $download['url'] }}" target="_blank" rel="noopener">{{ $download['name'] }}</a>
                        @endforeach
                    </section>
                </x-vms::ui.drawer>
            @endif

            @if ($briefing->editorUrl)
                <template x-teleport="body">
                    <div x-show="editorOpen" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" aria-label="Edit OFP" x-on:keydown.escape.window="editorOpen = false">
                        <div class="absolute inset-0 bg-black/50" x-on:click="editorOpen = false" aria-hidden="true"></div>
                        <section class="relative grid h-[min(920px,94dvh)] w-full max-w-5xl grid-rows-[auto_minmax(0,1fr)_auto] overflow-hidden rounded-lg" x-trap.noscroll="editorOpen" aria-label="Edit SimBrief operational flight plan">
                            <header class="flex items-start justify-between gap-4 px-5 py-4">
                                <div class="grid min-w-0 gap-1">
                                    <p>SIMBRIEF · EDIT OFP</p>
                                    <h2 class="font-mono text-xl" tabindex="-1">{{ $briefing->flight->summary->callsign }} · {{ $briefing->flight->summary->dpt ?? '—' }} → {{ $briefing->flight->summary->arr ?? '—' }}</h2>
                                    <p class="text-[13px]">Changes are made securely in SimBrief and remain subject to its own session and controls.</p>
                                </div>
                                <button type="button" class="rounded-md p-1" x-on:click="editorOpen = false" aria-label="Close OFP editor">
                                    <x-vms::ui.icon name="x-mark" />
                                </button>
                            </header>

                            <div class="relative min-h-0">
                                <div x-show="editorLoading" class="absolute inset-0 z-10 grid place-items-center" role="status" aria-live="polite">
                                    <div class="grid justify-items-center gap-1 text-center">
                                        <div class="inline-flex min-w-0 items-center" role="status" aria-live="polite" aria-label="Loading"><x-ts-spinner sm /></div>
                                        <strong>Opening SimBrief editor</strong>
                                        <p class="text-[13px]">The editor is loading in a protected embedded window.</p>
                                    </div>
                                </div>
                                <template x-if="editorOpen">
                                    <iframe class="size-full border-0" src="{{ $briefing->editorUrl }}" title="SimBrief OFP editor" x-on:load="editorLoading = false"></iframe>
                                </template>
                            </div>

                            <footer class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <p class="max-w-md text-xs">The embedded editor is operated by SimBrief. phpVMS cannot inspect or control its cross-origin content.</p>
                                <div class="flex flex-wrap gap-2">
                                    <x-ts-button type="button" color="slate" light x-on:click="editorOpen = false">Close</x-ts-button>
                                    <x-ts-button type="button" x-on:click="$wire.syncEdited().then((ready) => { if (ready) editorOpen = false })" wire:loading.attr="disabled" wire:target="syncEdited">Return to briefing / Download updated OFP</x-ts-button>
                                </div>
                            </footer>
                        </section>
                    </div>
                </template>
            @endif
        </div>

        @if (count($prefileLinks))
            <p>Network prefile</p>
            @foreach ($prefileLinks as $name => $url)
                <a class="text-sm hover:underline" href="{{ $url }}" target="_blank" rel="noopener">{{ $name }}</a>
            @endforeach
        @endif
    </div>
</x-ts-card>
