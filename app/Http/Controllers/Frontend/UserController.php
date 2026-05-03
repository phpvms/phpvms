<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Http\Requests\SearchUsersRequest;
use App\Queries\UserSearchQuery;
use Illuminate\View\View;
use League\ISO3166\ISO3166;

class UserController extends Controller
{
    public function __construct(
        private readonly UserSearchQuery $userSearchQuery
    ) {}

    /**
     * Show the public list of pilots, with search/filter/sort/paginate.
     */
    public function index(SearchUsersRequest $request): View
    {
        $perPage = paginate_limit($request->integer('limit') ?: null);

        $pilots = $this->userSearchQuery
            ->build($request)
            ->paginate($perPage);

        return view('users.index', [
            'country' => new ISO3166(),
            'users'   => $pilots,
        ]);
    }
}
