<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Repositories\UserFieldRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Flash\Flash;
use Prettus\Repository\Criteria\RequestCriteria;

class UserFieldController extends Controller
{
    public function __construct(
        private readonly UserFieldRepository $userFieldRepo
    ) {
    }

    /**
     * Display a listing of the UserField.
     *
     *
     * @throws \Prettus\Repository\Exceptions\RepositoryException
     */
    public function index(Request $request): View
    {
        $this->userFieldRepo->pushCriteria(new RequestCriteria($request));
        $fields = $this->userFieldRepo->where('internal', false)->get();

        return view('admin.userfields.index', ['fields' => $fields]);
    }

    /**
     * Show the form for creating a new UserField.
     */
    public function create(): View
    {
        return view('admin.userfields.create');
    }

    /**
     * Store a newly created UserField in storage.
     *
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function store(Request $request): RedirectResponse
    {
        $this->userFieldRepo->create($request->all());

        Flash::success('Field added successfully.');

        return redirect(route('admin.userfields.index'));
    }

    /**
     * Display the specified UserField.
     */
    public function show(int $id): View
    {
        $field = $this->userFieldRepo->findWithoutFail($id);

        if (empty($field)) {
            Flash::error('Flight field not found');

            return redirect(route('admin.userfields.index'));
        }

        return view('admin.userfields.show', ['field' => $field]);
    }

    /**
     * Show the form for editing the specified UserField.
     */
    public function edit(int $id): RedirectResponse|View
    {
        $field = $this->userFieldRepo->findWithoutFail($id);

        if (empty($field)) {
            Flash::error('Field not found');

            return redirect(route('admin.userfields.index'));
        }

        if ($field->internal) {
            Flash::error('You cannot edit an internal user field');

            return redirect(route('admin.userfields.index'));
        }

        return view('admin.userfields.edit', ['field' => $field]);
    }

    /**
     * Update the specified UserField in storage.
     *
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function update(int $id, Request $request): RedirectResponse
    {
        $field = $this->userFieldRepo->findWithoutFail($id);

        if (empty($field)) {
            Flash::error('UserField not found');

            return redirect(route('admin.userfields.index'));
        }

        if ($field->internal) {
            Flash::error('You cannot edit an internal user field');

            return redirect(route('admin.userfields.index'));
        }

        $this->userFieldRepo->update($request->all(), $id);

        Flash::success('Field updated successfully.');

        return redirect(route('admin.userfields.index'));
    }

    /**
     * Remove the specified UserField from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $field = $this->userFieldRepo->findWithoutFail($id);
        if (empty($field)) {
            Flash::error('Field not found');

            return redirect(route('admin.userfields.index'));
        }

        if ($field->internal) {
            Flash::error('You cannot delete an internal user field');

            return redirect(route('admin.userfields.index'));
        }

        if ($this->userFieldRepo->isInUse($id)) {
            Flash::error('This field cannot be deleted, it is in use. Deactivate it instead');

            return redirect(route('admin.userfields.index'));
        }

        $this->userFieldRepo->delete($id);

        Flash::success('Field deleted successfully.');

        return redirect(route('admin.userfields.index'));
    }
}
