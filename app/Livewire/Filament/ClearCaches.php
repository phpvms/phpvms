<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;
use Livewire\Component;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

final class ClearCaches extends Component
{
    /**
     * Clear all application caches by running `optimize:clear` (plus
     * supporting cache:clear and filament:optimize-clear, and removing
     * the cached themes/modules files).
     *
     * Mirrors the logic in App\Filament\Pages\Maintenance::clearCache so
     * the topbar shortcut and the maintenance page behave identically.
     */
    public function clear(): void
    {
        try {
            $module_cache_files = base_path().'/bootstrap/cache/*_module.php';
            foreach (File::glob($module_cache_files) as $file) {
                $deleted = File::delete($file) ? 'Module cache file deleted' : 'Module cache file not found!';
                Log::debug($deleted.' | '.$file);
            }

            $calls = [
                'cache:clear',
                'optimize:clear',
                'filament:optimize-clear',
            ];

            foreach ($calls as $call) {
                Process::env(['APP_RUNNING_IN_CONSOLE' => true])
                    ->run([$this->getPhpBinary(), base_path('artisan'), $call])->throw();
            }

            Notification::make()
                ->title(__('filament.maintenance_cache_cleared'))
                ->success()
                ->send();
        } catch (Throwable $e) {
            Log::error('ClearCaches plugin failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title(__('filament.maintenance_cache_clear_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render(): View
    {
        return view('livewire.filament.clear-caches');
    }

    private function getPhpBinary(): string
    {
        $finder = new PhpExecutableFinder();
        $php_path = $finder->find(false);
        $php = str_replace('-fpm', '', (string) $php_path);

        if (str_contains($php, '-cgi')) {
            $php .= ' -d register_argc_argv=On';
        }

        return $php;
    }
}
