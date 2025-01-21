<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Subfleet;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

class SubfleetRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    protected $fieldSearchable = [
        'name' => 'like',
        'type' => 'like',
    ];

    /**
     * @return string
     */
    public function model()
    {
        return Subfleet::class;
    }

    /**
     * Return the list of aircraft formatted for a select box
     *
     * @param bool $add_blank
     */
    public function selectBoxList($add_blank = false): array
    {
        $retval = [];
        $items = $this->with('airline')->all();

        if ($add_blank) {
            $retval[''] = '';
        }

        foreach ($items as $i) {
            $retval[$i->id] = $i->name.' | '.$i->airline->icao;
        }

        return $retval;
    }
}
