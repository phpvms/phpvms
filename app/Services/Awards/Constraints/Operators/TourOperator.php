<?php

declare(strict_types=1);

namespace App\Services\Awards\Constraints\Operators;

use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Services\Awards\CriteriaCompiler;
use App\Services\Awards\TourConstraints;
use Filament\Forms\Components\Select;
use Filament\QueryBuilder\Constraints\Operators\Operator;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared machinery for the tour constraint's operators: the nested rule
 * builder and the comparison vocabulary, plus the scope the server forces
 * onto every subquery.
 */
abstract class TourOperator extends Operator
{
    /** Settings key holding the nested rule tree describing one tour run. */
    public const string INNER_RULES_NAME = 'tourRules';

    public const string COMPARISON_NAME = 'comparison';

    /**
     * The comparisons a rule may name, as `[direct, inverse]` SQL operators.
     * A submitted comparison outside this map applies nothing, so no part of
     * the operator string ever comes from the payload.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const array COMPARISONS = [
        'atLeast' => ['>=', '<'],
        'atMost'  => ['<=', '>'],
        'exactly' => ['=', '!='],
    ];

    /** @var array<string, string> */
    protected const array COMPARISON_LABELS = [
        'atLeast' => 'At least',
        'atMost'  => 'At most',
        'exactly' => 'Exactly',
    ];

    /**
     * Apply the forced scope and the rule's own inner tree to a subquery over
     * `user_tours`.
     *
     * `status = Completed` is ANDed at the top level of the subquery, so an
     * inner tree -- whose own top level the vendor wraps in a nested group
     * when it contains an OR block -- can only narrow the set further, never
     * widen it. The user correlation is supplied by the caller via `has()`.
     *
     * @param  Builder<UserTour> $query
     * @return Builder<UserTour>
     */
    protected function applyInnerRules(Builder $query): Builder
    {
        $query->where($query->getModel()->qualifyColumn('status'), TourStatus::Completed->value);

        $tree = $this->getSettings()[static::INNER_RULES_NAME] ?? [];

        if (!is_array($tree) || $tree === []) {
            return $query;
        }

        return app(CriteriaCompiler::class)->compile($query, $tree, TourConstraints::make(), UserTour::class);
    }

    /**
     * The SQL comparison operator for this rule, or `null` when the submitted
     * setting names one we do not offer.
     */
    protected function getComparison(): ?string
    {
        $comparison = $this->getStringSetting(static::COMPARISON_NAME);

        if ($comparison === null || !array_key_exists($comparison, static::COMPARISONS)) {
            return null;
        }

        return static::COMPARISONS[$comparison][$this->isInverse() ? 1 : 0];
    }

    protected function getComparisonLabel(): ?string
    {
        $comparison = $this->getStringSetting(static::COMPARISON_NAME);

        return static::COMPARISON_LABELS[$comparison] ?? null;
    }

    protected function getInnerRulesComponent(): RuleBuilder
    {
        return RuleBuilder::make(static::INNER_RULES_NAME)
            ->label('Matching tours')
            ->constraints(TourConstraints::make())
            ->columnSpanFull();
    }

    protected function getComparisonComponent(): Select
    {
        return Select::make(static::COMPARISON_NAME)
            ->label('Comparison')
            ->options(static::COMPARISON_LABELS)
            ->default(array_key_first(static::COMPARISON_LABELS))
            // Same reasoning as the aggregate select: no placeholder means the
            // first option is shown even when the state is empty, so read it
            // as the state rather than failing `required()` on a field that
            // visibly has a value.
            ->formatStateUsing(fn (mixed $state): string => is_string($state) && $state !== ''
                ? $state
                : (string) array_key_first(static::COMPARISON_LABELS))
            ->selectablePlaceholder(false)
            ->required();
    }
}
