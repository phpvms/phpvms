<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\SimBriefAirframe;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

class AirframeRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    protected $fieldSearchable = [
        'icao'        => 'like',
        'name'        => 'like',
        'airframe_id' => 'like',
        'source'      => 'like',
    ];

    public function model()
    {
        return SimBriefAirframe::class;
    }

    public function selectBoxList($add_blank = false, $only_custom = false, $icao = null): array
    {
        $retval = [];
        $where = [];
        if ($only_custom) {
            $where['source'] = 'Custom';
        }

        if (filled($icao)) {
            $where['icao'] = $icao;
        }

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
