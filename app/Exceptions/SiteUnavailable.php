<?php

namespace App\Exceptions;

use Illuminate\Contracts\Database\LostConnectionDetector;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The generic "site is down" page, shown when the database cannot be reached.
 * It never mentions the database to visitors.
 */
final class SiteUnavailable
{
    /**
     * MySQL client errors for "can't connect", "connection refused", "server
     * has gone away" and "lost connection".
     *
     * @var array<int, int>
     */
    private const array MYSQL_CONNECTION_CODES = [2002, 2003, 2005, 2006];

    /**
     * True when the throwable, or anything in its previous chain, is a
     * database connection failure: a lost connection as Laravel detects it,
     * an SQLSTATE class `08` (connection exception) error, or a MySQL client
     * connection error code.
     */
    public static function causedBy(Throwable $e): bool
    {
        $detector = app(LostConnectionDetector::class);

        for ($current = $e; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($detector->causedByLostConnection($current)) {
                return true;
            }

            // QueryException extends PDOException and copies its errorInfo
            if (!$current instanceof PDOException) {
                continue;
            }

            $errorInfo = $current->errorInfo ?? [];
            $sqlState = (string) ($errorInfo[0] ?? $current->getCode());

            if (str_starts_with($sqlState, '08')) {
                return true;
            }

            if (in_array((int) ($errorInfo[1] ?? 0), self::MYSQL_CONNECTION_CODES, true)) {
                return true;
            }

            if (preg_match('/\[(2002|2003|2005|2006)\]/', $current->getMessage()) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function response(): Response
    {
        return response()->view('system.errors.database_error', [
            'title'   => __('errors.database_unreachable.title'),
            'message' => __('errors.database_unreachable.message'),
        ], 503);
    }
}
