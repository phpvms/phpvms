<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Models\Enums\UserState;
use App\Repositories\Criteria\WhereCriteria;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use League\ISO3166\ISO3166;
use Prettus\Repository\Exceptions\RepositoryException;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepo
    ) {
    }

    /**
     * @throws RepositoryException
     */
    public function index(Request $request): View
    {
        $with = ['airline', 'current_airport', 'fields.field', 'home_airport', 'rank'];
        $with_count = ['awards'];

        $where = [];

        if (setting('pilots.hide_inactive')) {
            $where['state'] = UserState::ACTIVE;
        }

        try {
            $this->userRepo->pushCriteria(new WhereCriteria($request, $where));
        } catch (RepositoryException $e) {
            Log::emergency($e);
        }

        $users = $this->userRepo
            ->withCount($with_count)
            ->with($with)
            ->sortable('id')
            ->paginate();

        return view('users.index', [
            'country' => new ISO3166(),
            'users'   => $users,
        ]);
    }
}
