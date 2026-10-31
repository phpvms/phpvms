@inject('shell', 'App\Features\Shell\ShellData')

{{--
  The pilot dashboard. Data: the DashboardData fields from DashboardController and the
  nav-display route; the pilot identity comes from the shell.
--}}
@php
    $pilot = $shell->user();
    $number = fn (int|float|null $value): string => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
    $pilotName = $pilot['name'] ?? $name ?? 'Pilot';
    $initials = mb_strtoupper(collect(preg_split('/\s+/', trim($pilotName)))->filter()->map(fn (string $part): string => mb_substr($part, 0, 1))->take(2)->implode(''));

    $stateColor = match ($state['color'] ?? null) {
        'success' => 'green',
        'warning' => 'amber',
        'danger' => 'red',
        'info' => 'sky',
        default => 'gray',
    };
    $stateLabel = $state['label'] ?? ($onLeave ? __('ui.on_leave') : 'Status unavailable');

    $promotion = match (true) {
        $rank === null => 'Rank target unavailable',
        $rank['to'] === null => 'Highest rank reached',
        $rank['hoursRemaining'] === null => 'Rank target unavailable',
        default => $number($rank['hoursRemaining']).' hrs to promotion',
    };

    $metrics = [
        'Flights'     => $number($flights),
        'Hours'       => $flightTimeMinutes ?? '—',
        'Transfer'    => $transferTimeMinutes ?? '—',
        'Balance'     => $balance['formatted'] ?? '—',
        'Pilot score' => $number($pilotScore),
        'On-time'     => $onTimePercentage === null ? '—' : $onTimePercentage.'%',
        'Avg landing' => $averageLandingRate === null ? '—' : str_replace('-', '−', (string) $averageLandingRate).' fpm',
    ];
@endphp

<x-vms::layouts.app :title="__('common.dashboard')">
    <div class="grid gap-6">
        <section class="grid gap-4" aria-label="Pilot summary">
            <x-ts-card>
                <div class="grid gap-5 sm:grid-cols-[minmax(0,300px)_minmax(0,1fr)] sm:items-center">
                    <div class="flex min-w-0 items-center gap-3.5">
                        <x-ts-avatar :image="$pilot['avatar'] ?? null" :text="$initials" lg />
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="m-0 text-xl leading-tight font-semibold">{{ $pilotName }}</h1>
                                <x-ts-badge :text="$stateLabel" :color="$stateColor" light round />
                            </div>
                            <p class="mt-1.5 flex flex-wrap gap-1 font-mono text-xs text-dark-500 dark:text-dark-400">
                                <span>{{ $pilot['ident'] ?? '—' }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ $pilot['callsign'] ?? '—' }}</span>
                                <span aria-hidden="true">·</span>
                                <span>At {{ $currentAirport ?? '—' }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="grid min-w-0 gap-1.5 sm:border-l sm:border-gray-200 sm:pl-5 dark:sm:border-dark-700" aria-label="Rank progress">
                        <div class="flex justify-between gap-2.5 text-xs text-dark-500 dark:text-dark-400">
                            <span class="font-medium tracking-wide uppercase">Rank progress</span>
                            <span>{{ $rank['from'] ?? '—' }} <span aria-hidden="true">→</span> {{ $rank['to'] ?? '—' }}</span>
                        </div>
                        <strong class="font-mono text-sm">{{ $number($rank['currentHours'] ?? null) }} / {{ $number($rank['targetHours'] ?? null) }} hrs</strong>
                        @if (($rank['targetHours'] ?? null) !== null)
                            {{-- The native element: TallStackUI's bar renders role="progressbar" with no accessible name. --}}
                            <progress class="h-2 w-full overflow-hidden rounded-full accent-primary-600 dark:accent-primary-400" value="{{ $rank['pct'] }}" max="100" aria-label="{{ $rank['from'] }} progress: {{ $rank['pct'] }}%">{{ $rank['pct'] }}%</progress>
                        @endif
                        <p class="m-0 text-xs text-dark-500 dark:text-dark-400">{{ $promotion }}</p>
                    </div>
                </div>
            </x-ts-card>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 min-[1200px]:grid-cols-7">
                @foreach ($metrics as $label => $value)
                    <x-ts-stats :title="$label">
                        <span class="text-xl font-semibold tabular-nums wrap-anywhere">{{ $value }}</span>
                    </x-ts-stats>
                @endforeach
            </div>
        </section>

        <livewire:dashboard::dashboard-board :route="$route" />
    </div>
</x-vms::layouts.app>
