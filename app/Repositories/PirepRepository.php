<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Models\User;

/**
 * Class PirepRepository
 */
class PirepRepository extends Repository
{
    protected $fieldSearchable = [
        'user_id',
        'status',
        'state',
    ];

    /**
     * @return string
     */
    public function model()
    {
        return Pirep::class;
    }

    /**
     * Get all the pending reports in order. Returns the Pirep
     * model but you still need to call ->all() or ->paginate()
     *
     *
     * @return Pirep
     */
    public function getPending(?User $user = null)
    {
        $where = [];
        if ($user instanceof User) {
            $where['user_id'] = $user->id;
        }

        $pireps = $this->orderBy('created_at', 'desc')->findWhere($where)->all();

        return $pireps;
    }

    /**
     * Number of PIREPs that are pending
     *
     *
     * @return mixed
     */
    public function getPendingCount(?User $user = null)
    {
        $where = [
            'state' => PirepState::PENDING,
        ];

        if ($user instanceof User) {
            $where['user_id'] = $user->id;
        }

        $pireps = $this->orderBy('created_at', 'desc')
            ->findWhere($where, ['id'])
            ->count();

        return $pireps;
    }
}
