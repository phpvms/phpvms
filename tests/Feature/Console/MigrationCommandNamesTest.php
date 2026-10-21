<?php

declare(strict_types=1);

use App\Console\Commands\DataMigrationInstallCommand;
use Illuminate\Database\Console\Migrations\InstallCommand;
use Illuminate\Support\Facades\Artisan;
use Jlorente\DataMigrations\Console\Commands\InstallCommand as DataInstallCommand;

/**
 * The data-migration installer inherits Laravel's `migrate:install` signature,
 * and a signature outranks the `$name` the package renames itself with. Nothing
 * reports that collision: the wrong command succeeds, creates `migrations_data`
 * instead of `migrations`, and the migration run then fails on a table that was
 * never created -- so this asserts the names rather than any behaviour.
 */
it('keeps the data-migration installer off the migrate:install name', function (): void {
    $commands = Artisan::all();

    expect($commands)->toHaveKeys(['migrate:install', 'migrate-data:install'])
        ->and($commands['migrate:install'])->toBeInstanceOf(InstallCommand::class)
        ->and($commands['migrate:install'])->not->toBeInstanceOf(DataInstallCommand::class)
        ->and($commands['migrate-data:install'])->toBeInstanceOf(DataMigrationInstallCommand::class);
});
