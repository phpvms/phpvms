{{-- Laravel's paginator links in airside's style: previous, numbered pages with gaps, next. --}}
@php
    $link = 'inline-flex min-w-8 items-center justify-center rounded-md border border-line bg-panel px-2 py-1.5 text-[13px] text-ink no-underline hover:bg-panel-inset';
@endphp
@if ($paginator->hasPages())
    <nav class="pv-pagination flex flex-wrap items-center justify-center gap-1" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="{{ $link }} opacity-50" aria-disabled="true" aria-label="@lang('pagination.previous')"><span aria-hidden="true">‹</span></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}" aria-label="@lang('pagination.previous')" wire:navigate><span aria-hidden="true">‹</span></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="inline-flex min-w-8 items-center justify-center text-ink-faint" aria-hidden="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="{{ $link }} border-accent bg-accent-soft font-semibold text-accent" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="{{ $link }}" aria-label="Page {{ $page }}" wire:navigate>{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}" aria-label="@lang('pagination.next')" wire:navigate><span aria-hidden="true">›</span></a>
        @else
            <span class="{{ $link }} opacity-50" aria-disabled="true" aria-label="@lang('pagination.next')"><span aria-hidden="true">›</span></span>
        @endif
    </nav>
@endif
