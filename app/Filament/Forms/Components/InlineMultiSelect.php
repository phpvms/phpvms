<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Select;

/**
 * Dense multi-select without pills (console pattern, Preline advanced-select
 * as the visual reference): the trigger is a one-line summary — "B737, B738,
 * B739 +2" over a dotted rule — and the dropdown is a searchable checklist
 * whose rows show the label with an optional right-aligned mono meta (a
 * type code, an ICAO, …).
 *
 * Extends Select, so ->options(), ->relationship() (plain pivots only — a
 * sync drops extra pivot columns), validation and state behave exactly like
 * the stock field; only the view and its Alpine layer are ours. Options are
 * serialized to the client and searched there, so keep the option set in
 * the hundreds, not thousands.
 */
class InlineMultiSelect extends Select
{
    protected string $view = 'filament.forms.components.inline-multi-select';

    /**
     * value => meta text shown right-aligned in the option row and used as
     * the compact summary token when present.
     *
     * @var array<int|string, string>|Closure|null
     */
    protected array|Closure|null $optionMetas = null;

    /**
     * value => group header the option sorts under (TallStackUI's grouped
     * styled-select is the visual reference). Ungrouped options list first.
     *
     * @var array<int|string, string>|Closure|null
     */
    protected array|Closure|null $optionGroups = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->multiple();
        $this->preload();
    }

    /**
     * @param array<int|string, string>|Closure|null $metas
     */
    public function optionMetas(array|Closure|null $metas): static
    {
        $this->optionMetas = $metas;

        return $this;
    }

    /**
     * @return array<int|string, string>
     */
    public function getOptionMetas(): array
    {
        return (array) $this->evaluate($this->optionMetas);
    }

    /**
     * @param array<int|string, string>|Closure|null $groups
     */
    public function optionGroups(array|Closure|null $groups): static
    {
        $this->optionGroups = $groups;

        return $this;
    }

    /**
     * @return array<int|string, string>
     */
    public function getOptionGroups(): array
    {
        return (array) $this->evaluate($this->optionGroups);
    }

    /**
     * Options flattened for the client-side list, ordered by group so the
     * blade can emit a header row whenever the group changes.
     *
     * @return list<array{value: string, label: string, meta: string|null, group: string|null}>
     */
    public function getItems(): array
    {
        $metas = $this->getOptionMetas();
        $groups = $this->getOptionGroups();

        return collect($this->getOptions())
            ->map(fn ($label, $value): array => [
                'value' => (string) $value,
                'label' => (string) $label,
                'meta'  => isset($metas[$value]) ? (string) $metas[$value] : null,
                'group' => isset($groups[$value]) ? (string) $groups[$value] : null,
            ])
            ->sortBy(fn (array $item): string => $item['group'] ?? '', SORT_NATURAL)
            ->values()
            ->all();
    }
}
