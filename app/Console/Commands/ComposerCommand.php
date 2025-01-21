<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use Illuminate\Support\Facades\Artisan;

class ComposerCommand extends Command
{
    protected $signature = 'phpvms:composer {cmd}';

    protected $description = 'Composer related tasks';

    /**
     * Run composer update related commands.
     */
    public function handle()
    {
        switch (trim($this->argument('cmd'))) {
            case 'post-update':
                $this->postUpdate();
                break;
            default:
                $this->error('Command exists');
        }
    }

    /**
     * Any composer post update tasks.
     */
    protected function postUpdate(): void
    {
        if (config('app.env') === 'dev') {
            /* @noinspection NestedPositiveIfStatementsInspection */
            if (class_exists(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class)) {
                Artisan::call('ide-helper:generate');
                Artisan::call('ide-helper:meta');
            }
        }
    }
}
