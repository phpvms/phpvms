<x-filament-widgets::widget class="fi-widget dashboard-stat-widget">
    <section class="dashboard-stat-card" aria-label="{{ $label }}">
        <span class="overview__icon overview__icon--{{ $accent }}">@svg($icon)</span>
        <span class="overview__label">{{ $label }}</span>
        <span class="overview__value">
            {{ $value }}
            @if (filled($suffix))
                <small>{{ $suffix }}</small>
            @endif
        </span>
        @if (filled($note))
            <span class="overview__note">
                @if (filled($noteIcon))
                    @svg($noteIcon)
                @endif
                {{ $note }}
            </span>
        @endif
    </section>
</x-filament-widgets::widget>
