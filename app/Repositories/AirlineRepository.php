<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Airline;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

/**
 * @mixin \App\Models\Airline
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
     * Return the list of airline formatted for a select box.
     *
     * @param bool $add_blank
     * @param bool $only_active
     */
    public function selectBoxList($add_blank = false, $only_active = true): array
    {
        $retval = [];
        $where = [
            'active' => $only_active,
        ];

        $items = $this->findWhere($where);

        if ($add_blank) {
            $retval[''] = '';
        }

        foreach ($items as $i) {
            $retval[$i->id] = $i->name;
        }

        return $retval;
    }
}
