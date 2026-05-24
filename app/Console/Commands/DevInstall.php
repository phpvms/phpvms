<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;

#[AsCommand(name: 'phpvms:dev-install', description: 'Create a fresh development install and run the sample migration')]
#[Signature('phpvms:dev-install 
                            {--reset-db : Completely drop and recreate the database}
                            {--force : Force the operation to run without prompts}')]
class DevInstall extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->isProduction() && !$this->option('force')) {
            $confirmed = confirm(
                label: 'You are in a PRODUCTION environment! This will destroy your database. Are you absolutely sure?',
                default: false,
                hint: 'Run with --force to bypass this prompt.'
            );

            if (!$confirmed) {
                $this->components->warn('Developer installation cancelled.');

                return self::FAILURE;
            }
        }

        app()->boot();

        $this->components->info('Starting phpVMS Developer Installation...');

        // We pass `--force` to the inner command so it doesn't prompt the user a second time
        $this->components->info('Step 1: Setting up the database');
        $this->call('db:create', [
            '--reset' => $this->option('reset-db'),
            '--force' => true,
        ]);

        $this->components->info('Step 2: Running migrations and seeders');
        $this->call('migrate:fresh', [
            '--seed'  => true,
            '--force' => true,
        ]);

        $this->components->info('Developer installation completed successfully!');

        return self::SUCCESS;
    }
}
