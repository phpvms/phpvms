<?php

declare(strict_types=1);

namespace App\Services\Awards;

use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Compiles a stored rule tree into an Eloquent query.
 *
 * Works with no HTTP request and no table instance, so the award run
 * service, the PIREP listener and the nightly cron all share one path.
 */
class CriteriaCompiler
{
    public function __construct(
        protected int $maxRules = 50,
        protected int $maxNestingDepth = 5,
    ) {}

    /**
     * Apply a stored rule tree to `$query`.
     *
     * Fails closed: a tree that cannot be compiled exactly throws rather than
     * returning a query with criteria missing. The vendor filter drops criteria
     * instead, which is right for a table (you see every row) and catastrophic
     * for an award (every user qualifies and is granted it).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>                                                $query
     * @param  array<string, array{type: string, data: array<string, mixed>}> $tree
     * @param  array<Constraint>                                              $constraints
     * @param  class-string<TModel>                                           $model
     * @return Builder<TModel>
     *
     * @throws CriteriaCompilationFailed
     */
    public function compile(Builder $query, array $tree, array $constraints, string $model): Builder
    {
        if ($tree === []) {
            return $query;
        }

        $filter = StandaloneQueryBuilder::make('criteria')
            ->constraints($constraints)
            ->forModel($model)
            ->maxRules($this->maxRules)
            ->maxNestingDepth($this->maxNestingDepth);

        if ($filter->exceedsRuleLimits($tree)) {
            throw CriteriaCompilationFailed::exceedsBounds($this->maxRules, $this->maxNestingDepth);
        }

        $ruleBuilder = $this->hydrateRuleBuilder($tree, $filter->getConstraints());

        // The hydrated state, never `$tree`: `Builder` regenerates item keys on
        // fill, and `applyRulesToQuery()` resolves each child schema by the key
        // it is iterating. Passing the stored array throws
        // "No query builder block found for [...]".
        return $filter->applyRulesToQuery($query, $ruleBuilder->getState(), $ruleBuilder);
    }

    /**
     * @param array<string, mixed> $tree
     * @param array<Constraint>    $constraints
     */
    protected function hydrateRuleBuilder(array $tree, array $constraints): RuleBuilder
    {
        $host = new CriteriaSchemaHost();
        $host->data = ['rules' => $tree];

        $schema = Schema::make($host)
            ->statePath('data')
            ->components([
                RuleBuilder::make('rules')->constraints($constraints),
            ]);

        $schema->fill(['rules' => $tree]);

        $ruleBuilder = $schema->getComponent(fn (Component $component): bool => $component instanceof RuleBuilder);

        if (!$ruleBuilder instanceof RuleBuilder) {
            throw new LogicException('No rule builder component found.');
        }

        return $ruleBuilder;
    }
}
