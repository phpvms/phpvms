<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use Illuminate\View\View;
use Nwidart\Modules\Facades\Module;

class CreditsController extends Controller
{
    public function index(): View
    {
        $all_modules = Module::all();
        $v7_defaults = ['Awards', 'Vacentral', 'Sample'];
        $modules = collect();

        foreach ($all_modules as $key => $module) {
            if (in_array($key, $v7_defaults)) {
                continue;
            }

            $module_details = $this->ReadModuleJson($key);

            if ($module_details) {
                $modules->push($module_details);
            }
        }

        return view('credits', [
            'modules' => $modules,
        ]);
    }

    // Read module.json file
    // Return laravel collection
    public function ReadModuleJson($module_name = null)
    {
        $file = isset($module_name) ? base_path().'/modules/'.$module_name.'/module.json' : null;

        if (!is_file($file)) {
            return null;
        }

        $contents = json_decode(file_get_contents($file));

        $details = new \stdClass();
        $details->name = isset($contents->name) ? $contents->name : $module_name;
        $details->description = isset($contents->description) ? $contents->description : null;
        $details->version = isset($contents->version) ? $contents->version : null;
        $details->readme_url = isset($contents->readme_url) ? $contents->readme_url : null;
        $details->license_url = isset($contents->license_url) ? $contents->license_url : null;
        $details->attribution = isset($contents->attribution) ? $contents->attribution : null;
        $details->active = Module::isEnabled($contents->name);

        return $details;
    }
}
