<?php

namespace App\Services;

use App\Contracts\Service;
use App\Exceptions\ModuleExistsException;
use App\Exceptions\ModuleInstallationError;
use App\Exceptions\ModuleInvalidFileType;
use App\Models\Module;
use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laracasts\Flash\FlashNotifier;
use Madnest\Madzipper\Madzipper;
use Nwidart\Modules\Json;
use PharData;

class ModuleService extends Service
{
    protected static array $adminLinks = [];

    /**
     * @var array 0 == logged out, 1 == logged in
     */
    protected static array $frontendLinks = [
        0 => [],
        1 => [],
    ];

    /**
     * Add a module link in the frontend
     */
    public function addFrontendLink(string $title, string $url, string $icon = 'bi bi-people', bool $logged_in = true)
    {
        self::$frontendLinks[$logged_in][] = [
            'title' => $title,
            'url'   => $url,
            'icon'  => $icon,
        ];
    }

    /**
     * Get all of the frontend links
     */
    public function getFrontendLinks(mixed $logged_in): array
    {
        return self::$frontendLinks[$logged_in];
    }

    /**
     * Add a module link in the admin panel
     */
    public function addAdminLink(string $title, string $url, string $icon = 'bi bi-people')
    {
        self::$adminLinks[] = [
            'title' => $title,
            'url'   => $url,
            'icon'  => $icon,
        ];
    }

    /**
     * Get all of the module links in the admin panel
     */
    public function getAdminLinks(): array
    {
        return self::$adminLinks;
    }

    /**
     * Get all of the modules from database but make sure they also exist on disk
     *
     * @return object
     */
    public function getAllModules(): array
    {
        $modules = [];
        $moduleList = Module::all();

        /** @var Module $module */
        foreach ($moduleList as $module) {
            /** @var \Nwidart\Modules\Module $moduleInstance */
            $moduleInstance = \Nwidart\Modules\Facades\Module::find($module->name);
            if (empty($moduleInstance)) {
                continue;
            }

            if (file_exists($moduleInstance->getPath())) {
                $modules[] = $module;
            }
        }

        return $modules;
    }

    /**
     * Determine if a module is active - also checks that the module exists properly
     */
    public function isModuleActive(string $name): bool
    {
        $module = Module::where('name', $name)->first();
        if (empty($module)) {
            return false;
        }

        /** @var \Nwidart\Modules\Module $moduleInstance */
        $moduleInstance = \Nwidart\Modules\Facades\Module::find($module->name);
        if (empty($moduleInstance)) {
            return false;
        }

        if (!file_exists($moduleInstance->getPath())) {
            return false;
        }

        return $moduleInstance->isEnabled();
    }

    /**
     * Get Module Information from Database.
     */
    public function getModule($id): Module
    {
        return Module::find($id);
    }

    /**
     * Adding installed module to the database
     */
    public function addModule($module_name): bool
    {
        /* Check if module already exists */
        $module = Module::where('name', $module_name);
        if (!$module->exists()) {
            Module::create([
                'name'    => $module_name,
                'enabled' => 1,
            ]);

            try {
                Artisan::call('module:migrate', ['module' => $module_name, '--force' => true]);
            } catch (Exception $e) {
                Log::error('Error running migration for '.$module_name.'; error='.$e);
            }

            return true;
        }

        return false;
    }

    /**
     * User's uploaded file is passed into this method
     * to install module in the Storage.
     */
    public function installModule(UploadedFile $file): FlashNotifier
    {
        $file_ext = strtolower($file->getClientOriginalExtension());
        $allowed_extensions = ['zip', 'tar', 'gz'];

        if (!in_array($file_ext, $allowed_extensions, true)) {
            throw new ModuleInvalidFileType();
        }

        $new_dir = rand();
        File::makeDirectory(
            storage_path('app/tmp/modules/'.$new_dir),
            0777,
            true
        );

        $temp_ext_folder = storage_path('app/tmp/modules/'.$new_dir);
        $temp = storage_path('app/tmp/modules/'.$new_dir);

        $zipper = null;

        if ($file_ext === 'tar' || $file_ext === 'gz') {
            $zipper = new PharData($file);
            $zipper->decompress();
        }

        if ($file_ext === 'zip') {
            $madZipper = new Madzipper();

            try {
                $zipper = $madZipper->make($file);
            } catch (Exception $e) {
                throw new ModuleInstallationError();
            }
        }

        try {
            $zipper->extractTo($temp);
        } catch (Exception $e) {
            throw new ModuleInstallationError();
        }

        if (!File::exists($temp.'/module.json')) {
            $directories = Storage::directories('tmp/modules/'.$new_dir);
            $temp = storage_path('app/'.$directories[0]);
        }

        $json_file = $temp.'/module.json';

        if (File::exists($json_file)) {
            $json = json_decode(file_get_contents($json_file), true);
            $module = $json['name'];
        } else {
            File::deleteDirectory($temp_ext_folder);

            return flash()->error('Module Structure Not Correct!');
        }

        if (!$module) {
            File::deleteDirectory($temp_ext_folder);

            return flash()->error('Not a Valid Module File.');
        }

        $toCopy = base_path().'/modules/'.$module;

        if (File::exists($toCopy)) {
            File::deleteDirectory($temp_ext_folder);

            throw new ModuleExistsException($module);
        }

        File::moveDirectory($temp, $toCopy);
        File::deleteDirectory($temp_ext_folder);

        try {
            $this->addModule($module);
        } catch (Exception $e) {
            throw new ModuleExistsException($module);
        }

        Artisan::call('config:cache');
        Artisan::call('module:migrate', ['module' => $module, '--force' => true]);

        return flash()->success('Module Installed');
    }

    /**
     * Update module with the status passed by user.
     */
    public function updateModule($id, $status): bool
    {
        $module = Module::find($id);

        $cache = config('cache.keys.MODULES');
        Cache::forget($cache['key']);

        $module->update([
            'enabled' => $status,
        ]);

        if ($status === true) {
            Artisan::call('module:migrate', ['module' => $module->name, '--force' => true]);
        }

        return true;
    }

    /**
     * Delete Module from the Storage & Database.
     */
    public function deleteModule($id, $data): bool
    {
        $module = Module::find($id);
        if ($data['verify'] === strtoupper($module->name)) {
            try {
                $module->delete();
            } catch (Exception $e) {
                Log::emergency('Cannot Delete Module!');
            }
            $moduleDir = base_path().'/modules/'.$module->name;

            try {
                File::deleteDirectory($moduleDir);
            } catch (Exception $e) {
                Log::info('Folder Deleted Manually for Module : '.$module->name);

                return true;
            }

            return true;
        }

        return false;
    }

    /**
     * Get & scan all modules.
     *
     * @return array
     */
    public function scan()
    {
        $modules_path = base_path('modules/*');
        $path = Str::endsWith($modules_path, '/*') ? $modules_path : Str::finish($modules_path, '/*');

        $modules = [];

        $manifests = (new Filesystem())->glob("{$path}/module.json");

        is_array($manifests) || $manifests = [];

        foreach ($manifests as $manifest) {
            $name = Json::make($manifest)->get('name');
            $module = Module::where('name', $name);
            if (!$module->exists()) {
                array_push($modules, $name);
            }
        }

        return $modules;
    }
}
