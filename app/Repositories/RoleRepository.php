<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Role;
use Prettus\Repository\Contracts\CacheableInterface;
use Prettus\Repository\Traits\CacheableRepository;

/**
 * Class RoleRepository.
 */
class RoleRepository extends Repository implements CacheableInterface
{
    use CacheableRepository;

    protected $fieldSearchable = [
        'name' => 'like',
    ];

    public function model(): string
    {
        return Role::class;
    }

    /**
     * Return the list of roles formatted for a select box.
     *
     * @param bool $include_read_only
     * @param bool $add_blank
     */
    public function selectBoxList($include_read_only = true, $add_blank = false): array
    {
        $retval = [];

        $where = [];
        if ($include_read_only) {
            $where['read_only'] = true;
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
