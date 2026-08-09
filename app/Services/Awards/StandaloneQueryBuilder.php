<?php

declare(strict_types=1);

namespace App\Services\Awards;

use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * The Filament query-builder filter, usable without a table.
 *
 * `getConstraints()`/`getConstraint()` are the only members that reach for
 * `$this->getTable()->getModel()` (`QueryBuilder.php:526,531`); nothing else
 * in the filter touches the table. Overriding those two to take the model
 * class directly makes `applyRulesToQuery()` callable from a cron or a job.
 */
class StandaloneQueryBuilder extends QueryBuilder
{
    /** @var class-string<Model> */
    protected string $modelClass;

    /**
     * @param class-string<Model> $model
     */
    public function forModel(string $model): static
    {
        $this->modelClass = $model;

        return $this;
    }

    /**
     * @return array<string, Constraint>
     */
    #[Override]
    public function getConstraints(): array
    {
        return array_map(
            fn (Constraint $constraint): Constraint => $constraint->model($this->modelClass),
            $this->constraints,
        );
    }

    #[Override]
    public function getConstraint(string $name): ?Constraint
    {
        return ($this->constraints[$name] ?? null)?->model($this->modelClass);
    }

    /**
     * Drop rules whose constraint is no longer registered.
     *
     * A stored tree outlives the constraint set that produced it: a snippet
     * row can be deleted, and an imported tree can name a constraint that
     * never existed here. `RuleBuilder` silently omits an unknown block type
     * from its child schemas, and the parent then throws an opaque
     * `LogicException` for the missing schema (`QueryBuilder.php:213`).
     *
     * Turn that into a named failure rather than skipping the rule. Dropping
     * it would leave the query missing a criterion, and for an award "fewer
     * criteria" means "more users qualify" -- a silent mass grant. Nested OR
     * groups recurse through `$this`, so they are covered too.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>      $query
     * @param  array<string, mixed> $rules
     * @return Builder<TModel>
     *
     * @throws CriteriaCompilationFailed
     */
    #[Override]
    public function applyRulesToQuery(Builder $query, array $rules, RuleBuilder $ruleBuilder): Builder
    {
        foreach ($rules as $key => $rule) {
            if (!$ruleBuilder->getChildSchema((string) $key) instanceof Schema) {
                throw CriteriaCompilationFailed::unknownConstraint((string) ($rule['type'] ?? $key));
            }
        }

        return parent::applyRulesToQuery($query, $rules, $ruleBuilder);
    }
}
