<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Permission;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

/**
 * Class RoleRepository
 */
class PermissionsRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    protected $fieldSearchable = [
        'name' => 'like',
    ];

    public function model(): string
    {
        return Permission::class;
    }

    /**
     * Return the list of roles formatted for a select box
     *
     * @param bool $add_blank
     */
    public function selectBoxList($add_blank = false): array
    {
        $retval = [];
        $items = $this->all();

        if ($add_blank) {
            $retval[''] = '';
        }

        foreach ($items as $i) {
            $retval[$i->id] = $i->name;
        }

        return $retval;
    }
}
