@props(['title' => null, 'description' => null, 'variant' => 'outline'])

{{--
  Card (UPageCard). Props: title, description, variant (outline|subtle: subtle uses the inset surface).
  Slots: header (replaces title/description), default (body), footer.
--}}
@php
    $surface = $variant === 'subtle' ? 'bg-panel-inset' : 'bg-panel';
@endphp
<section {{ $attributes->class(['pv-card overflow-hidden rounded-md border border-line', $surface]) }}>
    @if (isset($header) || $title || $description)
        <header class="grid gap-1 border-b border-line px-4 py-3">
            @isset($header)
                {{ $header }}
            @else
                @if ($title)
                    <h2 class="text-sm font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="text-[13px] text-ink-dim">{{ $description }}</p>
                @endif
            @endisset
        </header>
    @endif

    @if (! $slot->isEmpty())
        <div class="px-4 py-3">{{ $slot }}</div>
    @endif

    @isset($footer)
        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-line bg-panel-inset px-4 py-2 text-[13px]">
            {{ $footer }}
        </footer>
    @endisset
</section>
