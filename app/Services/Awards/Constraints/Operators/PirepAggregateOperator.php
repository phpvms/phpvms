<?php

declare(strict_types=1);

namespace App\Services\Awards\Constraints\Operators;

use App\Models\Pirep;
use App\Models\User;
use App\Services\Awards\Constraints\PirepConstraint;
use App\Services\Awards\PirepConstraints;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * "The sum / average / minimum / maximum of <column> over the user's accepted
 * PIREPs matching these rules is at least / at most / exactly N."
 *
 * A correlated scalar subquery over the *same* inner-filtered set, following
 * the vendor's own aggregate shape (`CanAggregateRelationships`): a cast
 * `selectRaw`, a `whereColumn` correlation, and a bound comparison value. The
 * window is just another inner rule, which is what makes "hours flown in the
 * last 30 days" expressible at all.
 */
class PirepAggregateOperator extends PirepOperator
{
    public const string COLUMN_NAME = 'column';

    public const string AGGREGATE_NAME = 'aggregate';

    public const string VALUE_NAME = 'value';

    /** @var array<string, string> */
    protected const array AGGREGATES = [
        'sum' => 'Sum',
        'avg' => 'Average',
        'min' => 'Minimum',
        'max' => 'Maximum',
    ];

    #[Override]
    public function getName(): string
    {
        return 'aggregate';
    }

    #[Override]
    public function getLabel(): string
    {
        return $this->isInverse() ? 'aggregate is not' : 'aggregate is';
    }

    #[Override]
    public function getSummary(): string
    {
        $value = $this->getNumericSetting(static::VALUE_NAME);

        return trim(sprintf(
            '%s of %s over PIREPs is %s%s %s',
            static::AGGREGATES[$this->getAggregate() ?? ''] ?? '',
            $this->getColumnOptions()[$this->getColumn() ?? ''] ?? '',
            $this->isInverse() ? 'not ' : '',
            strtolower($this->getComparisonLabel() ?? ''),
            $value === null ? '' : (string) $value,
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
            Select::make(static::AGGREGATE_NAME)
                ->label('Aggregate')
                ->options(static::AGGREGATES)
                ->default(array_key_first(static::AGGREGATES))
                ->selectablePlaceholder(false)
                ->required(),
            Select::make(static::COLUMN_NAME)
                ->label('Of')
                ->options($this->getColumnOptions())
                ->required(),
            $this->getComparisonComponent(),
            TextInput::make(static::VALUE_NAME)
                ->label('Value')
                ->numeric()
                ->required(),
            $this->getTriggeringPirepScopeComponent(),
        ];
    }

    /**
     * @param  Builder<User> $query
     * @return Builder<User>
     */
    #[Override]
    public function applyToBaseQuery(Builder $query): Builder
    {
        $value = $this->getNumericSetting(static::VALUE_NAME);
        $column = $this->getColumn();
        $aggregate = $this->getAggregate();
        $comparison = $this->getComparison();

        // Security: every part of the generated SQL comes from a server-side
        // allowlist -- the aggregate function, the column, and the comparison --
        // and the value is bound. Anything unrecognised, or a triggering-PIREP
        // scope with no PIREP bound, applies nothing at all.
        if ($value === null || $column === null || $aggregate === null || $comparison === null || !$this->canApply()) {
            return $query;
        }

        /** @var HasMany<Pirep, User> $relationship */
        $relationship = $query->getModel()->{PirepConstraint::RELATIONSHIP}();

        $related = $relationship->getModel();
        $qualifiedColumn = $related->qualifyColumn($column);
        $castType = $this->getNumericCastType($query);

        $subQuery = $this->applyInnerRules(
            $related->newQuery()
                ->selectRaw("cast({$aggregate}({$qualifiedColumn}) as {$castType})")
                ->whereColumn($relationship->getQualifiedForeignKeyName(), $relationship->getQualifiedParentKeyName()),
        );

        return $query->whereRaw(
            "({$subQuery->toSql()}) {$comparison} ?",
            [...$subQuery->getBindings(), $value],
        );
    }

    /**
     * The numeric `pireps` columns, taken straight from the inner vocabulary so
     * the two never drift and so the column can only ever be one we registered.
     *
     * @return array<string, string>
     */
    protected function getColumnOptions(): array
    {
        $options = [];

        foreach (PirepConstraints::make() as $constraint) {
            if ($constraint instanceof NumberConstraint) {
                $options[$constraint->getAttribute()] = $constraint->getLabel();
            }
        }

        return $options;
    }

    protected function getColumn(): ?string
    {
        $column = $this->getStringSetting(static::COLUMN_NAME);

        if ($column === null || !array_key_exists($column, $this->getColumnOptions())) {
            return null;
        }

        return $column;
    }

    protected function getAggregate(): ?string
    {
        $aggregate = $this->getStringSetting(static::AGGREGATE_NAME);

        if ($aggregate === null || !array_key_exists($aggregate, static::AGGREGATES)) {
            return null;
        }

        return $aggregate;
    }
}
