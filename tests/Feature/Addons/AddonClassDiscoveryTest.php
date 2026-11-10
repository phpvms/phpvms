<?php

declare(strict_types=1);

use App\Addons\Models\AddonBootCache;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\AddonQuarantine;
use App\Addons\Support\BootCache;
use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Points\RenderingFlightActions;
use App\Models\Addon;
use App\Models\Pirep;
use App\Models\User;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Write a throwaway addon under a temp base and make its namespace loadable.
 * `{NS}` in a file is replaced with the addon's unique root namespace.
 *
 * @param  array<string, string>                            $files path under the addon's `app/` => PHP source
 * @return array{ns: string, dir: string, provider: string}
 */
function makeDiscoveryAddon(object $test, array $files): array
{
    $ns = 'DiscFx'.bin2hex(random_bytes(4));
    $dir = $test->base.'/Disc';
    $provider = $ns.'\\Providers\\DiscProvider';

    $files['Providers/DiscProvider.php'] ??= <<<'PHP'
<?php

namespace {NS}\Providers;

class DiscProvider extends \App\Contracts\Addons\AddonServiceProvider {}
PHP;

    foreach ($files as $path => $source) {
        File::ensureDirectoryExists(dirname($dir.'/app/'.$path));
        File::put($dir.'/app/'.$path, str_replace('{NS}', $ns, $source));
    }

    File::put($dir.'/addon.json', json_encode(addonManifest(['name' => 'Disc', 'alias' => 'disc', 'registry_id' => 'acme/disc', 'providers' => [$provider]])));
    File::put($dir.'/composer.json', json_encode(['autoload' => ['psr-4' => [$ns.'\\' => 'app/']]]));

    $loader = collect(spl_autoload_functions())->first(fn ($entry): bool => is_array($entry) && $entry[0] instanceof ClassLoader)[0];
    $loader->addPsr4($ns.'\\', $dir.'/app');

    return ['ns' => $ns, 'dir' => $dir, 'provider' => $provider];
}

function discoveryRenderListener(string $name = 'FlightRender', string $sub = ''): string
{
    $namespace = '{NS}\\Hooks'.($sub === '' ? '' : '\\'.$sub);

    return <<<PHP
<?php

namespace {$namespace};

use App\Contracts\Addons\Attributes\AddonListener;
use App\Features\Hooks\Listeners\RenderListener;
use App\Features\Hooks\Points\RenderingFlightActions;

#[AddonListener]
class {$name} extends RenderListener
{
    public function render(RenderingFlightActions \$hook): void {}
}
PHP;
}

function discoveryListeners(string $point): array
{
    return collect(Hooks::listenersFor($point))->pluck('listener')->all();
}

beforeEach(function (): void {
    Addon::query()->delete();
    $this->base = sys_get_temp_dir().'/discovery-'.uniqid();
    File::ensureDirectoryExists($this->base);
    Config::set('addons.paths.base', $this->base);
    app(BootCache::class)->delete();
});

afterEach(function (): void {
    app(BootCache::class)->delete();
    File::deleteDirectory($this->base);
});

it('registers event and hook listeners from subfolders', function (): void {
    $addon = makeDiscoveryAddon($this, [
        'Events/Ping.php'                 => "<?php\nnamespace {NS}\\Events;\nclass Ping {}",
        'Listeners/Pireps/OnPing.php'     => "<?php\nnamespace {NS}\\Listeners\\Pireps;\nuse App\\Contracts\\Addons\\Attributes\\AddonListener;\n#[AddonListener]\nclass OnPing { public function handle(\\{NS}\\Events\\Ping \$event): void {} }",
        'Hooks/Briefing/FlightRender.php' => discoveryRenderListener('FlightRender', 'Briefing'),
    ]);

    app()->register($addon['provider']);

    expect(Event::getListeners($addon['ns'].'\\Events\\Ping'))->not->toBeEmpty()
        ->and(discoveryListeners(RenderingFlightActions::class))->toContain($addon['ns'].'\\Hooks\\Briefing\\FlightRender');
});

it('skips a class whose name does not match its file, with a warning, and ignores function files', function (): void {
    Log::spy();
    $addon = makeDiscoveryAddon($this, [
        'Hooks/accepted.php' => str_replace('class FlightRender', 'class OnAccepted', discoveryRenderListener()),
        'Hooks/helpers.php'  => "<?php\nfunction discovery_helper_fixture(): void {}",
    ]);

    app()->register($addon['provider']);

    expect(discoveryListeners(RenderingFlightActions::class))->not->toContain($addon['ns'].'\\Hooks\\OnAccepted');
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'accepted.php') && str_contains($message, 'OnAccepted'))
        ->once();
    Log::shouldNotHaveReceived('error');
});

it('does not register a scanned listener without #[AddonListener]', function (): void {
    Log::spy();
    $addon = makeDiscoveryAddon($this, [
        'Hooks/Bare.php' => str_replace('#[AddonListener]', '', discoveryRenderListener('Bare')),
    ]);

    app()->register($addon['provider']);

    expect(discoveryListeners(RenderingFlightActions::class))->not->toContain($addon['ns'].'\\Hooks\\Bare');
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, $addon['ns'].'\\Hooks\\Bare') && str_contains($message, 'lacks #[AddonListener]'))
        ->once();
});

it('quarantines an addon whose listener breaks the hook directory rules', function (string $path, string $namespace, string $class, string $source, string $expected): void {
    Log::spy();
    $addon = makeDiscoveryAddon($this, [$path => "<?php\nnamespace {NS}\\{$namespace};\nuse App\\Contracts\\Addons\\Attributes\\AddonListener;\nuse App\\Features\\Hooks\\Listeners\\FilterListener;\nuse App\\Features\\Hooks\\Listeners\\GateListener;\nuse App\\Features\\Hooks\\Points\\PlacingBid;\nuse App\\Features\\Hooks\\Points\\RenderingFlightActions;\nuse App\\Events\\PirepAccepted;\n#[AddonListener]\n{$source}"]);

    app()->register($addon['provider']);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'quarantined') && str_contains($message, $addon['ns'].'\\'.$namespace.'\\'.$class) && str_contains($message, $expected))
        ->once();
})->with([
    'no base class'          => ['Hooks/Plain.php', 'Hooks', 'Plain', 'class Plain { public function handle(RenderingFlightActions $hook): void {} }', 'must extend'],
    'missing kind method'    => ['Hooks/NoCheck.php', 'Hooks', 'NoCheck', 'class NoCheck extends GateListener {}', 'defines no check() method'],
    'gate point on filter'   => ['Hooks/Mismatch.php', 'Hooks', 'Mismatch', 'class Mismatch extends FilterListener { public function filter(PlacingBid $hook): mixed { return null; } }', 'a gate hook point'],
    'render point on filter' => ['Hooks/OnRender.php', 'Hooks', 'OnRender', 'class OnRender extends FilterListener { public function filter(RenderingFlightActions $hook): mixed { return null; } }', 'a render hook point'],
    'hook left in Listeners' => ['Listeners/Left.php', 'Listeners', 'Left', 'class Left { public function handle(RenderingFlightActions $hook): void {} }', 'Move it to app/Hooks'],
    'event in Hooks'         => ['Hooks/OnAccepted.php', 'Hooks', 'OnAccepted', 'class OnAccepted { public function handle(PirepAccepted $event): void {} }', 'Move it to app/Listeners'],
    'extra required param'   => ['Hooks/TwoArgs.php', 'Hooks', 'TwoArgs', 'class TwoArgs extends GateListener { public function check(PlacingBid $hook, string $reason): void {} }', 'must take only the hook point'],
]);

it('quarantines instead of failing the boot when addon attributes are invalid', function (string $path, string $source): void {
    Log::spy();
    $addon = makeDiscoveryAddon($this, [$path => $source]);

    app()->register($addon['provider']);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'quarantined'))
        ->once();
})->with([
    'bad hook priority'    => ['Hooks/BadPriority.php', "<?php\nnamespace {NS}\\Hooks;\nuse App\\Contracts\\Addons\\Attributes\\AddonListener;\nuse App\\Features\\Hooks\\Attributes\\HookPriority;\nuse App\\Features\\Hooks\\Listeners\\GateListener;\nuse App\\Features\\Hooks\\Points\\PlacingBid;\n#[AddonListener]\n#[HookPriority('high')]\nclass BadPriority extends GateListener { public function check(PlacingBid \$hook): void {} }"],
    'policy with no model' => ['Policies/EmptyPolicy.php', "<?php\nnamespace {NS}\\Policies;\nuse App\\Contracts\\Addons\\Attributes\\PolicyFor;\n#[PolicyFor]\nclass EmptyPolicy {}"],
]);

it('skips onBoot once a broken listener has disabled the addon', function (): void {
    Log::spy();
    $this->mock(AddonQuarantine::class)->shouldReceive('handle')->once()->andReturnTrue();
    $addon = makeDiscoveryAddon($this, [
        'Providers/DiscProvider.php' => "<?php\nnamespace {NS}\\Providers;\nclass DiscProvider extends \\App\\Contracts\\Addons\\AddonServiceProvider { protected function onBoot(): void { config(['disc.booted' => true]); } }",
        'Hooks/NoCheck.php'          => "<?php\nnamespace {NS}\\Hooks;\nuse App\\Contracts\\Addons\\Attributes\\AddonListener;\nuse App\\Features\\Hooks\\Listeners\\GateListener;\n#[AddonListener]\nclass NoCheck extends GateListener {}",
    ]);

    app()->register($addon['provider']);

    expect(config('disc.booted'))->toBeNull();
});

it('registers policies for a core model, and for the addon model by name', function (): void {
    Log::spy();
    $addon = makeDiscoveryAddon($this, [
        'Models/Tour.php'                => "<?php\nnamespace {NS}\\Models;\nclass Tour extends \\Illuminate\\Database\\Eloquent\\Model {}",
        'Policies/TourPolicy.php'        => "<?php\nnamespace {NS}\\Policies;\nclass TourPolicy {}",
        'Policies/Nested/CorePolicy.php' => '<?php
namespace {NS}\Policies\Nested;
use App\Contracts\Addons\Attributes\PolicyFor;
#[PolicyFor(\\'.Pirep::class.'::class)]
class CorePolicy { public function view($user, $pirep): bool { return false; } }',
        'Policies/ReportPolicy.php'     => "<?php\nnamespace {NS}\\Policies;\nclass ReportPolicy {}",
        'Policies/TourPolicyHelper.php' => "<?php\nnamespace {NS}\\Policies;\nclass TourPolicyHelper {}",
    ]);

    app()->register($addon['provider']);

    $user = User::factory()->create();

    expect(Gate::getPolicyFor($addon['ns'].'\\Models\\Tour'))->toBeInstanceOf($addon['ns'].'\\Policies\\TourPolicy')
        ->and(Gate::getPolicyFor(Pirep::class))->toBeInstanceOf($addon['ns'].'\\Policies\\Nested\\CorePolicy')
        ->and(Gate::forUser($user)->allows('view', new Pirep()))->toBeFalse();
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, $addon['ns'].'\\Policies\\ReportPolicy') && str_contains($message, 'matches no model'))
        ->once();
    // Without the suffix the name must not fall back to Tour and replace TourPolicy.
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, $addon['ns'].'\\Policies\\TourPolicyHelper') && str_contains($message, 'matches no model'))
        ->once();
});

/**
 * Enable the throwaway addon and rebuild the boot cache from disk.
 */
function rebuildDiscoveryCache(): ?AddonBootCache
{
    Addon::factory()->create(['name' => 'Disc', 'registry_id' => 'acme/disc', 'enabled' => true]);
    app(AddonDiscoveryService::class)->rebuildCache();

    return app(BootCache::class)->enabled()->first();
}

it('saves the discovered classes in the rebuilt boot cache', function (): void {
    $addon = makeDiscoveryAddon($this, [
        'Listeners/Pireps/OnPing.php'     => "<?php\nnamespace {NS}\\Listeners\\Pireps;\nclass OnPing {}",
        'Hooks/Briefing/FlightRender.php' => discoveryRenderListener('FlightRender', 'Briefing'),
        'Policies/TourPolicy.php'         => "<?php\nnamespace {NS}\\Policies;\nclass TourPolicy {}",
    ]);

    expect(rebuildDiscoveryCache()->discovered)->toBe([
        'listeners' => [$addon['ns'].'\\Listeners\\Pireps\\OnPing'],
        'hooks'     => [$addon['ns'].'\\Hooks\\Briefing\\FlightRender'],
        'policies'  => [$addon['ns'].'\\Policies\\TourPolicy'],
    ]);
});

it('boots from the saved lists outside local and scans live in local or without them', function (string $environment, bool $keepLists, bool $seesLateClass): void {
    $addon = makeDiscoveryAddon($this, ['Hooks/FlightRender.php' => discoveryRenderListener()]);
    $entry = rebuildDiscoveryCache();

    if (!$keepLists) {
        app(BootCache::class)->write([AddonBootCache::fromArray([...$entry->toArray(), 'discovered' => null])]);
    }

    File::put($addon['dir'].'/app/Hooks/LateRender.php', str_replace('{NS}', $addon['ns'], discoveryRenderListener('LateRender')));
    app()->detectEnvironment(static fn (): string => $environment);

    app()->register($addon['provider']);

    $listeners = discoveryListeners(RenderingFlightActions::class);

    expect($listeners)->toContain($addon['ns'].'\\Hooks\\FlightRender')
        ->and(in_array($addon['ns'].'\\Hooks\\LateRender', $listeners, true))->toBe($seesLateClass);
})->with([
    'production with lists' => ['production', true, false],
    'local with lists'      => ['local', true, true],
    'production, no lists'  => ['production', false, true],
]);
