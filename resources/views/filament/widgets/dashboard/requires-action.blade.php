{{--
    "Requires action" queue (mockup index.html:608-666, classes theme.css:queue__*).
--}}
<x-filament-widgets::widget class="fi-widget">
    <x-filament::section>
        <x-slot name="heading">
            {{ __('filament.dashboard.recent_action') }} <em>{{ $count }}</em>
        </x-slot>

        <div class="queue">
            @forelse ($rows as $row)
                <a class="queue__item" href="{{ $row['url'] }}">
                    <span class="queue__flag queue__flag--{{ $row['flag'] }}">@svg($row['icon'])</span>
                    <span class="queue__text">
                        <strong>{{ $row['strong'] }}</strong>
                        <span>{{ $row['sub'] }}</span>
                    </span>
                    <span class="queue__when">{{ $row['when'] }}</span>
                </a>
            @empty
                <div class="queue__item queue__item--empty">
                    <span class="queue__text">
                        <span>{{ __('filament.dashboard.nothing_needs_attention') }}</span>
                    </span>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
