<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Services\ModuleService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ModulesController extends Controller
{
    public function __construct(
        private readonly ModuleService $moduleSvc
    ) {
    }

    /**
     * Display a listing of the Module.
     */
    public function index(): View
    {
        $modules = $this->moduleSvc->getAllModules();
        $new_modules = $this->moduleSvc->scan();

        return view('admin.modules.index', [
            'modules'     => $modules,
            'new_modules' => $new_modules,
        ]);
    }

    /**
     * Show the form for creating a new Module.
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('admin.modules.create');
    }

    /**
     * Store a newly Uploaded Module in the Storage.
     */
    public function store(Request $request): View
    {
        $this->moduleSvc->installModule($request->file('module_file'));

        return $this->index();
    }

    /**
     * Show the form for editing the specified Module.
     */
    public function edit(int $id): View
    {
        $module = $this->moduleSvc->getModule($id);

        return view('admin.modules.edit', [
            'module' => $module,
        ]);
    }

    /**
     * Update the specified Module in storage.
     */
    public function update(int $id, Request $request): RedirectResponse
    {
        $this->moduleSvc->updateModule($id, $request->has('enabled'));
        flash()->success('Module Status Changed!');

        return redirect(route('admin.modules.index'));
    }

    /**
     * Enabling Module Present in the Modules Folder
     */
    public function enable(Request $request): RedirectResponse
    {
        $moduleName = $request->input('name');

        try {
            $this->moduleSvc->addModule($moduleName);
        } catch (\Exception $e) {
            Log::error('Error activating module '.$moduleName);
        }

        return redirect(route('admin.modules.index'));
    }

    /**
     * Verify and Remove the specified Module from storage.
     */
    public function destroy(int $id, Request $request): RedirectResponse
    {
        $delete = $this->moduleSvc->deleteModule($id, $request->all());
        if ($delete == true) {
            flash()->success('Module Deleted Successfully!');

            return redirect(route('admin.modules.index'));
        }
        flash()->error('Verification Failed!');

        return redirect(route('admin.modules.edit', $id));
    }
}
