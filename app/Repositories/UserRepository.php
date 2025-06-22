<?php

namespace App\Repositories;

use App\Contracts\Repository;
use App\Models\Enums\UserState;
use App\Models\User;
use App\Models\UserField;
use App\Repositories\Criteria\WhereCriteria;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UserRepository extends Repository
{
    protected $fieldSearchable = [
        'name'  => 'like',
        'email' => 'like',
        'home_airport_id',
        'curr_airport_id',
        'state',
    ];

    /**
     * @return string
     */
    public function model()
    {
        return User::class;
    }

    /**
     * Get all of the fields which has the mapped values
     *
     * @param  bool                                                                                            $only_public_fields   Only include the user's public fields
     * @param  mixed                                                                                           $with_internal_fields
     * @return \App\Models\UserField[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */
    public function getUserFields(User $user, $only_public_fields = null, $with_internal_fields = false): Collection
    {
        $fields = UserField::when(!$with_internal_fields, fn ($query) => $query->where('internal', false));

        if (is_bool($only_public_fields)) {
            $fields = $fields->where(['private' => !$only_public_fields]);
        }

        $fields = $fields->get();

        return $fields->map(function ($field, $_) use ($user): \Illuminate\Database\Eloquent\Model {
            foreach ($user->fields as $userFieldValue) {
                if ($userFieldValue->field->slug === $field->slug) {
                    $field->value = $userFieldValue->value;
                }
            }

            return $field;
        });
    }

    /**
     * Number of PIREPs that are pending
     *
     * @return mixed
     */
    public function getPendingCount()
    {
        $where = [
            'state' => UserState::PENDING,
        ];

        return $this->orderBy('created_at', 'desc')
            ->findWhere($where, ['id'])
            ->count();
    }

    /**
     * Create the search criteria and return this with the stuff pushed
     *
     *
     * @return $this
     *
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function searchCriteria(Request $request, bool $only_active = true): static
    {
        $where = [];

        if ($only_active) {
            $where['state'] = UserState::ACTIVE;
        }

        if ($request->filled('name')) {
            $where[] = ['name', 'LIKE', '%'.$request->name.'%'];
        }

        if ($request->filled('email')) {
            $where[] = ['email', 'LIKE', '%'.$request->email.'%'];
        }

        if ($request->filled('state')) {
            $where['state'] = $request->state;
        }

        $this->pushCriteria(new WhereCriteria($request, $where));

        return $this;
    }
}
