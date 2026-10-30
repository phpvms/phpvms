<x-airside::card :title="__('widgets.latestnews.news')" class="pv-widget">
    @if ($news->count() === 0)
        <p class="py-6 text-center text-[13px] text-ink-dim">@lang('widgets.latestnews.nonewsfound')</p>
    @endif

    <div class="grid divide-y divide-line">
        @foreach ($news as $item)
            <article wire:key="news-{{ $item->id }}" class="grid gap-1 py-3 first:pt-0 last:pb-0">
                <h3 class="text-sm font-semibold text-ink">{{ $item->subject }}</h3>
                <p class="micro">{{ $item->user->name_private }} · {{ show_datetime($item->created_at) }}</p>
                <div class="pv-prose text-[13px] text-ink">{!! $item->body !!}</div>
            </article>
        @endforeach
    </div>
</x-airside::card>
