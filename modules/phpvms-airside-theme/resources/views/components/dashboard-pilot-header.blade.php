@props(['dashboard', 'pilot' => null])

{{--
  Dashboard pilot header (DashboardPilotHeader). Props: dashboard (the DashboardData fields as an array),
  pilot (the shell user: name, avatar, ident, callsign). No slots.
--}}
@php
    $number = fn (int|float|null $value): string => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');

    $rank = $dashboard['rank'] ?? null;
    $name = $pilot['name'] ?? $dashboard['name'] ?? 'Pilot';
    $initials = mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->filter()->map(fn (string $part): string => mb_substr($part, 0, 1))->take(2)->implode(''));

    $promotion = match (true) {
        $rank === null => 'Rank target unavailable',
        $rank['to'] === null => 'Highest rank reached',
        $rank['hoursRemaining'] === null => 'Rank target unavailable',
        default => $number($rank['hoursRemaining']).' hrs to promotion',
    };

    $status = $dashboard['state']['label'] ?? (($dashboard['onLeave'] ?? false) ? __('ui.on_leave') : 'Status unavailable');
    $landing = ($dashboard['averageLandingRate'] ?? null) === null ? '—' : str_replace('-', '−', (string) $dashboard['averageLandingRate']).' fpm';

    $metrics = [
        ['Flights', $number($dashboard['flights'] ?? null)],
        ['Hours', $dashboard['flightTimeMinutes'] ?? '—'],
        ['Transfer', $dashboard['transferTimeMinutes'] ?? '—'],
        ['Balance', $dashboard['balance']['formatted'] ?? '—'],
        ['Pilot score', $number($dashboard['pilotScore'] ?? null)],
        ['On-time', ($dashboard['onTimePercentage'] ?? null) === null ? '—' : $dashboard['onTimePercentage'].'%'],
        ['Avg landing', $landing],
    ];
@endphp
<section class="pv-dashboard-pilot overflow-hidden rounded-xl border border-line bg-panel text-ink" aria-label="Pilot summary">
    <div class="grid gap-5 p-[18px] sm:grid-cols-[minmax(0,300px)_minmax(0,1fr)] sm:items-center">
        <div class="flex min-w-0 items-center gap-3.5">
            @if (! empty($pilot['avatar']))
                <img class="size-14 shrink-0 rounded-lg object-cover" src="{{ $pilot['avatar'] }}" alt="" />
            @else
                <span class="grid size-14 shrink-0 place-items-center rounded-lg bg-accent text-lg font-bold text-panel" aria-hidden="true">{{ $initials }}</span>
            @endif
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="m-0 text-xl leading-tight font-semibold">{{ $name }}</h1>
                    <x-airside::state-badge :state-color="$dashboard['state']['color'] ?? 'gray'" :label="$status" />
                </div>
                <p class="mt-[7px] flex flex-wrap gap-[3px] font-mono text-xs text-ink-dim">
                    <span>{{ $pilot['ident'] ?? '—' }}</span>
                    <span aria-hidden="true"> · </span>
                    <span>{{ $pilot['callsign'] ?? '—' }}</span>
                    <span aria-hidden="true"> · </span>
                    <span>At {{ $dashboard['currentAirport'] ?? '—' }}</span>
                </p>
            </div>
        </div>

        <div class="grid min-w-0 gap-[7px] sm:border-l sm:border-line sm:pl-5" aria-label="Rank progress">
            <div class="flex justify-between gap-2.5 text-xs text-ink-dim">
                <span class="micro">Rank progress</span>
                <span>{{ $rank['from'] ?? '—' }} <span aria-hidden="true">→</span> {{ $rank['to'] ?? '—' }}</span>
            </div>
            <strong class="font-mono text-[13px]">{{ $number($rank['currentHours'] ?? null) }} / {{ $number($rank['targetHours'] ?? null) }} hrs</strong>
            @if (($rank['targetHours'] ?? null) !== null)
                <progress class="h-2 w-full accent-(--pv-accent)" value="{{ $rank['pct'] }}" max="100" aria-label="{{ $rank['from'] }} progress: {{ $rank['pct'] }}%">{{ $rank['pct'] }}%</progress>
            @endif
            <p class="m-0 text-[11px] text-ink-dim">{{ $promotion }}</p>
        </div>
    </div>

    <div class="overflow-hidden border-t border-line">
        <dl class="m-0 -mt-px -ml-px grid grid-cols-2 sm:grid-cols-4 min-[1200px]:grid-cols-7">
            @foreach ($metrics as [$label, $value])
                <div class="min-w-0 border-t border-l border-line p-3.5">
                    <dt class="text-[10px] tracking-[0.06em] text-ink-faint uppercase">{{ $label }}</dt>
                    <dd class="m-0 mt-[5px] text-[17px] font-semibold wrap-anywhere tnum">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
