<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OptionPage
{
    /**
     * Fetch one page of a query as select options. One extra row is fetched
     * only to learn whether a next page exists.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>                                                                    $query
     * @param  Closure(TModel): mixed                                                             $label
     * @return array{options: array<int, array{value: string|int, label: string}>, hasMore: bool}
     */
    public static function fromQuery(Builder $query, int $page, int $perPage, Closure $label): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $options = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get()
            ->map(fn ($model): array => [
                'value' => $model->getKey(),
                'label' => (string) $label($model),
            ])
            ->all();

        return [
            'options' => array_slice($options, 0, $perPage),
            'hasMore' => count($options) > $perPage,
        ];
    }
}
