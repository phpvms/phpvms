@inject('shell', 'App\Features\Shell\ShellData')

{{--
  The pilot dashboard. Data: the DashboardData fields from DashboardController and the
  nav-display route; the pilot identity comes from the shell.
--}}
@php
    $pilot = $shell->user();
    $number = fn (int|float|null $value): string => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
    $pilotName = $pilot['name'] ?? $name ?? 'Pilot';

    $stateColor = \Modules\PhpvmsAirsideTheme\Support\StateColor::tallstack($state['color'] ?? null);
    $stateLabel = $state['label'] ?? ($onLeave ? __('ui.on_leave') : 'Status unavailable');

    // Static class names so Tailwind finds them: the badge dot and label take the state colour.
    $badgeTone = match ($stateColor) {
        'green' => 'text-green-700 dark:text-green-400',
        'amber' => 'text-amber-700 dark:text-amber-400',
        'red' => 'text-red-700 dark:text-red-400',
        'sky' => 'text-sky-700 dark:text-sky-400',
        default => 'text-slate-600 dark:text-slate-300',
    };

    $promotion = match (true) {
        $rank === null => 'Rank target unavailable',
        $rank['to'] === null => 'Highest rank reached',
        $rank['hoursRemaining'] === null => 'Rank target unavailable',
        default => $number($rank['hoursRemaining']).' hrs to promotion',
    };

    // The pilot's own timezone when they set one, else the app's.
    $timezone = \Illuminate\Support\Facades\Auth::user()?->timezone;
    $hour = (int) now(in_array($timezone, \DateTimeZone::listIdentifiers(), true) ? $timezone : config('app.timezone'))->format('G');
    $greeting = match (true) {
        $hour < 12 => 'Good morning,',
        $hour < 18 => 'Good afternoon,',
        default => 'Good evening,',
    };

    // "312h 24m" becomes a large number with a small muted unit; text before the first number ("$") stays large.
    $metricParts = fn (string $value): array => collect(preg_split('/([−-]?[\d,.]+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY))
        ->map(fn (string $part, int $index): array => ['text' => trim($part), 'unit' => $index > 0 && ! preg_match('/^[−-]?[\d,.]+$/u', $part)])
        ->filter(fn (array $part): bool => $part['text'] !== '')
        ->all();

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
    <div class="grid gap-3.5">
        <section class="vms-pilot-summary relative grid items-end gap-8 overflow-hidden rounded-(--vms-radius-xl) p-6 sm:p-10 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-10" aria-label="Pilot summary">
            <x-vms::sky class="absolute inset-0 size-full" />

            <div class="relative min-w-0">
                <div class="vms-pilot-eyebrow flex flex-wrap items-center gap-2.5 text-xs font-bold tracking-[0.14em] text-(--vms-hero-ink-3) uppercase">
                    {{ __('common.dashboard') }}
                    <span class="vms-pilot-badge inline-flex items-center gap-1.5 rounded-full bg-(--vms-surface)/75 px-2.5 py-1 text-[11.5px] font-semibold tracking-normal normal-case {{ $badgeTone }}"><span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $stateLabel }}</span>
                </div>
                <h1 class="vms-pilot-greeting mt-3.5 text-[clamp(36px,4.6vw,60px)] leading-none font-extrabold tracking-[-0.045em] text-(--vms-hero-ink)">{{ $greeting }}<br><span>{{ $pilotName }}.</span></h1>
                <p class="vms-pilot-chips mt-5 flex flex-wrap gap-2 text-[13px] text-(--vms-hero-ink-3)">
                    @if (filled($pilot['ident'] ?? null))
                        <span class="rounded-full bg-(--vms-surface)/70 px-3 py-1.5 font-mono font-semibold text-(--vms-hero-ink)">{{ $pilot['ident'] }}</span>
                    @endif
                    @if (filled($pilot['callsign'] ?? null))
                        <span class="rounded-full bg-(--vms-surface)/70 px-3 py-1.5 font-mono">{{ $pilot['callsign'] }}</span>
                    @endif
                    @if (filled($currentAirport ?? null))
                        <span class="rounded-full bg-(--vms-surface)/70 px-3 py-1.5">At <b class="font-mono font-semibold text-(--vms-hero-ink)">{{ $currentAirport }}</b></span>
                    @endif
                </p>
            </div>

            <div class="vms-rank relative grid min-w-0 gap-2.5 rounded-(--vms-radius-lg) bg-(--vms-surface)/80 p-6 text-(--vms-hero-ink) backdrop-blur-md" aria-label="Rank progress">
                <div class="flex flex-wrap justify-between gap-x-2.5 gap-y-0.5 text-xs text-(--vms-ink-3)">
                    <span class="font-bold whitespace-nowrap tracking-[0.12em] text-(--vms-hero-ink-3) uppercase">Rank progress</span>
                    <span>{{ $rank['from'] ?? '—' }} <span aria-hidden="true">→</span> {{ $rank['to'] ?? '—' }}</span>
                </div>
                <strong class="font-mono text-[26px] font-extrabold tracking-tight">{{ $number($rank['currentHours'] ?? null) }} <small class="text-sm font-medium tracking-normal text-(--vms-ink-3)">/ {{ $number($rank['targetHours'] ?? null) }} hrs</small></strong>
                @if (($rank['targetHours'] ?? null) !== null)
                    {{-- The native element: TallStackUI's bar renders role="progressbar" with no accessible name. --}}
                    <progress class="h-2 w-full appearance-none overflow-hidden rounded-full bg-[#e6ecf3] accent-primary-600 dark:bg-dark-700 [&::-moz-progress-bar]:bg-primary-600 [&::-webkit-progress-bar]:bg-[#e6ecf3] dark:[&::-webkit-progress-bar]:bg-dark-700 [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-primary-600" value="{{ $rank['pct'] }}" max="100" aria-label="{{ $rank['from'] }} progress: {{ $rank['pct'] }}%">{{ $rank['pct'] }}%</progress>
                @endif
                <p class="flex justify-between gap-2.5 text-[13px] text-(--vms-hero-ink-3)">
                    @if (($rank['targetHours'] ?? null) !== null)
                        <span>{{ $rank['pct'] }}%</span>
                    @endif
                    <span class="ml-auto">{{ $promotion }}</span>
                </p>
            </div>
        </section>

        <dl class="vms-metrics grid grid-cols-2 gap-3.5 sm:grid-cols-4 min-[75rem]:grid-cols-7" aria-label="Pilot statistics">
            @foreach ($metrics as $label => $value)
                <div class="vms-metric min-w-0 rounded-(--vms-radius-lg) bg-(--vms-surface) px-5 pt-4.5 pb-5">
                    <dt class="text-xs font-semibold text-(--vms-ink-3)">{{ $label }}</dt>
                    <dd class="m-0 mt-2 text-2xl font-bold tracking-tight tabular-nums wrap-anywhere">
                        @foreach ($metricParts($value) as $part)@if ($part['unit'])<small class="mr-1.5 ml-0.5 text-[13px] font-medium tracking-normal text-(--vms-ink-3)">{{ $part['text'] }}</small>@else{{ $part['text'] }}@endif@endforeach
                    </dd>
                </div>
            @endforeach
        </dl>

        <livewire:dashboard::dashboard-board :route="$route" />
    </div>
</x-vms::layouts.app>
