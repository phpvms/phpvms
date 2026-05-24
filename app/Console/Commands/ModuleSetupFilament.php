<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\Concerns\PromptsForMissingInput;
use Illuminate\Contracts\Console\PromptsForMissingInput as PromptsForMissingInputContract;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nwidart\Modules\Module;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'module:setup-filament', description: 'Add Filament Support to a Module')]
#[Signature('module:setup-filament {module : The name of the module}')]
class ModuleSetupFilament extends Command implements PromptsForMissingInputContract
{
    use PromptsForMissingInput;

    protected string $basePath = 'Providers/Filament';

    protected string $className = 'AdminPanelProvider';

    protected string $panelStub = 'resources/stubs/modules/admin-panel-provider.stub';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $moduleName = $this->argument('module');

        /** @var Module|null $module */
        $module = app('modules')->find($moduleName);

        if (!$module) {
            $this->components->error(sprintf("Module [%s] not found. Are you sure it's installed and enabled?", $moduleName));

            return self::FAILURE;
        }

        $this->components->info('Setting up Filament for module: '.$module->getName());

        $providerPath = str($module->getExtraPath(sprintf('%s/%s', $this->basePath, $this->className)))
            ->replace('\\', '/')
            ->append('.php')
            ->toString();

        $namespace = Str::of($this->basePath)
            ->replace('/', '\\')
            ->prepend('\\')
            ->prepend($this->getModuleNamespace($module))
            ->toString();

        $providerClass = sprintf('%s\%s', $namespace, $this->className);

        // Step 1: Scaffold the Provider
        $stubSuccess = false;

        $this->components->task('Creating Admin Panel Provider', function () use ($module, $providerPath, &$stubSuccess): bool {
            $stubSuccess = $this->copyPanelStubToApp($module, $providerPath);

            // Return the boolean so the task component shows a Green Check or Red X
            return $stubSuccess;
        });

        if (!$stubSuccess) {
            return self::FAILURE;
        }

        // Step 2: Register in module.json
        $this->components->task('Registering provider in module.json', function () use ($module, $providerClass): void {
            $this->updateJsonArray(
                module_path($module->getName(), 'module.json'),
                'providers',
                $providerClass
            );
        });

        // Step 3: Register in composer.json
        $this->components->task('Registering provider in composer.json', function () use ($module, $providerClass): void {
            $this->updateComposerJson($module, $providerClass);
        });

        $this->components->info(sprintf('Module [%s] is now ready for Filament!', $module->getName()));

        return self::SUCCESS;
    }

    /**
     * Copy the stub file and replace placeholders.
     */
    protected function copyPanelStubToApp(Module $module, string $targetPath): bool
    {
        $panelStubPath = base_path($this->panelStub);

        if (!File::exists($panelStubPath)) {
            $this->components->error(sprintf('The panel stub file does not exist at [%s]', $panelStubPath));

            return false;
        }

        $stub = str(File::get($panelStubPath));

        $replacements = [
            'STUDLY_NAME'      => $module->getStudlyName(),
            'LOWER_NAME'       => $module->getLowerName(),
            'MODULE_NAMESPACE' => app('modules')->config('namespace'),
        ];

        foreach ($replacements as $key => $replacement) {
            $stub = $stub->replace([sprintf('{{ %s }}', $key), '$'.$key.'$'], $replacement);
        }

        File::ensureDirectoryExists(dirname($targetPath));
        File::put($targetPath, (string) $stub);

        return true;
    }

    /**
     * Helper to safely append a value to a JSON file array (like module.json).
     */
    protected function updateJsonArray(string $path, string $key, string $value): void
    {
        if (!File::exists($path)) {
            return;
        }

        // Casting to array fixes the PHPStan "not nullable" warning
        // while safely handling empty files.
        $data = (array) File::json($path);
        $items = collect($data[$key] ?? []);

        if (!$items->contains($value)) {
            $data[$key] = $items->push($value)->values()->toArray();
            File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Helper to safely append the provider to the composer.json extra.laravel.providers array.
     */
    protected function updateComposerJson(Module $module, string $providerClass): void
    {
        $path = module_path($module->getName(), 'composer.json');

        if (!File::exists($path)) {
            return;
        }

        // Casting to array fixes the PHPStan "not nullable" warning
        $data = (array) File::json($path);
        $providers = collect(data_get($data, 'extra.laravel.providers', []));

        if (!$providers->contains($providerClass)) {
            $providers->push($providerClass);
            data_set($data, 'extra.laravel.providers', $providers->values()->toArray());

            File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Get the root namespace for the module.
     */
    protected function getModuleNamespace(Module $module): string
    {
        return app('modules')->config('namespace').'\\'.$module->getName();
    }
}
