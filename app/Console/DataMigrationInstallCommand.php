<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Jlorente\DataMigrations\Console\Commands\InstallCommand as BaseInstallCommand;

/**
 * The data-migration repository installer, under its own command name.
 *
 * `Jlorente\DataMigrations\Console\Commands\InstallCommand` renames Laravel's
 * `migrate:install` by overriding `protected $name`. That stopped working in
 * laravel/framework 13.38, which rewrote `Illuminate\...\InstallCommand` to
 * declare `protected $signature = 'migrate:install {--database=}'` instead of a
 * `$name` plus `getOptions()`. `Command::__construct()` derives the command name
 * from `$signature` whenever it is set, so the inherited signature wins and the
 * data-migration installer registers itself as `migrate:install`.
 *
 * The consequence is not a missing command but a silently wrong one: every
 * `migrate` run calls `migrate:install` to create its repository, gets this
 * command, and creates `migrations_data` instead of `migrations`. The run then
 * fails reading a `migrations` table that was never made, which takes the whole
 * test suite with it because RefreshDatabase starts with `migrate:fresh`.
 *
 * Declaring the signature here — name included — is what survives the upstream
 * change, since a subclass signature overrides the parent's.
 *
 * It sits in `app/Console` rather than `app/Console/Commands` on purpose.
 * Laravel auto-discovers the latter and registers what it finds by class name,
 * which means the container autowires the constructor. Two things go wrong
 * there: the inherited `MigrationRepositoryInterface` parameter has no binding
 * in core Laravel at all (only `kitloong/laravel-migrations-generator`, a dev
 * dependency, binds it), so a `--no-dev` install cannot build the class; and
 * when it can, autowiring hands it the core `migrations` repository, so the
 * discovered instance overwrites the correct registration and the command
 * installs the wrong table. Out of that directory, the only thing that builds
 * it is the `command.migrate-data.install` binding in AppServiceProvider, which
 * passes `migration.data.repository`.
 */
#[Description('Create the data migration repository')]
#[Signature('migrate-data:install {--database= : The database connection to use}')]
class DataMigrationInstallCommand extends BaseInstallCommand {}
