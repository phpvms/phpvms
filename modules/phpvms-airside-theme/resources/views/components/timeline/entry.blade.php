@props(['offset' => null, 'time' => null, 'caption' => null, 'node' => null, 'compact' => false])

{{--
  One timeline entry: the items at one offset, stacked in the body. Props: offset (minutes from 0; shown as
  +H:MM when time is not set), time (overrides the offset label), caption (a word under the time), node
  (fix|phase|alt|hidden, else the default dot), compact (the tighter row of fixes and phases).
  Slot: default = the stacked items.
--}}
@php
    $time ??= $offset === null ? null : \Modules\PhpvmsAirsideTheme\Support\TimelineStack::label((int) $offset);
@endphp
<li {{ $attributes->class(['vms-timeline-item grid grid-cols-[1.375rem_minmax(0,1fr)] gap-x-3.5 sm:grid-cols-[4.5rem_1.75rem_minmax(0,1fr)] sm:gap-x-4.5', 'vms-timeline-item--compact' => $compact]) }}>
    <div @class(['vms-timeline-time whitespace-nowrap text-right max-sm:hidden', 'pt-5' => ! $compact, 'pt-2.5' => $compact])>
        @if ($time)
            <b @class(['block tabular-nums', 'text-base font-extrabold' => ! $compact, 'text-[13px] font-bold text-dark-600 dark:text-dark-300' => $compact])>{{ $time }}</b>
        @endif
        @if ($caption)
            <span class="mt-px block text-xs text-dark-500 dark:text-dark-400">{{ $caption }}</span>
        @endif
    </div>
    <div class="vms-timeline-spine relative flex justify-center">
        <span @class(['vms-timeline-node', 'vms-timeline-node--'.$node => in_array($node, ['fix', 'phase', 'alt'], true), 'hidden' => $node === 'hidden'])></span>
    </div>
    <div @class(['vms-timeline-body grid min-w-0 gap-3', 'py-4 pb-5.5' => ! $compact, 'py-2' => $compact])>
        {{ $slot }}
    </div>
</li>
