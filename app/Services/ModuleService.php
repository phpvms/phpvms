<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Exceptions\ModuleExistsException;
use App\Exceptions\ModuleInstallationError;
use App\Exceptions\ModuleInvalidFileType;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laracasts\Flash\FlashNotifier;
// use Madnest\Madzipper\Madzipper;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Json;
use PharData;

class ModuleService extends Service
{
    /**
     * Module-registered admin nav links. Populated once per worker via each
     * module's boot()->registerLinks() call; not a per-request accumulator.
     */
    protected array $adminLinks = [];

    /**
     * @var array 0 == logged out, 1 == logged in
     */
    protected array $frontendLinks = [
        0 => [],
        1 => [],
    ];

    /**
     * Add a module link in the frontend
     */
    public function addFrontendLink(string $title, string $url, string $icon = 'bi bi-people', bool $logged_in = true): void
    {
        $this->frontendLinks[$logged_in][] = [
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
        return $this->frontendLinks[$logged_in];
    }

    /**
     * Add a module link in the admin panel
     */
    public function addAdminLink(string $title, string $url, string $icon = 'bi bi-people'): void
    {
        $this->adminLinks[] = [
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
        return $this->adminLinks;
    }

    /**
     * Get all of the modules from database but make sure they also exist on disk
     */
    public function getAllModules(): array
    {
        return Module::all();
    }

    /**
     * Determine if a module is active - also checks that the module exists properly
     */
    public function isModuleActive(string $name): bool
    {
        /** @var ?\Nwidart\Modules\Module $module */
        $module = Module::find($name);

        if (!$module) {
            return false;
        }

        if (!file_exists($module->getPath())) {
            return false;
        }

        return $module->isEnabled();
    }

    /**
     * User's uploaded file is passed into this method
     * to install module in the Storage.
     *
     * Will be re added in the future (new zip library + marketplace implementation)
     */
    /*public function installModule(UploadedFile $file): FlashNotifier
    {
        $file_ext = strtolower($file->getClientOriginalExtension());
        $allowed_extensions = ['zip', 'tar', 'gz'];

        if (!in_array($file_ext, $allowed_extensions, true)) {
            throw new ModuleInvalidFileType();
        }

        $new_dir = random_int(PHP_INT_MIN, PHP_INT_MAX);
        File::makeDirectory(
            storage_path('app/tmp/modules/'.$new_dir),
            0777,
            true
        );

        $temp_ext_folder = storage_path('app/tmp/modules/'.$new_dir);
        $temp = storage_path('app/tmp/modules/'.$new_dir);

        $zipper = null;

        if ($file_ext === 'tar' || $file_ext === 'gz') {
            $zipper = new PharData($file->getRealPath());
            $zipper->decompress();
        }

        /*if ($file_ext === 'zip') {
            $madZipper = new Madzipper();

            try {
                $zipper = $madZipper->make($file->getRealPath());
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
            $name = $json['name'];
        } else {
            File::deleteDirectory($temp_ext_folder);

            return flash()->error('Module Structure Not Correct!');
        }

        if (!$name) {
            File::deleteDirectory($temp_ext_folder);

            return flash()->error('Not a Valid Module File.');
        }

        $toCopy = base_path().'/modules/'.$name;

        if (File::exists($toCopy)) {
            File::deleteDirectory($temp_ext_folder);

            throw new ModuleExistsException($name);
        }

        File::moveDirectory($temp, $toCopy);
        File::deleteDirectory($temp_ext_folder);

        try {
            $module = Module::find($name);
            $module->enable();
        } catch (Exception $e) {
            throw new ModuleExistsException($name);
        }

        Artisan::call('config:cache');
        Artisan::call('module:migrate', ['module' => $name, '--force' => true]);

        return flash()->success('Module Installed');
    }*/

    /**
     * Update module with the status passed by user.
     */
    public function updateModule(string $name, bool $enabled): void
    {
        /** @var ?\Nwidart\Modules\Module $module */
        $module = Module::find($name);

        if (!$module) {
            return;
        }

        // setActive() flips the enabled flag, persists to DB, and regenerates the boot cache
        // (via ModuleShim::setActive() → PrimeService::run()). The per-module migrate command
        // (module:migrate) belonged to nwidart and no longer exists. Addon migration execution
        // is owned by the standard `php artisan migrate` path (Phase 5 lifecycle).
        $module->setActive($enabled);

        if (file_exists(base_path('bootstrap/cache/modules.php'))) {
            unlink(base_path('bootstrap/cache/modules.php'));
        }
    }

    /**
     * Delete Module from the Storage & Database.
     */
    public function deleteModule(string $name): void
    {
        /** @var ?\Nwidart\Modules\Module $module */
        $module = Module::find($name);

        if (!$module) {
            return;
        }

        $module->delete();

        if (file_exists(base_path('bootstrap/cache/modules.php'))) {
            unlink(base_path('bootstrap/cache/modules.php'));
        }
    }
}
