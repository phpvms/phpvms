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

    // Notices sit under the first row (not a notice) with their anchor; the rest go under the departure station.
    $rows = collect($briefing->timeline)->reject(fn ($item) => $item->kind === BriefingItemKind::Notice)->values();
    $departureIndex = $rows->search(fn ($item) => $item->role === BriefingStationRole::Departure) ?: 0;
    $noticesUnder = [];
    foreach ($briefing->timeline as $item) {
        if ($item->kind !== BriefingItemKind::Notice) {
            continue;
        }
        $index = $item->anchor === null ? false : $rows->search(fn ($row) => $row->anchor === $item->anchor);
        $noticesUnder[$index === false ? $departureIndex : $index][] = $item;
    }
    $hasAlternate = $rows->contains(fn ($item) => $item->role === BriefingStationRole::Alternate);

    $clock = function (?\Carbon\CarbonImmutable $at): ?string {
        if ($at === null) {
            return null;
        }
        $at = $at->utc();

        return ($at->isSameDay(now('UTC')) ? 'Today' : $at->format('j M')).' '.$at->format('H:i').'Z';
    };
    $level = fn (int $ft): string => $ft >= 18000 ? 'FL'.str_pad((string) intdiv($ft, 100), 3, '0', STR_PAD_LEFT) : number_format($ft).' ft';
    $elapsed = fn (int $minutes): string => '+'.intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);

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
                <ol class="m-0 list-none p-0">
                    @foreach ($rows as $index => $item)
                        @php
                            $kind = $item->kind;
                            $isStation = $kind === BriefingItemKind::Station;
                            $compact = $kind === BriefingItemKind::Fix || $kind === BriefingItemKind::Phase;
                            $isAlternate = $isStation && $item->role === BriefingStationRole::Alternate;
                            $timeText = match (true) {
                                $isStation && $item->role === BriefingStationRole::Departure => [$flight->scheduledDeparture, 'departs'],
                                $isStation && $item->role === BriefingStationRole::Arrival => [$flight->scheduledArrival, 'arrives'],
                                $isAlternate => [null, 'alternate'],
                                $kind === BriefingItemKind::Route => [$summary->blockTime, 'block'],
                                $item->elapsedMinutes !== null => [$elapsed($item->elapsedMinutes), null],
                                default => [null, null],
                            };
                            $nodeClass = match (true) {
                                $kind === BriefingItemKind::Phase => 'vms-timeline-node--phase',
                                $kind === BriefingItemKind::Fix => 'vms-timeline-node--fix',
                                $isAlternate => 'vms-timeline-node--alt',
                                $kind === BriefingItemKind::Route => 'hidden',
                                default => '',
                            };
                        @endphp
                        <li wire:key="timeline-{{ $index }}" @class(['vms-timeline-item grid grid-cols-[1.375rem_minmax(0,1fr)] gap-x-3.5 sm:grid-cols-[4.5rem_1.75rem_minmax(0,1fr)] sm:gap-x-4.5', 'vms-timeline-item--compact' => $compact])>
                            <div @class(['vms-timeline-time whitespace-nowrap text-right max-sm:hidden', 'pt-5' => ! $compact, 'pt-2.5' => $compact])>
                                @if ($timeText[0])
                                    <b @class(['block tabular-nums', 'text-base font-extrabold' => ! $compact, 'text-[13px] font-bold text-dark-600 dark:text-dark-300' => $compact])>{{ $timeText[0] }}</b>
                                @endif
                                @if ($timeText[1])
                                    <span class="mt-px block text-xs text-dark-500 dark:text-dark-400">{{ $timeText[1] }}</span>
                                @endif
                            </div>
                            <div class="vms-timeline-spine relative flex justify-center"><span class="vms-timeline-node {{ $nodeClass }}"></span></div>
                            <div @class(['vms-timeline-body grid min-w-0 gap-3', 'py-4 pb-5.5' => ! $compact, 'py-2' => $compact])>
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
                                        <livewire:flights::airport-weather lazy :key="'briefing-weather-'.$index.'-'.$item->anchor" :icao="$item->anchor" />
                                    @endif
                                @elseif ($kind === BriefingItemKind::Route)
                                    <div class="vms-briefing-route flex flex-wrap items-center gap-x-0.5 gap-y-1" aria-label="Filed route {{ $item->title }}">
                                        @foreach (preg_split('/\s+/', trim($item->title)) ?: [] as $token)
                                            <span class="vms-flight-pill border border-gray-200 px-2.5 py-0.5 font-mono text-[11.5px] font-semibold dark:border-dark-700">{{ $token }}</span>
                                        @endforeach
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

                                @foreach ($noticesUnder[$index] ?? [] as $notice)
                                    @php
                                        $tone = match ($notice->severity->getColor()) {
                                            'danger' => 'text-red-600 dark:text-red-400',
                                            'warning' => 'text-amber-700 dark:text-amber-400',
                                            default => 'text-dark-600 dark:text-dark-300',
                                        };
                                    @endphp
                                    <div class="vms-briefing-notice flex items-start gap-2.5 overflow-hidden rounded-2xl border border-gray-200 px-3.5 py-2.5 dark:border-dark-700" data-severity="{{ $notice->severity->value }}">
                                        <x-vms::meteocon :name="$notice->icon ?? 'not-available-fill'" class="-mt-1 -mr-0.5 -ml-1 size-9 shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <p class="m-0 text-[13.5px] font-bold {{ $tone }}">{{ $notice->title }}</p>
                                            @if ($notice->detail)
                                                <p class="m-0 mt-px text-[12.5px] leading-normal text-dark-500 dark:text-dark-400">{{ $notice->detail }}</p>
                                            @endif
                                        </div>
                                        @if ($notice->at)
                                            <span class="shrink-0 text-xs tabular-nums whitespace-nowrap text-dark-500 max-sm:hidden dark:text-dark-400">{{ $clock($notice->at) }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </li>
                    @endforeach

                    @unless ($hasAlternate)
                        <li class="vms-timeline-item grid grid-cols-[1.375rem_minmax(0,1fr)] gap-x-3.5 sm:grid-cols-[4.5rem_1.75rem_minmax(0,1fr)] sm:gap-x-4.5">
                            <div class="vms-timeline-time pt-5 text-right max-sm:hidden"><span class="block text-xs text-dark-500 dark:text-dark-400">alternate</span></div>
                            <div class="vms-timeline-spine relative flex justify-center"><span class="vms-timeline-node vms-timeline-node--alt"></span></div>
                            <div class="vms-timeline-body py-4 pb-5.5"><p class="m-0 text-[13.5px] text-dark-500 dark:text-dark-400">No alternate airport on this flight.</p></div>
                        </li>
                    @endunless
                </ol>
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
                                        <x-slot-outlet name="flights.detail.actions" :context="['flight' => $flight, 'policy' => $policy]" />
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
