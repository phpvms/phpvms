{{-- Previous and next only (simplePaginate), in airside's style. --}}
@php
    $link = 'inline-flex items-center justify-center rounded-md border border-line bg-panel px-3 py-1.5 text-[13px] text-ink no-underline hover:bg-panel-inset';
@endphp
@if ($paginator->hasPages())
    <nav class="pv-pagination flex items-center justify-center gap-2" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="{{ $link }} opacity-50" aria-disabled="true">@lang('pagination.previous')</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }}" wire:navigate>@lang('pagination.previous')</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }}" wire:navigate>@lang('pagination.next')</a>
        @else
            <span class="{{ $link }} opacity-50" aria-disabled="true">@lang('pagination.next')</span>
        @endif
    </nav>
@endif
