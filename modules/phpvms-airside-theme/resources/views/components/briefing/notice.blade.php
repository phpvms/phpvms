@props(['item', 'time' => null])

{{--
  A notice on the briefing timeline. Props: item (a Notice briefing item), time (its clock label, or null).
--}}
@php
    $tone = match ($item->severity->getColor()) {
        'danger' => 'text-red-600 dark:text-red-400',
        'warning' => 'text-amber-700 dark:text-amber-400',
        default => 'text-dark-600 dark:text-dark-300',
    };
@endphp
<div {{ $attributes->class('vms-briefing-notice flex items-start gap-2.5 overflow-hidden rounded-2xl border border-gray-200 px-3.5 py-2.5 dark:border-dark-700') }} data-severity="{{ $item->severity->value }}">
    <x-vms::meteocon :name="$item->icon ?? 'not-available-fill'" class="-mt-1 -mr-0.5 -ml-1 size-9 shrink-0" />
    <div class="min-w-0 flex-1">
        <p class="m-0 text-[13.5px] font-bold {{ $tone }}">{{ $item->title }}</p>
        @if ($item->detail)
            <p class="m-0 mt-px text-[12.5px] leading-normal text-dark-500 dark:text-dark-400">{{ $item->detail }}</p>
        @endif
    </div>
    @if ($time)
        <span class="shrink-0 text-xs tabular-nums whitespace-nowrap text-dark-500 max-sm:hidden dark:text-dark-400">{{ $time }}</span>
    @endif
</div>
