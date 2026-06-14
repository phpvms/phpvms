<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Enums\Ability;
use Illuminate\Support\Str;

/**
 * Shared permission metadata for Filament pages and widgets so the
 * PermissionRegistry can discover them. The permission key defaults to the
 * kebab-cased class basename (e.g. `Settings` => `settings`).
 */
trait HasPermissionKey
{
    protected static ?string $permissionKey = null;

    public static function getPermissionKey(): string
    {
        return static::$permissionKey ?? Str::kebab(class_basename(static::class));
    }

    /**
     * Abilities this page/widget exposes. Override to add Ability::Edit for
     * pages that have a mutating action (e.g. Settings).
     *
     * @return array<int, Ability>
     */
    public static function getPermissionAbilities(): array
    {
        return [Ability::View];
    }

    /**
     * Human-readable group label shown in the roles permission matrix.
     */
    public static function getPermissionGroupLabel(): string
    {
        return Str::headline(class_basename(static::class));
    }

    /**
     * Extra custom-named permissions that belong in this component's group
     * (e.g. the Backups page's `create-backup`/`delete-backup` actions).
     *
     * @return array<int, array{name: string, ability: null, label: string}>
     */
    public static function extraPermissions(): array
    {
        return [];
    }

    /**
     * The permission definitions contributed to the registry.
     *
     * @return array<int, array{name: string, ability: string|null, label: string}>
     */
    public static function permissionDefinitions(): array
    {
        $key = static::getPermissionKey();

        $abilities = array_map(static fn (Ability $ability): array => [
            'name'    => $ability->permission($key),
            'ability' => $ability->value,
            'label'   => Str::headline($ability->value),
        ], static::getPermissionAbilities());

        return [...$abilities, ...static::extraPermissions()];
    }
}
