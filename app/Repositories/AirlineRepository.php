<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Airline;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

/**
 * @mixin Airline
 */
class AirlineRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    protected $fieldSearchable = [
        'code',
        'name' => 'like',
    ];

    public function model()
    {
        return Airline::class;
    }

    /**
     * Return the list of airline formatted for a select box
     */
    public function selectBoxList(bool $add_blank = false, bool $only_active = true, string $order_by = 'id'): array
    {
        $retval = [];
        $where = [
            'active' => $only_active,
        ];

        $items = $this->orderBy($order_by)->findWhere($where);

        if ($add_blank) {
            $retval[''] = '';
        }

        foreach ($items as $i) {
            $retval[$i->id] = $i->name;
        }

        return $retval;
    }
}
