@props(['headline' => null, 'title' => null, 'description' => null])

{{--
  Page header (UPageHeader). Props: headline (micro label above), title, description.
  Slots: links (right-side controls), default (extra content under the description).
--}}
<header {{ $attributes->class('flex flex-wrap items-end justify-between gap-4 border-b border-line pb-4') }}>
    <div class="grid min-w-0 gap-1">
        @if ($headline)
            <p class="micro">{{ $headline }}</p>
        @endif
        @if ($title)
            <h1 class="text-xl font-semibold text-ink">{{ $title }}</h1>
        @endif
        @if ($description)
            <p class="text-[13px] text-ink-dim">{{ $description }}</p>
        @endif
        {{ $slot }}
    </div>

    @isset($links)
        <div class="flex flex-wrap items-center gap-2">{{ $links }}</div>
    @endisset
</header>
