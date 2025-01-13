<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Http\Requests\CreatePageRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Repositories\PageRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laracasts\Flash\Flash;

class PagesController extends Controller
{
    public function __construct(
        private readonly PageRepository $pageRepo
    ) {
    }

    public function index(Request $request): View
    {
        $pages = $this->pageRepo->all();

        return view('admin.pages.index', [
            'pages' => $pages,
        ]);
    }

    /**
     * Show the form for creating a new Airlines.
     */
    public function create(): View
    {
        return view('admin.pages.create');
    }

    /**
     * Store a newly created Airlines in storage.
     *
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function store(CreatePageRequest $request): RedirectResponse
    {
        $input = $request->all();
        $this->pageRepo->create($input);

        Flash::success('Page saved successfully.');

        return redirect(route('admin.pages.index'));
    }

    /**
     * Display the specified page.
     */
    public function show(int $id): View
    {
        $pages = $this->pageRepo->findWithoutFail($id);

        if (empty($pages)) {
            Flash::error('Page not found');

            return redirect(route('admin.page.index'));
        }

        return view('admin.pages.show', [
            'pages' => $pages,
        ]);
    }

    /**
     * Show the form for editing the specified pages.
     */
    public function edit(int $id): RedirectResponse|View
    {
        $page = $this->pageRepo->findWithoutFail($id);

        if (empty($page)) {
            Flash::error('Page not found');

            return redirect(route('admin.pages.index'));
        }

        return view('admin.pages.edit', [
            'page' => $page,
        ]);
    }

    /**
     * Update the specified Airlines in storage.
     *
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function update(int $id, UpdatePageRequest $request): RedirectResponse
    {
        $page = $this->pageRepo->findWithoutFail($id);

        if (empty($page)) {
            Flash::error('page not found');

            return redirect(route('admin.pages.index'));
        }

        $this->pageRepo->update($request->all(), $id);

        Flash::success('pages updated successfully.');

        return redirect(route('admin.pages.index'));
    }

    /**
     * Remove the specified Airlines from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $pages = $this->pageRepo->findWithoutFail($id);

        if (empty($pages)) {
            Flash::error('Page not found');

            return redirect(route('admin.pages.index'));
        }

        $this->pageRepo->delete($id);

        Flash::success('page deleted successfully.');

        return redirect(route('admin.pages.index'));
    }
}
