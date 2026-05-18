<?php

namespace Tests;

use App\Services\ModuleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module as NwidartModuleFacade;

class ModuleServiceCacheTest extends TestCase
{
    public function test_add_module_makes_freshly_deposited_module_findable(): void
    {
        $name = 'PhpvmsCacheBustingFixtureModule';
        $moduleDir = base_path('modules/'.$name);
        $cacheKey = config('modules.cache.key');
        $cacheStore = Cache::store(config('modules.cache.driver'));

        if (File::exists($moduleDir)) {
            File::deleteDirectory($moduleDir);
        }

        try {
            $cacheStore->put($cacheKey, [
                'Sample' => ['path' => base_path('modules/Sample')],
            ], 3600);

            File::makeDirectory($moduleDir, 0777, true);
            file_put_contents($moduleDir.'/module.json', json_encode([
                'name'        => $name,
                'alias'       => strtolower($name),
                'description' => '',
                'keywords'    => [],
                'active'      => 1,
                'order'       => 0,
                'providers'   => [],
                'aliases'     => (object) [],
                'files'       => [],
                'requires'    => [],
            ]));

            $this->assertNull(
                NwidartModuleFacade::find($name),
                'Test premise broken: stale cache should hide the new module from Nwidart::find.'
            );

            app(ModuleService::class)->addModule($name);

            $this->assertNotNull(
                NwidartModuleFacade::find($name),
                'addModule must invalidate the phpvms-modules cache so the just-deposited module is findable by the very next module:migrate.'
            );
        } finally {
            if (File::exists($moduleDir)) {
                File::deleteDirectory($moduleDir);
            }
        }
    }
}
