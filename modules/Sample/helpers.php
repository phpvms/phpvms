<?php

declare(strict_types=1);

/*
 * Global helper functions for the Sample module.
 *
 * This file is loaded at runtime by the addon engine because it is declared
 * under `autoload.files` in this module's composer.json — the module equivalent
 * of the root project's app/helpers.php.
 *
 * Conventions for module helpers:
 *  - Prefix function names with the module alias (e.g. sample_*) to avoid
 *    collisions with other modules and the core app.
 *  - Guard every declaration with function_exists() so the file is safe to load
 *    more than once in a long-lived process (Octane workers).
 */

if (!function_exists('sample_module_greeting')) {
    /**
     * Return a greeting from the Sample module.
     *
     * Demonstrates that a module-provided global helper is autoloaded and
     * callable once the module is enabled.
     */
    function sample_module_greeting(): string
    {
        return 'Hello from the Sample module!';
    }
}
