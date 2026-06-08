<?php

namespace App\Http\Controllers\Frontend;

use App\Addons\AddonRegistry;
use App\Contracts\Controller;
use Illuminate\View\View;
use stdClass;

class CreditsController extends Controller
{
    public function index(): View
    {
        $all_modules = app(AddonRegistry::class)->all()->keyBy(fn ($addon): string => $addon->getName());
        $v7_defaults = ['Awards', 'Vacentral', 'Sample'];
        $modules = collect();

        foreach ($all_modules as $key => $module) {
            if (in_array($key, $v7_defaults)) {
                continue;
            }

            $module_details = $this->ReadModuleJson($key);

            if ($module_details instanceof stdClass) {
                $modules->push($module_details);
            }
        }

        return view('credits', [
            'modules' => $modules,
        ]);
    }

    // Read module.json file
    // Return laravel collection
    public function ReadModuleJson($module_name = null): ?stdClass
    {
        $file = isset($module_name) ? base_path().'/modules/'.$module_name.'/module.json' : null;

        if (!is_file($file)) {
            return null;
        }

        $contents = json_decode(file_get_contents($file));

        $details = new stdClass();
        $details->name = $contents->name ?? $module_name;
        $details->description = $contents->description ?? null;
        $details->version = $contents->version ?? null;
        $details->readme_url = $contents->readme_url ?? null;
        $details->license_url = $contents->license_url ?? null;
        $details->attribution = $contents->attribution ?? null;
        $details->active = (bool) app(AddonRegistry::class)->find($contents->name)?->isEnabled();

        return $details;
    }
}
