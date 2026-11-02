{{-- See App\View\Components\Admin\BannerTabs for the API. --}}
<div class="banner-tabs" role="tablist" @if (filled($ariaLabel)) aria-label="{{ $ariaLabel }}" @endif>
    @foreach ($tabs as $tab)
        @php
            $key = $tab['key'];
            $url = $tab['url'] ?? null;
            $badge = $tab['badge'] ?? null;
        @endphp

        <{{ filled($url) ? 'a' : 'button' }}
            @if (filled($url))
                href="{{ $url }}"
            @else
                type="button"
            @endif
            class="banner-tab"
            role="tab"
            id="t-{{ $key }}"
            @if (filled($tab['panel'] ?? null)) aria-controls="{{ $tab['panel'] }}" @endif
            @if (filled($model) && blank($url))
                :aria-selected="{{ $model }} === @js($key)"
                @click="{{ $model }} = @js($key)"
            @else
                aria-selected="{{ $active === $key ? 'true' : 'false' }}"
            @endif
        >
            @if (filled($tab['icon'] ?? null))
                <x-filament::icon :icon="$tab['icon']" />
            @endif
            {{ $tab['label'] }}
            @if (filled($badge))
                <em>{{ $badge }}</em>
            @endif
        </{{ filled($url) ? 'a' : 'button' }}>
    @endforeach
</div>
