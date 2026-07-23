<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Kvp;
use App\Services\Installer\SeederService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    Addon::query()->delete();

    $this->base = sys_get_temp_dir().'/addon-seed-autoload-'.uniqid('', true);
    $this->addonDir = $this->base.'/seedfixture';
    File::ensureDirectoryExists($this->addonDir.'/app/Models');
    File::ensureDirectoryExists($this->addonDir.'/database/seeders');
    Config::set('addons.paths.base', $this->base);

    // module.json + composer.json: PSR-4 Modules\SeedFixture\ => app/. This path
    // is NOT in Composer's autoload map, so the class below is only loadable if
    // SeederService registers the addon's namespace before running the seeder.
    File::put($this->addonDir.'/module.json', json_encode(['name' => 'SeedFixture', 'providers' => []]));
    File::put($this->addonDir.'/composer.json', json_encode([
        'autoload' => ['psr-4' => ['Modules\\SeedFixture\\' => 'app/']],
    ]));

    // A model that the seeder references — mirrors VMSAcars' Rule model.
    File::put($this->addonDir.'/app/Models/Widget.php', <<<'PHP'
        <?php

        namespace Modules\SeedFixture\Models;

        class Widget
        {
            public string $token = 'widget-loaded';
        }
        PHP);

    // The seeder references its own addon model. Before the fix this threw
    // "Class Modules\SeedFixture\Models\Widget not found".
    File::put($this->addonDir.'/database/seeders/SeedFixtureDatabaseSeeder.php', <<<'PHP'
        <?php

        namespace Modules\SeedFixture\Database\Seeders;

        use App\Models\Kvp;
        use Illuminate\Database\Seeder;
        use Modules\SeedFixture\Models\Widget;

        class SeedFixtureDatabaseSeeder extends Seeder
        {
            public function run(): void
            {
                Kvp::updateOrCreate(['key' => 'seedfixture_ran'], ['value' => (new Widget())->token]);
            }
        }
        PHP);

    Addon::factory()->create([
        'name'      => 'SeedFixture',
        'namespace' => 'Modules\\SeedFixture',
        'path'      => $this->addonDir,
        'enabled'   => true,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->base);
});

it("registers an enabled addon's namespace so its seeder can load the addon's own classes", function (): void {
    app(SeederService::class)->seedAddons();

    // The seeder ran to completion (it could only construct Widget if the addon
    // PSR-4 namespace was registered first).
    expect(Kvp::where('key', 'seedfixture_ran')->value('value'))->toBe('widget-loaded')
        // ...and a per-addon seed marker was written, so it won't re-run forever.
        ->and(Kvp::where('key', 'like', 'addon_seeded:SeedFixture:%')->exists())->toBeTrue();
});
