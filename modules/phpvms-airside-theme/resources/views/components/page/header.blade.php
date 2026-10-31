@props(['headline' => null, 'title' => null, 'description' => null])

{{--
  Page header. Props: headline (small label above), title, description.
  Slots: links (right-side controls), default (extra content under the description).
--}}
<header {{ $attributes->class('flex flex-wrap items-end justify-between gap-4 border-b border-gray-200 pb-4 dark:border-dark-700') }}>
    <div class="grid min-w-0 gap-1">
        @if ($headline)
            <p class="text-xs font-medium tracking-wide text-dark-500 uppercase dark:text-dark-400">{{ $headline }}</p>
        @endif
        @if ($title)
            <h1 class="text-xl font-semibold">{{ $title }}</h1>
        @endif
        @if ($description)
            <p class="text-sm text-dark-500 dark:text-dark-400">{{ $description }}</p>
        @endif
        {{ $slot }}
    </div>

    @isset($links)
        <div class="flex flex-wrap items-center gap-2">{{ $links }}</div>
    @endisset
</header>
