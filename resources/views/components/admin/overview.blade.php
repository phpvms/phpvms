{{--
    Overview: the carded read-only header for edit pages. See
    App\View\Components\Admin\Overview for the contract and both render paths
    (component tag on blade pages, View::make('components.admin.overview') in
    Filament schemas).

    cards: list of [icon => Phosphor, tint => null|'blue'|'teal'|'violet'|'rose'|'amber',
        label, value, note (optional)]
    editAction: the page's edit Action (pairs with
        App\Filament\Actions\EditDetailsAction's settings drawer), rendered in
        the last card's top-right corner; null hides the trigger.
--}}
@php($editAction ??= null)
<section
    @class(['overview', 'overview--3' => count($cards) === 3, 'overview--4' => count($cards) === 4])
    aria-label="{{ $ariaLabel }}"
>
    @foreach ($cards as $card)
        <div @class(['overview__cell', 'overview__cell--edit' => $loop->last && $editAction !== null])>
            <span @class(['overview__icon', 'overview__icon--' . ($card['tint'] ?? '') => filled($card['tint'] ?? null)])>
                <x-filament::icon :icon="$card['icon']" />
            </span>
            <span class="overview__label">{{ $card['label'] }}</span>
            <span class="overview__value">{{ $card['value'] }}</span>
            @if (filled($card['note'] ?? null))
                <span class="overview__note">{{ $card['note'] }}</span>
            @endif
            @if ($loop->last && $editAction !== null)
                <div class="overview__edit">{{ $editAction }}</div>
            @endif
        </div>
    @endforeach
</section>
