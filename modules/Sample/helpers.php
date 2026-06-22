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
     * Demonstrates reading an addon setting: the value is declared by
     * SampleServiceProvider::settings(), editable from the Sample panel's
     * Settings page, and read here by addon alias. Falls back to a default
     * when the setting has not been synced yet.
     */
    function sample_module_greeting(): string
    {
        return (string) addon_setting('sample', 'greeting', 'Hello from the Sample module!');
    }
}

if (!function_exists('sample_setting')) {
    /**
     * Convenience accessor for any Sample module setting.
     *
     * Usage example:
     *     sample_setting('max_items', 10);   // typed int
     *     sample_setting('enabled', true);   // typed bool
     *
     * @param  mixed $default
     * @return mixed
     */
    function sample_setting(string $key, $default = null)
    {
        return addon_setting('sample', $key, $default);
    }
}
