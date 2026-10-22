<?php

declare(strict_types=1);

use App\Console\DataMigrationInstallCommand;
use Illuminate\Database\Console\Migrations\InstallCommand;
use Illuminate\Support\Facades\Artisan;
use Jlorente\DataMigrations\Console\Commands\InstallCommand as DataInstallCommand;
use Symfony\Component\Console\Command\Command;

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

/**
 * The name being right is not enough. While the class lived in
 * `app/Console/Commands`, Laravel's command discovery built a second copy of it
 * by class name, autowired the core `migrations` repository into it, and that
 * copy won the registration -- so `migrate-data:install` would have installed
 * the wrong table. Discovery also cannot build it at all under `--no-dev`,
 * which took out the release-package and docker-image jobs.
 */
it('gives each installer the repository for its own table', function (): void {
    $commands = Artisan::all();

    $repositoryTable = function (Command $command): string {
        $repository = new ReflectionProperty($command, 'repository')->getValue($command);

        return new ReflectionProperty($repository, 'table')->getValue($repository);
    };

    expect($repositoryTable($commands['migrate-data:install']))->toBe(config('data-migrations.table'))
        ->and($repositoryTable($commands['migrate:install']))->toBe(config('database.migrations.table'));
});
