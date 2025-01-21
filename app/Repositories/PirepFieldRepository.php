<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\PirepField;

/**
 * Class PirepFieldRepository
 */
class PirepFieldRepository extends Repository
{
    protected $fieldSearchable = [
        'name' => 'like',
    ];

    public function model()
    {
        return PirepField::class;
    }
}
