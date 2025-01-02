<div class="card">
    <div class="card-header" role="tablist">
        @lang('widgets.latestnews.news')
    </div>
    <div class="card-body p-4">
        @if ($news->count() === 0)
            <div class="text-center text-muted" style="padding: 30px;">
                @lang('widgets.latestnews.nonewsfound')
            </div>
        @endif

        @foreach ($news as $item)
            <div>
                <h4>{{ $item->subject }}</h4>
                <p class="category">{{ $item->user->name_private }}
                    - {{ show_datetime($item->created_at) }}</p>
                <span>
                    {!! $item->body !!}
                </span>
                <hr>
            </div>
        @endforeach
    </div>
</div>
