<?php

declare(strict_types=1);

namespace App\Services\Awards\Constraints\Operators;

use App\Enums\PirepState;
use App\Models\Pirep;
use App\Services\Awards\Constraints\PirepConstraint;
use App\Services\Awards\CriteriaCompiler;
use App\Services\Awards\PirepConstraints;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\QueryBuilder\Constraints\Operators\Operator;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared machinery for the PIREP constraint's operators: the nested rule
 * builder, the comparison vocabulary, and the scopes the server forces onto
 * every subquery.
 */
abstract class PirepOperator extends Operator
{
    /** Settings key holding the nested rule tree describing one PIREP. */
    public const string INNER_RULES_NAME = 'pirepRules';

    /** Settings key opting the rule into the triggering-PIREP scope (design D6). */
    public const string TRIGGERING_PIREP_SCOPE_NAME = 'thisPirep';

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
     * Apply the forced scopes and the rule's own inner tree to a subquery over
     * `pireps`.
     *
     * `state = ACCEPTED` is ANDed at the top level of the subquery, so an
     * inner tree -- whose own top level the vendor wraps in a nested group
     * when it contains an OR block -- can only narrow the set further, never
     * widen it. The user correlation is supplied by the caller: `has()` for a
     * count, an explicit `whereColumn()` for an aggregate.
     *
     * @param  Builder<Pirep> $query
     * @return Builder<Pirep>
     */
    protected function applyInnerRules(Builder $query): Builder
    {
        $query->where($query->getModel()->qualifyColumn('state'), PirepState::ACCEPTED->value);

        if ($this->isScopedToTriggeringPirep()) {
            $query->whereKey($this->getPirepConstraint()?->getTriggeringPirepId());
        }

        $tree = $this->getSettings()[static::INNER_RULES_NAME] ?? [];

        if (!is_array($tree) || $tree === []) {
            return $query;
        }

        return app(CriteriaCompiler::class)->compile($query, $tree, PirepConstraints::make(), Pirep::class);
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

    protected function isScopedToTriggeringPirep(): bool
    {
        return (bool) ($this->getSettings()[static::TRIGGERING_PIREP_SCOPE_NAME] ?? false);
    }

    /**
     * A rule scoped to the triggering PIREP is meaningless -- and dangerously
     * wide -- with no PIREP bound, so it applies nothing at all.
     */
    protected function canApply(): bool
    {
        if (!$this->isScopedToTriggeringPirep()) {
            return true;
        }

        return filled($this->getPirepConstraint()?->getTriggeringPirepId());
    }

    protected function getPirepConstraint(): ?PirepConstraint
    {
        $constraint = $this->getConstraint();

        return $constraint instanceof PirepConstraint ? $constraint : null;
    }

    /**
     * The driver comes off the model rather than off `$query->getConnection()`,
     * which is only typed as the `ConnectionInterface` the driver name is not on.
     * Both resolve the same connection: the builder was built from the model's.
     *
     * @param Builder<covariant Model> $query
     */
    protected function getNumericCastType(Builder $query): string
    {
        return match ($query->getModel()->getConnection()->getDriverName()) {
            'sqlite' => 'real',
            'pgsql'  => 'numeric',
            default  => 'decimal(10,2)',
        };
    }

    protected function getInnerRulesComponent(): RuleBuilder
    {
        return RuleBuilder::make(static::INNER_RULES_NAME)
            ->label('Matching PIREPs')
            ->constraints(PirepConstraints::make())
            ->columnSpanFull();
    }

    protected function getComparisonComponent(): Select
    {
        return Select::make(static::COMPARISON_NAME)
            ->label('Comparison')
            ->options(static::COMPARISON_LABELS)
            ->default(array_key_first(static::COMPARISON_LABELS))
            ->selectablePlaceholder(false)
            ->required();
    }

    protected function getTriggeringPirepScopeComponent(): Checkbox
    {
        // Resolved now, not in a closure: the constraint is attached to the
        // operator only while `getFormSchema()` runs, and a deferred
        // `visible()` would evaluate later with nothing attached -- hiding the
        // field, and with it stripping the setting out of the saved state.
        $isAvailable = $this->getPirepConstraint()?->canScopeToTriggeringPirep() ?? false;

        return Checkbox::make(static::TRIGGERING_PIREP_SCOPE_NAME)
            ->label('Only the PIREP that triggered this award')
            ->visible($isAvailable)
            ->columnSpanFull();
    }
}
