<?php

declare(strict_types=1);

namespace App\Services\Awards\Constraints\Operators;

use App\Models\User;
use App\Services\Awards\Constraints\TourConstraint;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * "The user has at least / at most / exactly N completed tours matching these
 * rules."
 *
 * Laravel's fifth argument to `has()` is a callback over the relation query,
 * which is what puts every inner condition inside the one subquery. Inverting
 * the operator covers "fewer than", "more than" and "not exactly"; the
 * "none at all" case is "at least 1", inverted.
 */
class TourCountOperator extends TourOperator
{
    public const string COUNT_NAME = 'count';

    #[Override]
    public function getName(): string
    {
        return 'count';
    }

    #[Override]
    public function getLabel(): string
    {
        return $this->isInverse() ? 'count is not' : 'count is';
    }

    #[Override]
    public function getSummary(): string
    {
        $count = $this->getNumericSetting(static::COUNT_NAME);

        return trim(sprintf(
            'Tours count is %s%s %s',
            $this->isInverse() ? 'not ' : '',
            strtolower($this->getComparisonLabel() ?? ''),
            $count === null ? '' : (string) (int) $count,
        ));
    }

    /**
     * @return array<Component | Action | ActionGroup>
     */
    #[Override]
    public function getFormSchema(): array
    {
        return [
            $this->getInnerRulesComponent(),
            $this->getComparisonComponent(),
            TextInput::make(static::COUNT_NAME)
                ->label('Number of tours')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(0),
        ];
    }

    /**
     * @param  Builder<User> $query
     * @return Builder<User>
     */
    #[Override]
    public function applyToBaseQuery(Builder $query): Builder
    {
        $count = $this->getNumericSetting(static::COUNT_NAME);
        $comparison = $this->getComparison();

        // Security: settings arrive from a stored tree and can be tampered
        // with. A non-numeric count or an unoffered comparison applies
        // nothing rather than reaching SQL.
        if ($count === null || $comparison === null) {
            return $query;
        }

        return $query->has(
            TourConstraint::RELATIONSHIP,
            $comparison,
            (int) $count,
            'and',
            $this->applyInnerRules(...),
        );
    }
}
