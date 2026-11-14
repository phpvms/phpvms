{{-- The flight briefing: head, route map, timeline and the "Before you fly" rail. State comes from App\Features\Flights\Livewire\FlightBriefing. --}}
@php
    use App\Enums\BriefingItemKind;
    use App\Enums\BriefingStationRole;

    $detail = $this->detail;
    $flight = $detail['flight'];
    $policy = $detail['policy'];
    $bid = $detail['bid'];
    $summary = $flight->summary;
    $briefing = $this->briefing;
    $ofp = $briefing->ofp;
    $action = $summary->primaryAction;

    // Items at the same offset from departure stack in one entry; items without an offset follow in list order.
    $stacks = \Modules\PhpvmsAirsideTheme\Support\TimelineStack::stack($briefing->timeline, fn ($item) => $item->offsetMinutes);
    $hasAlternate = collect($briefing->timeline)->contains(fn ($item) => $item->role === BriefingStationRole::Alternate);

    $clock = function (?\Carbon\CarbonImmutable $at): ?string {
        if ($at === null) {
            return null;
        }
        $at = $at->utc();

        return ($at->isSameDay(now('UTC')) ? 'Today' : $at->format('j M')).' '.$at->format('H:i').'Z';
    };
    $level = fn (int $ft): string => $ft >= 18000 ? 'FL'.str_pad((string) intdiv($ft, 100), 3, '0', STR_PAD_LEFT) : number_format($ft).' ft';

    $hasBid = $action === 'overview';
    $canPlanOfp = $policy->simbriefEnabled && (! $policy->simbriefRequiresBid || $hasBid);
    $aircraft = $bid?->aircraft;
    $unavailable = $action === 'unavailable';
    $selecting = ! $hasBid && ! $unavailable;
    $subfleets = $selecting ? $this->selectionSubfleets() : collect();
    $aircraftChosen = $this->aircraftId !== null;
    // Without an aircraft policy, the aircraft is optional and the bid can be placed without one.
    $aircraftOptional = $selecting && ! $policy->aircraftRequired;
    $canBid = $selecting && ($aircraftChosen || $aircraftOptional);
    $steps = [
        'aircraft' => $hasBid || $aircraftChosen,
        'bid'      => $hasBid,
        'ofp'      => $ofp !== null,
        'fly'      => false,
    ];
    $done = count(array_filter($steps));
    $current = collect($steps)->reject(fn ($isDone, $key) => $key === 'aircraft' && $aircraftOptional)->search(false);
@endphp

<div class="grid gap-4">
    <header class="vms-briefing-head grid gap-3.5 px-1 pt-1.5">
        <x-vms::flights.ident
            size="lg"
            :callsign="$summary->callsign"
            :dpt="$summary->dpt"
            :arr="$summary->arr"
            :airline="$summary->airline?->name"
            :logo="$summary->airline?->logo"
        />
        <div class="flex flex-wrap gap-1.5">
            @if ($summary->type)
                <span class="vms-flight-pill px-2.5 py-0.5 text-xs font-semibold">{{ $summary->type }}</span>
            @endif
            @if ($summary->distanceNm !== null)
                <span class="vms-flight-pill px-2.5 py-0.5 text-xs font-semibold tabular-nums">{{ number_format($summary->distanceNm) }} nm</span>
            @endif
            @if ($summary->blockTime)
                <span class="vms-flight-pill px-2.5 py-0.5 text-xs font-semibold tabular-nums">{{ $summary->blockTime }} block</span>
            @endif
            @if ($flight->cruiseLevel !== null)
                <span class="vms-flight-pill px-2.5 py-0.5 font-mono text-xs font-medium">FL{{ $flight->cruiseLevel }}</span>
            @endif
            @if ($summary->routeCode)
                <span class="vms-flight-pill px-2.5 py-0.5 text-xs font-semibold">{{ $summary->routeCode }}</span>
            @endif
            @if ($ofp)
                <span class="vms-flight-pill vms-flight-pill--bid inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold"><x-ts-icon name="phosphor.file-text.light" class="size-3.5" />OFP ready</span>
            @endif
        </div>
    </header>

    <div class="grid items-start gap-4 min-[1100px]:grid-cols-[minmax(0,1fr)_22.5rem]">
        <div class="grid min-w-0 gap-4">
            <section class="vms-briefing-map rounded-(--vms-radius-lg) border border-gray-200 bg-white p-3.5 dark:border-dark-700 dark:bg-dark-800" aria-label="Route">
                <x-vms::flights.route-map :flight="$flight" :fixes="$briefing->plannedFixes" :overlay="false" />
            </section>

            <section class="vms-briefing-timeline rounded-(--vms-radius-lg) border border-gray-200 bg-white py-2 pr-4 pl-2 sm:pr-7 sm:pl-4.5 dark:border-dark-700 dark:bg-dark-800" aria-label="Flight timeline">
                <x-vms::timeline>
                    @foreach ($stacks as $stackIndex => $stack)
                        @php
                            // The first item of a stack sets the entry's time, node and density.
                            $lead = $stack[0];
                            $leadKind = $lead->kind;
                            $leadIsStation = $leadKind === BriefingItemKind::Station;
                            [$time, $caption, $offset] = match (true) {
                                $leadIsStation && $lead->role === BriefingStationRole::Departure => [$flight->scheduledDeparture, 'departs', null],
                                $leadIsStation && $lead->role === BriefingStationRole::Arrival => [$flight->scheduledArrival, 'arrives', null],
                                $leadIsStation && $lead->role === BriefingStationRole::Alternate => [null, 'alternate', null],
                                $leadKind === BriefingItemKind::Route => [$summary->blockTime, 'block', null],
                                default => [null, null, $lead->offsetMinutes],
                            };
                            $node = match (true) {
                                $leadKind === BriefingItemKind::Phase => 'phase',
                                $leadKind === BriefingItemKind::Fix => 'fix',
                                $leadIsStation && $lead->role === BriefingStationRole::Alternate => 'alt',
                                $leadKind === BriefingItemKind::Route => 'hidden',
                                default => null,
                            };
                        @endphp
                        <x-vms::timeline.entry
                            wire:key="timeline-{{ $stackIndex }}"
                            :offset="$offset"
                            :time="$time"
                            :caption="$caption"
                            :node="$node"
                            :compact="$leadKind === BriefingItemKind::Fix || $leadKind === BriefingItemKind::Phase"
                        >
                            @foreach ($stack as $itemIndex => $item)
                                @php
                                    $kind = $item->kind;
                                    $isStation = $kind === BriefingItemKind::Station;
                                    $isAlternate = $isStation && $item->role === BriefingStationRole::Alternate;
                                @endphp
                                @if ($isStation)
                                    <h2 class="m-0 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                        @if ($isAlternate && ! $item->anchor)
                                            <span class="text-sm font-semibold">{{ $item->title }}</span>
                                        @else
                                            <b class="text-3xl leading-none font-extrabold tracking-tight">{{ $item->anchor ?? '—' }}</b>
                                            <span class="text-sm font-semibold text-dark-600 dark:text-dark-300">{{ $item->title }}</span>
                                        @endif
                                        @if ($isAlternate)
                                            <span class="vms-flight-pill self-center px-2.5 py-0.5 text-xs font-semibold">{{ $item->role->getLabel() }}</span>
                                        @endif
                                    </h2>

                                    @if ($isStation && $item->role === BriefingStationRole::Departure)
                                        <dl class="vms-briefing-facts m-0 grid grid-cols-3 gap-3.5 sm:grid-cols-5">
                                            @foreach ([
                                                'Distance' => $summary->distanceNm === null ? '—' : number_format($summary->distanceNm).' nm',
                                                'Block' => $summary->blockTime ?? '—',
                                                'Cruise' => $flight->cruiseLevel === null ? '—' : 'FL'.$flight->cruiseLevel,
                                                'Subfleet' => $detail['subfleet'] ?? '—',
                                                'Alternate' => $flight->alternate?->icao ?? 'None',
                                            ] as $label => $value)
                                                <div class="min-w-0">
                                                    <dt class="text-xs font-semibold text-dark-500 dark:text-dark-400">{{ $label }}</dt>
                                                    <dd class="m-0 mt-0.5 truncate text-[14.5px] font-bold tabular-nums">{{ $value }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    @endif

                                    @if ($item->anchor)
                                        <livewire:flights::airport-weather lazy :key="'briefing-weather-'.$stackIndex.'-'.$itemIndex.'-'.$item->anchor" :icao="$item->anchor" />
                                    @endif
                                @elseif ($kind === BriefingItemKind::Route)
                                    <div class="vms-briefing-route flex flex-wrap items-center gap-x-0.5 gap-y-1" aria-label="Filed route {{ $item->title }}">
                                        @foreach (preg_split('/\s+/', trim($item->title)) ?: [] as $token)
                                            <span class="vms-flight-pill border border-gray-200 px-2.5 py-0.5 font-mono text-[11.5px] font-semibold dark:border-dark-700">{{ $token }}</span>
                                        @endforeach
                                    </div>
                                @elseif ($kind === BriefingItemKind::Notice)
                                    @php
                                        $tone = match ($item->severity->getColor()) {
                                            'danger' => 'text-red-600 dark:text-red-400',
                                            'warning' => 'text-amber-700 dark:text-amber-400',
                                            default => 'text-dark-600 dark:text-dark-300',
                                        };
                                    @endphp
                                    <div class="vms-briefing-notice flex items-start gap-2.5 overflow-hidden rounded-2xl border border-gray-200 px-3.5 py-2.5 dark:border-dark-700" data-severity="{{ $item->severity->value }}">
                                        <x-vms::meteocon :name="$item->icon ?? 'not-available-fill'" class="-mt-1 -mr-0.5 -ml-1 size-9 shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <p class="m-0 text-[13.5px] font-bold {{ $tone }}">{{ $item->title }}</p>
                                            @if ($item->detail)
                                                <p class="m-0 mt-px text-[12.5px] leading-normal text-dark-500 dark:text-dark-400">{{ $item->detail }}</p>
                                            @endif
                                        </div>
                                        @if ($item->at)
                                            <span class="shrink-0 text-xs tabular-nums whitespace-nowrap text-dark-500 max-sm:hidden dark:text-dark-400">{{ $clock($item->at) }}</span>
                                        @endif
                                    </div>
                                @else
                                    <div class="flex flex-wrap items-center gap-2.5 text-[13px] text-dark-600 dark:text-dark-300">
                                        @if ($kind === BriefingItemKind::Fix)
                                            <span class="vms-flight-pill border border-gray-200 px-2.5 py-0.5 font-mono text-[11.5px] font-semibold dark:border-dark-700">{{ $item->title }}</span>
                                            @if ($item->viaAirway)
                                                <span class="font-mono text-[11.5px] text-dark-500 dark:text-dark-400">{{ $item->viaAirway }}</span>
                                            @endif
                                            @if ($item->detail)
                                                <span>{{ $item->detail }}</span>
                                            @endif
                                        @else
                                            <b class="text-dark-900 dark:text-dark-50">{{ $item->title }}</b>
                                        @endif
                                        @if ($item->altitudeFt !== null)
                                            <span class="ml-auto font-mono text-xs font-semibold tabular-nums">{{ $level($item->altitudeFt) }}</span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </x-vms::timeline.entry>
                    @endforeach

                    @unless ($hasAlternate)
                        <x-vms::timeline.entry caption="alternate" node="alt">
                            <p class="m-0 text-[13.5px] text-dark-500 dark:text-dark-400">No alternate airport on this flight.</p>
                        </x-vms::timeline.entry>
                    @endunless
                </x-vms::timeline>
            </section>
        </div>

        <aside class="vms-briefing-rail grid gap-1 rounded-(--vms-radius-lg) border border-gray-200 bg-white p-2 min-[1100px]:sticky min-[1100px]:top-5 dark:border-dark-700 dark:bg-dark-800" aria-label="Before you fly">
            <div class="px-4 pt-4 pb-3">
                <h2 class="m-0 text-lg font-extrabold tracking-tight">Before you fly</h2>
                <p class="m-0 mt-1 text-[13px] text-dark-500 dark:text-dark-400">{{ $done }} of 4 done.</p>
                <div class="mt-3.5 grid grid-cols-4 gap-1" aria-hidden="true">
                    @foreach (array_values($steps) as $index => $isDone)
                        <span @class(['h-1 rounded-full', 'bg-primary-600 dark:bg-primary-400' => $isDone, 'bg-gray-200 dark:bg-dark-700' => ! $isDone])></span>
                    @endforeach
                </div>
            </div>

            <ol class="m-0 list-none p-0">
                @php
                    $stepItems = [
                        ['key' => 'aircraft', 'number' => 1,
                            'title' => $hasBid ? ($aircraft ? 'Aircraft assigned' : 'No aircraft chosen') : 'Choose an aircraft',
                            'note' => $hasBid
                                ? ($aircraft ? $aircraft->registration.' · '.($aircraft->subfleet?->name ?? $aircraft->icao) : 'This is a flight-only bid.')
                                : ($unavailable ? ($summary->availabilityReason ?? 'This flight is unavailable.') : ($aircraftOptional ? 'Optional. Without one, the bid is for the flight only.' : null))],
                        ['key' => 'bid', 'number' => 2,
                            'title' => $hasBid ? 'In your schedule' : 'Bid on this flight',
                            'note' => $hasBid ? 'You are scheduled for this flight.' : ($unavailable ? null : ($canBid ? 'Places the bid with the chosen aircraft.' : 'Choose an aircraft first.'))],
                        ['key' => 'ofp', 'number' => 3, 'title' => $ofp ? 'OFP generated' : 'Generate the OFP', 'note' => $ofp ? null : (! $policy->simbriefEnabled ? 'SimBrief is not enabled on this site.' : ($canPlanOfp ? 'SimBrief builds it. Its fixes fill in the timeline.' : 'Bid on the flight first.'))],
                        ['key' => 'fly', 'number' => 4, 'title' => 'Fly', 'note' => null],
                    ];
                @endphp
                @foreach ($stepItems as $step)
                    @php
                        $isDone = $steps[$step['key']];
                        $isNow = $current === $step['key'] || ($step['key'] === 'fly' && $current === 'fly');
                    @endphp
                    <li wire:key="step-{{ $step['key'] }}" @class(['vms-briefing-step grid grid-cols-[2rem_minmax(0,1fr)] gap-3 rounded-2xl px-4 py-3.5', 'vms-briefing-step--now' => $isNow]) data-state="{{ $isDone ? 'done' : ($isNow ? 'now' : 'todo') }}">
                        <span @class(['grid size-8 place-items-center rounded-full border-[1.5px] text-[13px] font-extrabold', 'border-transparent bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $isDone, 'border-primary-600 bg-primary-600 text-white dark:border-primary-400 dark:bg-primary-400 dark:text-dark-950' => $isNow && ! $isDone, 'border-gray-200 text-dark-500 dark:border-dark-700 dark:text-dark-400' => ! $isDone && ! $isNow])>
                            @if ($isDone)
                                <x-ts-icon name="phosphor.check.light" class="size-4" />
                            @else
                                {{ $step['number'] }}
                            @endif
                        </span>
                        <div class="grid min-w-0 content-start gap-3">
                            <div>
                                <b class="block pt-1.5 text-[14.5px] font-bold">{{ $step['title'] }}</b>
                                @if ($step['note'])
                                    <p class="m-0 mt-0.5 text-[13px] text-dark-500 dark:text-dark-400">{{ $step['note'] }}</p>
                                @endif
                            </div>

                            @if ($step['key'] === 'aircraft' && $selecting)
                                <x-vms::bids.aircraft-selection
                                    :subfleets="$subfleets"
                                    :aircraft="$this->aircraftList"
                                    :subfleet-id="$this->subfleetId"
                                    :aircraft-id="$this->aircraftId"
                                    :editing-subfleet="$this->editingSubfleet"
                                    :editing-aircraft="$this->editingAircraft"
                                    :required="$policy->aircraftRequired"
                                    :heading="false"
                                />
                            @elseif ($step['key'] === 'bid' && ! $hasBid)
                                @if ($unavailable)
                                    <x-ts-button type="button" block disabled>Unavailable</x-ts-button>
                                @else
                                    @if ($this->failureMessage)
                                        <x-ts-alert color="red" light shadowless role="alert" data-failure="{{ $this->failureType }}">{{ $this->failureMessage }}</x-ts-alert>
                                    @endif
                                    <x-ts-button type="button" block wire:click="placeBid" wire:loading.attr="disabled" wire:target="placeBid" :disabled="! $canBid">
                                        <span wire:loading.remove wire:target="placeBid">Bid on flight</span>
                                        <span wire:loading wire:target="placeBid">Placing bid</span>
                                    </x-ts-button>
                                @endif
                            @elseif ($step['key'] === 'ofp')
                                @if ($ofp)
                                    <dl class="vms-briefing-ofp m-0 grid grid-cols-2 gap-x-3.5 gap-y-2.5 rounded-xl border border-gray-200 p-3 dark:border-dark-700">
                                        @foreach ([
                                            'Block fuel' => $ofp->blockFuel === null ? null : number_format($ofp->blockFuel).' '.$ofp->weightUnit,
                                            'Air time' => $ofp->airTimeMinutes === null ? null : intdiv($ofp->airTimeMinutes, 60).':'.str_pad((string) ($ofp->airTimeMinutes % 60), 2, '0', STR_PAD_LEFT),
                                            'ZFW' => $ofp->zeroFuelWeight === null ? null : number_format($ofp->zeroFuelWeight).' '.$ofp->weightUnit,
                                            'Runways' => ($ofp->departureRunway || $ofp->arrivalRunway) ? ($ofp->departureRunway ?? '—').' / '.($ofp->arrivalRunway ?? '—') : null,
                                            'Aircraft' => $ofp->aircraftRegistration,
                                        ] as $label => $value)
                                            @if ($value !== null)
                                                <div class="min-w-0">
                                                    <dt class="text-xs font-semibold text-dark-500 dark:text-dark-400">{{ $label }}</dt>
                                                    <dd class="m-0 truncate text-sm font-bold tabular-nums">{{ $value }}</dd>
                                                </div>
                                            @endif
                                        @endforeach
                                    </dl>
                                    <x-ts-button :href="$ofp->briefingUrl" wire:navigate light icon="phosphor.file-text.light">View OFP</x-ts-button>
                                @elseif ($canPlanOfp)
                                    <x-ts-button :href="$this->ofpPlanningUrl" wire:navigate light icon="phosphor.file-text.light">Generate OFP</x-ts-button>
                                @endif
                            @elseif ($step['key'] === 'fly')
                                <div class="vms-briefing-fly grid gap-2.5">
                                    <p class="m-0 text-[13px] text-dark-500 dark:text-dark-400">Start the flight from your client when the steps above are done.</p>
                                    <div class="flex flex-wrap items-center gap-2.5 not-has-[*]:hidden">
                                        <x-render-hook :hook="new \App\Features\Hooks\Points\App\Slots\SlotFlightActions($summary, $policy, auth()->user(), $bid, null)" />
                                    </div>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </aside>
    </div>
</div>
