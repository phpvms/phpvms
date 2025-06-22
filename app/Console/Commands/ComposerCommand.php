<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use Illuminate\Support\Facades\Artisan;

class ComposerCommand extends Command
{
    protected $signature = 'phpvms:composer {cmd}';

    protected $description = 'Composer related tasks';

    /**
     * Run composer update related commands
     */
    public function handle(): void
    {
        match (trim($this->argument('cmd'))) {
            'post-update' => $this->postUpdate(),
            default       => $this->error('Command exists'),
        };
    }

    /**
     * Any composer post update tasks
     */
    protected function postUpdate(): void
    {
        /* @noinspection NestedPositiveIfStatementsInspection */
        if (config('app.env') === 'dev' && class_exists(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class)) {
            Artisan::call('ide-helper:generate');
            Artisan::call('ide-helper:meta');
        }
    }
}
