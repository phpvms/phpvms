<?php

namespace App\Contracts;

use Exception;
use Illuminate\Validation\Validator;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Exceptions\RepositoryException;

use function is_array;

/**
 * @mixin BaseRepository
 */
abstract class Repository extends BaseRepository
{
    /**
     * @return mixed|null
     */
    public function findWithoutFail($id, array $columns = ['*'])
    {
        try {
            return $this->find($id, $columns);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return bool
     */
    public function validate($values)
    {
        $validator = Validator::make($values, $this->model()->rules);
        if ($validator->fails()) {
            return $validator->messages();
        }

        return true;
    }

    /**
     * Return N most recent items, sorted by created_at
     *
     * @param  int    $count
     * @param  string $sort_by created_at (default) or updated_at
     * @return mixed
     *
     * @throws RepositoryException
     */
    public function recent($count = null, $sort_by = 'created_at')
    {
        return $this->orderBy($sort_by, 'desc')->paginate($count);
    }

    /**
     * Find records with a WHERE clause but also sort them
     *
     *
     * @return $this
     */
    public function whereOrder($where, $sort_by, $order_by = 'asc')
    {
        return $this->scopeQuery(function ($query) use ($where, $sort_by, $order_by) {
            $q = $query->where($where);
            // See if there are multi-column sorts
            if (is_array($sort_by)) {
                foreach ($sort_by as $key => $sort) {
                    $q = $q->orderBy($key, $sort);
                }
            } else {
                $q = $q->orderBy($sort_by, $order_by);
            }

            return $q;
        });
    }

    /**
     * Find records where values don't match a list but sort the rest
     *
     * @param  string $col
     * @param  array  $values
     * @param  string $sort_by
     * @param  string $order_by
     * @return $this
     */
    public function whereNotInOrder($col, $values, $sort_by, $order_by = 'asc')
    {
        return $this->scopeQuery(function ($query) use ($col, $values, $sort_by, $order_by) {
            $q = $query->whereNotIn($col, $values);
            // See if there are multi-column sorts
            if (is_array($sort_by)) {
                foreach ($sort_by as $key => $sort) {
                    $q = $q->orderBy($key, $sort);
                }
            } else {
                $q = $q->orderBy($sort_by, $order_by);
            }

            return $q;
        });
    }

    /**
     * Retrieve all data of repository, paginated. Added in extra parameter to read from the
     * request which page it should be on
     *
     * @param  null   $limit
     * @param  array  $columns
     * @param  string $method
     * @return mixed
     *
     * @throws RepositoryException
     */
    public function paginate($limit = null, $columns = ['*'], $method = 'paginate')
    {
        $this->applyCriteria();
        $this->applyScope();

        $max = config('repository.pagination.limit', 50);
        $limit = (int) ($limit ?? request()->query('limit') ?? $max);
        $page = (int) request()->query('page', 1);

        $results = $this->model->{$method}($limit, $columns, 'page', $page);

        $qs = request()->except(['page', 'user']);
        $results->appends($qs);

        $this->resetModel();

        return $this->parserResult($results);
    }
}
