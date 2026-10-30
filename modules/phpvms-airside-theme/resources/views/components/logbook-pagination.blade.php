@props(['currentPage', 'lastPage'])

{{--
  Centred page links (UPagination): previous, the first and last page, the pages around the current one
  with gaps shown as an ellipsis, next. Props: currentPage, lastPage. Renders nothing for a single page.
--}}
@php
    $pages = collect([1, $lastPage])
        ->merge(range(max(1, $currentPage - 2), min($lastPage, $currentPage + 2)))
        ->unique()
        ->sort()
        ->values();
    $url = fn (int $page): string => route('frontend.pireps.index', ['page' => $page]);
    $link = 'inline-flex size-8 items-center justify-center rounded-md border border-line bg-panel text-[13px] text-ink hover:bg-panel-inset';
@endphp
@if ($lastPage > 1)
    <nav {{ $attributes->class('pv-logbook-pager flex flex-wrap items-center justify-center gap-1') }} aria-label="Pagination">
        @if ($currentPage > 1)
            <a href="{{ $url($currentPage - 1) }}" wire:navigate class="{{ $link }}" aria-label="Previous page" rel="prev"><span aria-hidden="true">‹</span></a>
        @else
            <span class="{{ $link }} opacity-50" aria-disabled="true"><span aria-hidden="true">‹</span></span>
        @endif

        @foreach ($pages as $page)
            @if (! $loop->first && $page - $pages[$loop->index - 1] > 1)
                <span class="inline-flex size-8 items-center justify-center text-ink-faint" aria-hidden="true">…</span>
            @endif

            @if ($page === $currentPage)
                <span class="{{ $link }} border-accent bg-accent-soft font-semibold text-accent" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $url($page) }}" wire:navigate class="{{ $link }}" aria-label="Page {{ $page }}">{{ $page }}</a>
            @endif
        @endforeach

        @if ($currentPage < $lastPage)
            <a href="{{ $url($currentPage + 1) }}" wire:navigate class="{{ $link }}" aria-label="Next page" rel="next"><span aria-hidden="true">›</span></a>
        @else
            <span class="{{ $link }} opacity-50" aria-disabled="true"><span aria-hidden="true">›</span></span>
        @endif
    </nav>
@endif
