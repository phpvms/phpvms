<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown at loader boot when Composer's autoloader is in classmap-authoritative
 * mode (LOAD-08, D-16, T-03-04).
 *
 * Runtime PSR-4 registration via addPsr4() silently fails under authoritative
 * classmap mode — addons would appear to load but their classes would not be
 * found. This exception halts boot loudly rather than letting the system
 * silently mis-behave.
 */
class AutoloadModeException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            "Composer's autoloader is running in classmap-authoritative mode. ".
            'The addon loader requires runtime PSR-4 registration via addPsr4(), '.
            'which is silently ignored in classmap-authoritative mode — addons '.
            'will not load correctly. '.
            'To fix: re-run `composer dump-autoload` without the '.
            '`--classmap-authoritative`, `-a`, or `--optimize` flags.'
        );
    }
}
