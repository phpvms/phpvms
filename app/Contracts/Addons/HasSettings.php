<?php

declare(strict_types=1);

namespace App\Contracts\Addons;

/**
 * Implemented by an addon's service provider to declare configurable settings.
 *
 * The declared schema is synced into the `addon_settings` table on boot (see
 * App\Services\AddonSettingSyncService): new keys are inserted with their
 * `default` as the initial value, metadata is reconciled to the declared
 * schema, and existing user-edited values are preserved.
 *
 * Read values back with the global helpers `addon_setting()` /
 * `addon_setting_save()`, addressing the addon by its manifest `alias` or
 * `registry_id`. Each declared setting is editable from the shared
 * App\Filament\Pages\AddonSettings page inherited by the addon's panel.
 *
 * Example:
 *
 *     public function settings(): array
 *     {
 *         return [
 *             [
 *                 'key'         => 'api_token',
 *                 'name'        => 'API Token',
 *                 'default'     => '',
 *                 'group'       => 'general',
 *                 'type'        => 'text',
 *                 'options'     => '',
 *                 'description' => 'Token used to authenticate with the remote service',
 *                 'order'       => 0,
 *             ],
 *         ];
 *     }
 */
interface HasSettings
{
    /**
     * Return the addon's settings schema.
     *
     * Each entry is an associative array. Only `key` is required; the rest fall
     * back to sensible defaults during sync:
     *
     *  - `key`         (required) string  Machine key, normalized to lower-case
     *                                     with dots collapsed to underscores.
     *  - `name`        (optional) string  Human-readable label (defaults to the key).
     *  - `default`     (optional) scalar  Initial value for new keys (defaults to '').
     *  - `group`       (optional) string  Tab group on the settings page (defaults to 'general').
     *  - `type`        (optional) string  Field type: text|string|boolean|bool|int|integer|
     *                                     number|float|date|select (defaults to 'text').
     *  - `options`     (optional) string  Comma-separated options for `select` (e.g. 'a,b=Bee').
     *  - `description` (optional) string  Help text shown under the field.
     *  - `order`       (optional) int     Sort order within the group.
     *
     * @return array<int, array<string, mixed>>
     */
    public function settings(): array;
}
