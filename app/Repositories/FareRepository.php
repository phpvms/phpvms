<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Fare;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

/**
 * Class FareRepository
 */
class FareRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    protected $fieldSearchable = [
        'code'  => 'like',
        'name'  => 'like',
        'notes' => 'like',
    ];

    public function model()
    {
        return Fare::class;
    }

    public function findByCode($code)
    {
        return $this->findByField('code', $code)->first();
    }
}
