<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Concerns\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Nwidart\Modules\Module;

class ModuleSetupFilament extends Command implements \Illuminate\Contracts\Console\PromptsForMissingInput
{
    use PromptsForMissingInput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:setup-filament {module}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add Filament Support to a Module';

    protected ?Module $module = null;

    private string $basePath = 'Providers/Filament';

    private string $className = 'AdminPanelProvider';

    private string $panelStub = 'resources/stubs/modules/admin-panel-provider.stub';

    public function handle()
    {
        $moduleName = $this->argument('module');
        $this->module = app('modules')->find($moduleName);

        if (!$this->module instanceof Module) {
            $this->fail("Module {$moduleName} not found, are you sure it's installed and enabled?");
        }

        // The provider file path
        $path = str($this->module->getExtraPath("{$this->basePath}/{$this->className}"))
            ->replace('\\', '/')
            ->append('.php')->toString();

        $namespace = Str::of($this->basePath)
            ->replace('/', '\\')
            ->prepend('\\')
            ->prepend($this->getModuleNamespace());

        $this->copyPanelStubToApp($path, [
            'STUDLY_NAME'      => $this->module->getStudlyName(),
            'LOWER_NAME'       => $this->module->getLowerName(),
            'MODULE_NAMESPACE' => $this->laravel['modules']->config('namespace'),
        ]);

        $this->info("The {$this->className} has been created at {$path}");

        $this->info("Adding {$this->className} to module.json and composer.json");

        $provider = "{$namespace}\\{$this->className}";

        $moduleJson = json_decode($this->readFile(module_path($this->module->getName(), 'module.json')), true);
        $providers = collect($moduleJson['providers']);

        if (!$providers->contains($provider)) {
            $moduleJson['providers'][] = $provider;
            $this->writeFile(module_path($this->module->getName(), 'module.json'), json_encode($moduleJson, JSON_PRETTY_PRINT));
        }

        $composerJson = json_decode($this->readFile(module_path($this->module->getName(), 'composer.json')), true);
        $providers = collect($composerJson['extra']['laravel']['providers']);

        if (!$providers->contains($provider)) {
            $composerJson['extra']['laravel']['providers'][] = $provider;
            $this->writeFile(module_path($this->module->getName(), 'composer.json'), json_encode($composerJson, JSON_PRETTY_PRINT));
        }

        $this->info("Module {$this->module->getName()} is now ready for Filament!");
    }

    protected function copyPanelStubToApp(string $targetPath, ?array $replacements = []): void
    {
        $filesystem = app(Filesystem::class);

        $panelStubPath = base_path($this->panelStub);

        if (!$this->fileExists($panelStubPath)) {
            $this->fail("The panel stub file does not exist at {$panelStubPath}");
        }

        $stub = str($filesystem->get($panelStubPath));

        foreach ($replacements as $key => $replacement) {
            $stub = $stub->replace("{{ {$key} }}", $replacement);
            $stub = $stub->replace('$'.$key.'$', $replacement);
        }

        $stub = (string) $stub;

        $this->writeFile($targetPath, $stub);
    }

    protected function fileExists(string $path): bool
    {
        $filesystem = app(Filesystem::class);

        return $filesystem->exists($path);
    }

    protected function readFile(string $path): string
    {
        $filesystem = app(Filesystem::class);

        if (!$this->fileExists($path)) {
            $this->fail("The file does not exist at {$path}");
        }

        return $filesystem->get($path);
    }

    protected function writeFile(string $path, string $contents): void
    {
        $filesystem = app(Filesystem::class);

        $filesystem->ensureDirectoryExists(
            pathinfo($path, PATHINFO_DIRNAME),
        );

        $filesystem->put($path, $contents);
    }

    protected function getModuleNamespace(): string
    {
        return $this->laravel['modules']->config('namespace').'\\'.$this->module->getName();
    }
}
