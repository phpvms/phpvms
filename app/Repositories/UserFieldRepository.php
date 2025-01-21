<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\UserField;
use App\Models\UserFieldValue;

class UserFieldRepository extends Repository
{
    protected $fieldSearchable = [
        'name' => 'like',
    ];

    public function model(): string
    {
        return UserField::class;
    }

    /**
     * Return whether or not this field is in use by a value.
     */
    public function isInUse($id): bool
    {
        return UserFieldValue::where(['user_field_id' => $id])->exists();
    }
}
