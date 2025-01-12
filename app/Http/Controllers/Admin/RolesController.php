<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Repositories\PermissionsRepository;
use App\Repositories\RoleRepository;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use Prettus\Repository\Criteria\RequestCriteria;

class RolesController extends Controller
{
    /**
     * AirlinesController constructor.
     */
    public function __construct(
        private readonly PermissionsRepository $permsRepo,
        private readonly RoleRepository $rolesRepo,
        private readonly RoleService $roleSvc
    ) {
    }

    /**
     * Display a listing of the Airlines.
     *
     *
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function index(Request $request): View
    {
        $this->rolesRepo->pushCriteria(new RequestCriteria($request));
        $roles = $this->rolesRepo->withCount('users')->findWhere(['read_only' => false]);

        return view('admin.roles.index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new Airlines.
     */
    public function create(): View
    {
        return view('admin.roles.create', [
            'permissions' => $this->permsRepo->all(),
        ]);
    }

    /**
     * Store a newly created Airlines in storage.
     *
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function store(CreateRoleRequest $request): RedirectResponse
    {
        $input = $request->all();

        // Create a slug using the display name provided
        $input['name'] = str_slug($input['display_name']);

        $this->rolesRepo->create($input);

        Flash::success('Role saved successfully.');

        return redirect(route('admin.roles.index'));
    }

    /**
     * Display the specified role.
     */
    public function show(int $id): RedirectResponse|View
    {
        $roles = $this->rolesRepo->findWithoutFail($id);

        if (empty($roles)) {
            Flash::error('Role not found');

            return redirect(route('admin.roles.index'));
        }

        return view('admin.roles.show', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for editing the specified roles.
     */
    public function edit(int $id): RedirectResponse|View
    {
        $role = $this->rolesRepo->withCount('users')->with('users')->findWithoutFail($id);

        if (empty($role)) {
            Flash::error('Role not found');

            return redirect(route('admin.role.index'));
        }

        return view('admin.roles.edit', [
            'role'        => $role,
            'users'       => $role->users,
            'users_count' => $role->users_count,
            'permissions' => $this->permsRepo->all(),
        ]);
    }

    /**
     * Update the specified Airlines in storage.
     */
    public function update(int $id, UpdateRoleRequest $request): RedirectResponse
    {
        $role = $this->rolesRepo->findWithoutFail($id);

        if (empty($role)) {
            Flash::error('Role not found');

            return redirect(route('admin.roles.index'));
        }

        $this->roleSvc->updateRole($role, $request->all());
        $this->roleSvc->setPermissionsForRole($role, $request->permissions);

        Flash::success('Roles updated successfully.');

        return redirect(route('admin.roles.index'));
    }

    /**
     * Remove the specified Airlines from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $roles = $this->rolesRepo->findWithoutFail($id);

        if (empty($roles)) {
            Flash::error('Role not found');

            return redirect(route('admin.roles.index'));
        }

        $this->rolesRepo->delete($id);

        Flash::success('Role deleted successfully.');

        return redirect(route('admin.roles.index'));
    }
}
