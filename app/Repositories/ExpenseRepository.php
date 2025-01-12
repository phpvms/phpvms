<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Expense;
use Illuminate\Support\Collection;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

class ExpenseRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    public function model()
    {
        return Expense::class;
    }

    /**
     * Get all of the expenses for a given type, and also
     * include expenses for a given airline ID.
     *
     * @param  null       $airline_id
     * @param  null       $ref_model
     * @param  mixed      $ref_model_id
     * @return Collection
     */
    public function getAllForType($type, $airline_id = null, $ref_model = null, $ref_model_id = null)
    {
        $where = [
            'type'   => $type,
            'active' => true,
            ['airline_id', '=', null],
        ];

        if ($ref_model) {
            if (\is_object($ref_model)) {
                $ref_model_type = \get_class($ref_model);
            } else {
                $ref_model_type = $ref_model;
            }

            if ($ref_model) {
                $where['ref_model'] = $ref_model_type;
            }

            if ($ref_model_id) {
                $where['ref_model_id'] = $ref_model_id;
            }
        }

        $expenses = $this->findWhere($where);

        if ($airline_id) {
            $where = [
                'type'       => $type,
                'active'     => true,
                'airline_id' => $airline_id,
            ];

            if ($ref_model) {
                $where['ref_model'] = $ref_model_type;
            }

            $airline_expenses = $this->findWhere($where);
            $expenses = $expenses->concat($airline_expenses);
        }

        return $expenses;
    }
}
