<?php

namespace App\Http\Controllers\Frontend;

use App\Addons\AddonRegistry;
use App\Contracts\Controller;
use App\Models\Addon;
use Illuminate\View\View;
use stdClass;

class CreditsController extends Controller
{
    public function __construct(
        private readonly AddonRegistry $addonRegistry,
    ) {}

    public function index(): View
    {
        $all_modules = $this->addonRegistry->all()->keyBy(fn ($addon): string => $addon->getName());
        $v7_defaults = ['Awards', 'Vacentral', 'Sample'];
        $modules = collect();

        foreach ($all_modules as $key => $module) {
            if (in_array($key, $v7_defaults)) {
                continue;
            }

            $module_details = $this->ReadModuleJson($key, $module);

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
    public function ReadModuleJson($module_name = null, ?Addon $module = null): ?stdClass
    {
        $file = isset($module_name) ? base_path().'/modules/'.$module_name.'/module.json' : null;

        if (!is_file($file)) {
            return null;
        }

        $contents = json_decode(file_get_contents($file)) ?? new stdClass();

        $details = new stdClass();
        $details->name = $contents->name ?? $module_name;
        $details->description = $contents->description ?? null;
        $details->version = $contents->version ?? null;
        $details->readme_url = $contents->readme_url ?? null;
        $details->license_url = $contents->license_url ?? null;
        $details->attribution = $contents->attribution ?? null;
        // Use the already-loaded addon when available to avoid re-querying the
        // full table on every iteration; fall back to a lookup for direct calls.
        $details->active = (bool) ($module?->isEnabled()
            ?? $this->addonRegistry->find($contents->name ?? $module_name)?->isEnabled());

        return $details;
    }
}
