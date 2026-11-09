<?php

namespace App\Features\Typeratings\Queries;

use App\Models\Typerating;
use App\Support\OptionPage;
use Closure;
use Illuminate\Database\Eloquent\Builder;

use function Filament\Support\apply_search_constraint;
use function Filament\Support\generate_search_pattern;

final class TyperatingOptions
{
    /**
     * @param  Closure(Builder<Typerating>): mixed|null                                           $scope Adds caller constraints to the query
     * @return array{options: array<int, array{value: string|int, label: string}>, hasMore: bool}
     */
    public function page(string $search, int $page, int $perPage, ?Closure $scope = null): array
    {
        $query = Typerating::query();

        if ($scope instanceof Closure) {
            $scope($query);
        }

        $query
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = generate_search_pattern($search, hasLeadingWildcard: true, hasTrailingWildcard: true);

                $query->where(function (Builder $query) use ($pattern): void {
                    apply_search_constraint($query, 'name', $pattern);
                    apply_search_constraint($query, 'type', $pattern, 'or');
                });
            })
            ->orderBy('name')
            ->orderBy('id');

        return OptionPage::fromQuery(
            $query,
            $page,
            $perPage,
            $this->format(...),
        );
    }

    /**
     * @param Closure(Builder<Typerating>): mixed|null $scope Adds caller constraints to the query
     */
    public function label(mixed $value, ?Closure $scope = null): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $query = Typerating::query();

        if ($scope instanceof Closure) {
            $scope($query);
        }

        $typerating = $query->whereKey($value)->first();

        return $typerating instanceof Typerating ? $this->format($typerating) : null;
    }

    private function format(Typerating $typerating): string
    {
        return "{$typerating->name} ({$typerating->type})";
    }
}
