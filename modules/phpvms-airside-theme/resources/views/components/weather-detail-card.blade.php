@props([
    'label' => '',
    'icao' => null,
    'state' => 'loading',
    'metar' => null,
    'observedAt' => null,
    'emptyLabel' => null,
    'description' => null,
    'observedClock' => null,
])

{{--
  One airport's weather, the ACARS way: a Meteocons icon, one plain sentence, the raw METAR in small mono (ellipsed),
  the flight category and the observation time.
  Props: label (shows a header with the ICAO when set), icao, state (loading|loaded|stale|missing|missing-station|error),
  metar, observedAt (the provider's text, shown when there is no clock), emptyLabel (text when the airport has no station),
  description (a MetarDescriptionData: icon, sentence, category), observedClock ("Today 23:35Z").
  Slot `retry`: the control shown with the provider error.
--}}
@php
    $row = 'flex items-start gap-2.5 px-3.5 py-2.5';
    $note = 'm-0 text-[13px] text-dark-500 dark:text-dark-400';
@endphp
<section {{ $attributes->class('vms-weather-detail-card min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white text-dark-900 dark:border-dark-700 dark:bg-dark-800 dark:text-dark-50') }} aria-label="{{ $label !== '' ? $label : ($icao ?? 'Airport') }} weather">
    @if ($label !== '')
        <header class="vms-weather-header flex items-center justify-between gap-3 px-3.5 pt-3">
            <p class="m-0 text-xs font-semibold">{{ $label }}</p>
            <strong class="font-mono text-xs">{{ $icao ?? '—' }}</strong>
        </header>
    @endif

    @if ($state === 'missing-station')
        <p class="{{ $note }} px-3.5 py-3">{{ $emptyLabel ?? 'Weather station unavailable' }}</p>
    @elseif ($state === 'loading')
        <div class="{{ $row }} items-center" role="status" aria-live="polite">
            <x-vms::meteocon name="not-available-fill" class="vms-weather-icon size-10 shrink-0 opacity-50" />
            <p class="{{ $note }}">Loading current report…</p>
        </div>
    @elseif ($state === 'loaded' || $state === 'stale')
        <div class="vms-weather-report {{ $row }}">
            <x-vms::meteocon :name="$description?->icon ?? 'not-available-fill'" class="vms-weather-icon -mt-1 -mr-0.5 -ml-1 size-10 shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="vms-weather-sentence m-0 text-sm font-semibold text-pretty">{{ $description?->sentence ?? 'Current report' }}</p>
                <p class="vms-weather-metar m-0 mt-0.5 truncate font-mono text-[11.5px] leading-normal text-dark-500 dark:text-dark-400" title="{{ $metar }}">{{ $metar }}</p>
            </div>
            <div class="grid shrink-0 justify-items-end gap-1 text-xs tabular-nums whitespace-nowrap text-dark-500 max-sm:hidden dark:text-dark-400">
                @if ($state === 'stale')
                    <span class="vms-weather-stale rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300" role="status">Stale</span>
                @elseif ($description?->category)
                    <span class="vms-weather-category rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-extrabold text-dark-700 dark:bg-dark-900 dark:text-dark-200">{{ $description->category }}</span>
                @endif
                <span>{{ $observedClock ?? $observedAt ?? 'Time unavailable' }}</span>
            </div>
        </div>
    @elseif ($state === 'missing')
        <p class="{{ $note }} px-3.5 py-3" role="status">No current report for {{ $icao }}.</p>
    @else
        <div class="{{ $row }} items-center justify-between" role="alert">
            <p class="{{ $note }}">Weather provider error for {{ $icao }}.</p>
            {{ $retry ?? '' }}
        </div>
    @endif
</section>
