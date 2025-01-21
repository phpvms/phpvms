<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Airport;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

class AirportRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    protected $fieldSearchable = [
        'iata' => 'like',
        'icao' => 'like',
        'name' => 'like',
    ];

    public function model()
    {
        return Airport::class;
    }

    /**
     * Return the list of airports formatted for a select box
     *
     * @param bool $add_blank
     * @param bool $only_hubs
     */
    public function selectBoxList($add_blank = false, $only_hubs = false): array
    {
        $retval = [];
        $where = [];

        if ($only_hubs) {
            $where['hub'] = 1;
        }

        $items = $this->orderBy('icao', 'asc')->findWhere($where);

        if ($add_blank) {
            $retval[''] = '';
        }

        foreach ($items as $i) {
            $s = $i->icao.' - '.$i->name;
            if (!$only_hubs && $i->hub) {
                $s .= ' (hub)';
            }

            $retval[$i->icao] = $s;
        }

        return $retval;
    }
}
