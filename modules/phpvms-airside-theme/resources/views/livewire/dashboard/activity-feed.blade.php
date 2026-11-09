{{-- The activity widget. State comes from App\Features\Dashboard\Livewire\ActivityFeed. --}}
@php
    $markers = [
        'pirep' => ['primary', 'phosphor.airplane-tilt.light'],
        'pilot_joined' => ['green', 'phosphor.user-plus.light'],
        'flight_added' => ['cyan', 'phosphor.map-pin.light'],
        'award' => ['amber', 'phosphor.trophy.light'],
    ];
    $flyingNow = $feed?->flyingNow ?? 0;
@endphp
<div class="flex flex-col gap-3.5">
    <header class="vms-flying flex items-center gap-2">
        {{-- Live indicator (Tailwind's ping pattern), not a loading state. --}}
        <span class="relative flex size-2" aria-hidden="true">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-75"></span>
            <span class="relative inline-flex size-2 rounded-full bg-green-500"></span>
        </span>
        <span class="text-[26px] leading-none font-extrabold tracking-tight tabular-nums">{{ $flyingNow }}</span>
        <span class="text-[13px] text-dark-400">{{ trans_choice('ui.flying_now', $flyingNow) }}</span>
    </header>

    @if ($state === 'loading')
        <p class="m-0 text-xs text-dark-500 dark:text-dark-400">{{ __('ui.loading_activity') }}</p>
    @elseif ($state === 'error')
        <p class="m-0 text-xs text-red-600" role="alert">{{ __('ui.activity_error') }}</p>
    @elseif ($state === 'empty')
        <p class="m-0 text-xs text-dark-500 dark:text-dark-400">{{ __('ui.no_activity') }}</p>
    @else
        <x-ts-timeline compact style="light">
            @foreach ($feed->events as $event)
                @php([$color, $icon] = $markers[$event->type] ?? ['gray', 'phosphor.lightning.light'])
                <x-ts-timeline.items
                    wire:key="activity-{{ $event->id }}"
                    :title="$event->title"
                    :description="$event->subtitle"
                    :date="\App\Features\Dashboard\Livewire\ActivityFeed::timeAgo($event->timestamp)"
                    :icon="$icon"
                    :color="$color"
                />
            @endforeach
        </x-ts-timeline>
    @endif
</div>
