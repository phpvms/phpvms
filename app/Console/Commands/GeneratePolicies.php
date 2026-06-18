<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PermissionRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'permission:generate-policies', description: 'Generate thin policies for Filament resource models')]
#[Description('Generate thin policies for Filament resource models')]
#[Signature('permission:generate-policies {--force : Overwrite existing policies with the generated stub}')]
class GeneratePolicies extends Command
{
    public function handle(PermissionRegistry $registry): int
    {
        $created = 0;
        $skipped = 0;

        foreach ($registry->resourceModels() as $model) {
            $policyClass = $this->policyClassFor($model);
            $path = $this->pathForClass($policyClass);

            if ($path === null) {
                $this->warn(sprintf('Skipped %s: unsupported namespace', $model));
                $skipped++;

                continue;
            }

            if (is_file($path) && !$this->option('force')) {
                $skipped++;

                continue;
            }

            if (!is_dir($dir = dirname($path))) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($path, $this->stub($policyClass, Str::kebab(class_basename($model))));
            $this->info('Generated '.$policyClass);
            $created++;
        }

        $this->info(sprintf('Policies generated: %d, skipped: %d', $created, $skipped));

        return self::SUCCESS;
    }

    /**
     * The target policy class for a model, mirroring the AppServiceProvider
     * resolver (Models => Policies\Filament).
     */
    protected function policyClassFor(string $model): string
    {
        return str_replace('\\Models\\', '\\Policies\\Filament\\', $model).'Policy';
    }

    /**
     * Map a fully-qualified class to its file path. Supports the App\ and
     * Modules\Foo\ PSR-4 roots (see root composer.json).
     */
    protected function pathForClass(string $class): ?string
    {
        if (str_starts_with($class, 'App\\')) {
            return app_path(str_replace('\\', '/', Str::after($class, 'App\\')).'.php');
        }

        if (str_starts_with($class, 'Modules\\')) {
            return base_path('modules/'.str_replace('\\', '/', Str::after($class, 'Modules\\')).'.php');
        }

        return null;
    }

    protected function stub(string $policyClass, string $subject): string
    {
        $namespace = Str::beforeLast($policyClass, '\\');
        $className = class_basename($policyClass);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use App\\Policies\\BasePolicy;

        class {$className} extends BasePolicy
        {
            protected string \$subject = '{$subject}';
        }

        PHP;
    }
}
