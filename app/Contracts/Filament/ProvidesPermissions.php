<?php

declare(strict_types=1);

namespace App\Contracts\Filament;

/**
 * Implemented (via the HasPermissionKey trait) by Filament pages and widgets
 * that contribute permissions to the PermissionRegistry.
 */
interface ProvidesPermissions
{
    public static function getPermissionKey(): string;

    public static function getPermissionGroupLabel(): string;

    /**
     * @return array<int, array{name: string, ability: string|null, label: string}>
     */
    public static function permissionDefinitions(): array;
}
