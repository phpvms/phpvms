@props(['title' => null, 'description' => null])

{{--
  Page header: the display title, an optional description, and controls on the right.
  Props: title (plain text), description.
  Slots: title (replaces the prop; any markup, e.g. "Find a <span>flight.</span>", where the span is the thin
  serif accent word), links (right side: counts, badges, buttons), default (extra content under the description).
  Classes merge onto the root, so a page adds its own hook: <x-vms::page.header class="vms-flights-header">.
--}}
<header {{ $attributes->class('vms-page-header flex flex-wrap items-end justify-between gap-6 px-1.5 pt-5') }}>
    <div class="grid min-w-0 gap-3">
        @if (filled((string) $title))
            <h1 class="vms-page-title m-0 text-4xl leading-none font-extrabold tracking-tight sm:text-5xl">{{ $title }}</h1>
        @endif
        @if ($description)
            <p class="vms-page-description m-0 max-w-2xl text-sm">{{ $description }}</p>
        @endif
        {{ $slot }}
    </div>

    @isset($links)
        <div class="vms-page-links flex flex-wrap items-center gap-2 text-[13px]">{{ $links }}</div>
    @endisset
</header>
