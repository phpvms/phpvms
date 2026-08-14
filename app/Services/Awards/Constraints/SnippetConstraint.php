<?php

declare(strict_types=1);

namespace App\Services\Awards\Constraints;

use App\Models\AwardRule;
use App\Models\AwardSnippet;
use App\Services\Awards\CriteriaCompiler;
use App\Services\Awards\SnippetConstraints;
use App\Services\Awards\UserConstraints;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\Operators\Operator;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * A reference to a saved `award_snippets` row.
 *
 * Registered under `snippet:<name>` so the stored rule's `type` is what
 * `AwardRule::snippetNames()` looks for when it mirrors references into the
 * `award_rule_snippet` pivot. Snippets take no parameters (design D7): the
 * reference compiles by expanding the snippet's own stored tree into the
 * query unchanged.
 */
class SnippetConstraint extends Constraint
{
    /**
     * How many snippet references deep an expansion still follows. A snippet
     * whose tree references itself, directly or around a cycle, would
     * otherwise recurse forever; past the limit the reference applies
     * nothing. Depth is carried on the constraint rather than a counter, so
     * nothing has to be reset if an expansion throws.
     */
    public const int MAX_DEPTH = 3;

    /** @var array<string, mixed> */
    protected array $conditions = [];

    protected int $depth = 0;

    public static function forSnippet(AwardSnippet $snippet, int $depth = 0): static
    {
        return static::make(AwardRule::SNIPPET_PREFIX.$snippet->name)
            ->label($snippet->label)
            ->conditions($snippet->conditions ?? [])
            ->depth($depth);
    }

    /**
     * @param array<string, mixed> $conditions
     */
    public function conditions(array $conditions): static
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function depth(int $depth): static
    {
        $this->depth = $depth;

        return $this;
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->icon(Heroicon::Bookmark);

        $this->operators([
            Operator::make('matches')
                ->label(fn (?bool $isInverse): string => $isInverse ? 'Does not match' : 'Matches')
                ->summary(fn (?bool $isInverse): string => ($isInverse ? 'Does not match ' : 'Matches ').$this->getLabel())
                ->query(fn (Builder $query, ?bool $isInverse): Builder => $isInverse
                    ? $query->whereNot(fn (Builder $query): Builder => $this->expand($query))
                    : $query->where(fn (Builder $query): Builder => $this->expand($query))),
        ]);
    }

    /**
     * Compile the snippet's own tree onto a nested query. An empty tree, or
     * one nested past the depth limit, leaves the query untouched -- and an
     * untouched nested query is discarded by `addNestedWhereQuery()`, so the
     * reference contributes no SQL either way round the inverse.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel> $query
     * @return Builder<TModel>
     */
    protected function expand(Builder $query): Builder
    {
        if ($this->conditions === [] || $this->depth >= self::MAX_DEPTH) {
            return $query;
        }

        return new CriteriaCompiler()->compile(
            $query,
            $this->conditions,
            [...UserConstraints::make(), ...SnippetConstraints::make($this->depth + 1)],
            $query->getModel()::class,
        );
    }
}
