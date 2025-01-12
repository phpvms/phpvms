<?php

namespace App\Repositories\Criteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Class RequestCriteria.
 */
class WhereCriteria implements CriteriaInterface
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    protected $where;

    protected $relations;

    /**
     * Create a new Where search.
     *
     * @param array $where
     * @param array   [$relations] Any whereHas (key = table name, value = array of criterea
     */
    public function __construct(Request $request, $where, $relations = [])
    {
        $this->request = $request;
        $this->where = $where;
        $this->relations = $relations;
    }

    /**
     * Apply criteria in query repository.
     *
     * @param Builder|Model $model
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function apply($model, RepositoryInterface $repository)
    {
        if ($this->where) {
            $model = $model->where($this->where);
        }

        // See if any relationships need to be included in this WHERE
        if ($this->relations) {
            foreach ($this->relations as $relation => $criterea) {
                $model = $model
                    ->with($relation)
                    ->whereHas($relation, function (Builder $query) use ($criterea) {
                        // By Taylor Broad
                        if (!isset($criterea['method'])) {
                            $query->where($criterea);
                        } else {
                            if ($criterea['method'] == 'where') {
                                $query->where($criterea['query']);
                            }
                            if ($criterea['method'] == 'whereIn') {
                                $query->whereIn($criterea['query']['key'], $criterea['query']['values']);
                            }
                        }
                    });
            }
        }

        return $model;
    }
}
