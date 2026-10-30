@props(['icon' => null, 'title' => null, 'description' => null])

{{--
  Empty state (UEmpty). Props: icon (tabler/heroicon name, optional), title, description.
  Slots: actions (buttons), default (extra content).
--}}
<div {{ $attributes->class('pv-empty grid justify-items-center gap-2 rounded-md border border-dashed border-line-strong px-6 py-10 text-center') }}>
    @if ($icon)
        <x-airside::icon :name="$icon" class="size-8 text-ink-faint" />
    @endif
    @if ($title)
        <p class="text-sm font-semibold text-ink">{{ $title }}</p>
    @endif
    @if ($description)
        <p class="max-w-md text-[13px] text-ink-dim">{{ $description }}</p>
    @endif
    {{ $slot }}
    @isset($actions)
        <div class="mt-2 flex flex-wrap justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
