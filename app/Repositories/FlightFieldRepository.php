<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\FlightField;

/**
 * Class FlightFieldRepository.
 */
class FlightFieldRepository extends Repository
{
    protected $fieldSearchable = [
        'name' => 'like',
    ];

    public function model(): string
    {
        return FlightField::class;
    }
}
