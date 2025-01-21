<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Navdata;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

/**
 * Class NavdataRepository
 */
class NavdataRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    // Super short lived cache for when the navdata stuff is re-imported
    protected $cacheMinutes = 5;

    public function model()
    {
        return Navdata::class;
    }
}
