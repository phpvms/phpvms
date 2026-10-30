{{-- The activity widget. State comes from App\Features\Dashboard\Livewire\ActivityFeed. --}}
@php
    $dots = [
        'pirep' => ['var(--pv-accent)', 'paper-airplane'],
        'pilot_joined' => ['var(--pv-green)', 'user-plus'],
        'flight_added' => ['var(--pv-cyan)', 'map-pin'],
        'award' => ['var(--pv-amber)', 'trophy'],
    ];
    $flyingNow = $feed?->flyingNow ?? 0;
@endphp
<div class="pv-activity flex flex-col gap-3.5">
    <header class="flex items-baseline gap-2">
        <span class="relative size-2 self-center" aria-hidden="true">
            <span class="pv-activity-pulse absolute inset-0 rounded-full bg-(--pv-green)"></span>
        </span>
        <span class="text-lg font-bold tracking-tight text-ink tnum">{{ $flyingNow }}</span>
        <span class="text-xs text-ink-dim">{{ trans_choice('ui.flying_now', $flyingNow) }}</span>
    </header>

    @if ($state === 'loading')
        <p class="m-0 text-xs text-ink-dim">{{ __('ui.loading_activity') }}</p>
    @elseif ($state === 'error')
        <p class="m-0 text-xs text-red-600" role="alert">{{ __('ui.activity_error') }}</p>
    @elseif ($state === 'empty')
        <p class="m-0 text-xs text-ink-dim">{{ __('ui.no_activity') }}</p>
    @else
        <ol class="m-0 flex list-none flex-col p-0">
            @foreach ($feed->events as $event)
                @php
                    [$color, $icon] = $dots[$event->type] ?? ['var(--pv-ink-faint)', 'bolt'];
                @endphp
                <li wire:key="activity-{{ $event->id }}" class="pv-activity-row grid grid-cols-[24px_1fr_auto] gap-2.5 pb-3.5 last:pb-0">
                    <span class="pv-activity-rail relative flex justify-center">
                        <span class="pv-activity-node relative z-10 flex size-[22px] items-center justify-center rounded-full border" style="color: {{ $color }}">
                            <x-airside::icon :name="$icon" class="size-[13px]" />
                        </span>
                    </span>
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <p class="m-0 text-[13px] leading-snug text-ink">{{ $event->title }}</p>
                        @if ($event->subtitle)
                            <p class="m-0 font-mono text-[11px] text-ink-dim tnum">{{ $event->subtitle }}</p>
                        @endif
                    </div>
                    <time class="pt-[3px] text-[11px] whitespace-nowrap text-ink-faint" datetime="{{ $event->timestamp }}">{{ \App\Features\Dashboard\Livewire\ActivityFeed::timeAgo($event->timestamp) }}</time>
                </li>
            @endforeach
        </ol>
    @endif
</div>
