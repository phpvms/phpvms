{{--
    Summary strip: the carded read-only header for edit pages whose real
    content is the record's tables (identity → workspace → tucked-away
    settings; same .strip pattern as the pirep detail header and dashboard
    stats strip).

    Rendered from a page's content() via
    View::make('filament.shared.summary-strip')->viewData([...]) with:
      - cards: list of [icon => TablerIcon, tint => null|'blue'|'teal'|'violet'|'rose'|'amber',
        label, value, note (optional)]
      - hasEditAction: render the page's editAction() trigger in the last
        card's top-right corner (pairs with App\Filament\Actions\EditDetailsAction)
      - ariaLabel: section label for assistive tech
--}}
<section
    @class(['strip', 'strip--4' => count($cards) === 4])
    aria-label="{{ $ariaLabel }}"
>
    @foreach ($cards as $card)
        <div @class(['strip__cell', 'strip__cell--edit' => $loop->last && ($hasEditAction ?? false)])>
            <span @class(['strip__icon', 'strip__icon--' . ($card['tint'] ?? '') => filled($card['tint'] ?? null)])>
                <x-filament::icon :icon="$card['icon']" />
            </span>
            <span class="strip__label">{{ $card['label'] }}</span>
            <span class="strip__value">{{ $card['value'] }}</span>
            @if (filled($card['note'] ?? null))
                <span class="strip__note">{{ $card['note'] }}</span>
            @endif
            @if ($loop->last && ($hasEditAction ?? false))
                <div class="strip__edit">{{ $this->editAction }}</div>
            @endif
        </div>
    @endforeach
</section>
