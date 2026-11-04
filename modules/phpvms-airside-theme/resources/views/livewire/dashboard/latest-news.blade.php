{{-- The latest news widget. Data comes from App\Features\Dashboard\Livewire\LatestNews. --}}
<div class="vms-news flex flex-col">
    @forelse ($news as $item)
        <article wire:key="news-{{ $item->id }}" class="grid gap-1 border-t border-dark-400/15 py-3 first:border-t-0 first:pt-0 last:pb-0">
            <h4 class="m-0 text-sm font-semibold text-dark-900 dark:text-dark-50">{{ $item->subject }}</h4>
            <p class="m-0 text-xs text-dark-400">
                {{ $item->user?->name_private }}@if ($item->user) · @endif{{ show_datetime($item->created_at) }}
            </p>
            <p class="m-0 text-[13px] text-dark-600 dark:text-dark-300">{{ \App\Features\Dashboard\Livewire\LatestNews::excerpt($item->body) }}</p>
        </article>
    @empty
        <p class="m-0 text-xs text-dark-500 dark:text-dark-400">{{ __('ui.no_news') }}</p>
    @endforelse
</div>
