<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Override;
use SensitiveParameter;
use Throwable;

/**
 * Eloquent user provider that tolerates a missing users table.
 *
 * Before phpVMS is installed (a fresh or wiped database) the `users` table does
 * not exist yet, but a browser may still carry a session cookie from a previous
 * install. Resolving that cookie's user id would run `select * from users ...`
 * and throw an undefined-table QueryException, crashing the installer.
 *
 * Overriding the retrieval paths to return null when the table is absent makes
 * every auth caller — the panel's AuthenticateSession middleware, Debugbar's
 * auth collector, Filament, the frontend — see "no authenticated user" during a
 * fresh install instead of a 500. Once the table exists, behaviour is identical
 * to the stock provider and genuine query errors still surface.
 */
class InstallSafeUserProvider extends EloquentUserProvider
{
    /**
     * @return (Authenticatable&Model)|null
     */
    #[Override]
    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->tolerateMissingTable(fn () => parent::retrieveById($identifier));
    }

    /**
     * @return (Authenticatable&Model)|null
     */
    #[Override]
    public function retrieveByToken($identifier, #[SensitiveParameter] $token): ?Authenticatable
    {
        return $this->tolerateMissingTable(fn () => parent::retrieveByToken($identifier, $token));
    }

    /**
     * @param  callable(): ((Authenticatable&Model)|null) $callback
     * @return (Authenticatable&Model)|null
     */
    private function tolerateMissingTable(callable $callback): ?Authenticatable
    {
        try {
            return $callback();
        } catch (QueryException $queryException) {
            // Only swallow the error when the users table genuinely does not
            // exist (pre-install). Any other query failure is a real problem and
            // must keep propagating.
            if (!$this->usersTableExists()) {
                return null;
            }

            throw $queryException;
        }
    }

    private function usersTableExists(): bool
    {
        try {
            return Schema::hasTable($this->createModel()->getTable());
        } catch (Throwable) {
            return false;
        }
    }
}
