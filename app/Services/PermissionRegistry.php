<?php

declare(strict_types=1);

namespace App\Services;

use App\Addons\Models\AddonBootCache;
use App\Addons\Support\BootCache;
use App\Contracts\Filament\ProvidesPermissions;
use App\Enums\Ability;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Str;

/**
 * Single source of truth for every permission phpVMS knows about.
 *
 * Aggregates four sources:
 *  - Filament resources  -> view/edit/delete per model subject
 *  - Filament pages that opt in via HasPermissionKey -> view (+edit)
 *  - Custom permissions   -> config('roles.custom_permissions') + runtime register()
 *  - Modules              -> an `access:<module>` permission per module that owns
 *                            Filament components, plus that module's resources/pages
 *
 * Every group carries a scope (core, or a module name) so the roles matrix can
 * present them under tabs. Consumed by the permission:sync command and the
 * roles permission matrix.
 */
class PermissionRegistry
{
    public const CORE_SCOPE = 'core';

    /**
     * Runtime-registered custom permissions: name => ['group' => ..., 'label' => ...].
     *
     * @var array<string, array{group: string, label: string}>
     */
    protected array $custom = [];

    /**
     * Names of permissions also declared as OAuth-exposable API scopes.
     *
     * @var list<string>
     */
    protected array $apiScopes = [];

    public function __construct(private readonly BootCache $bootCache) {}

    /**
     * Register a custom permission (e.g. from a module service provider).
     */
    public function register(string $name, string $group = 'Custom', ?string $label = null): void
    {
        $this->custom[$name] = [
            'group' => $group,
            'label' => $label ?? Str::headline($name),
        ];
    }

    /**
     * Register many custom permissions under one group.
     *
     * Accepts either ['name' => 'Label', ...] or a plain list of names.
     *
     * @param array<int|string, string> $permissions
     */
    public function registerMany(array $permissions, string $group = 'Custom'): void
    {
        foreach ($permissions as $name => $label) {
            if (is_int($name)) {
                $name = $label;
                $label = Str::headline($name);
            }

            $this->register($name, $group, $label);
        }
    }

    /**
     * Register a permission-backed OAuth scope.
     *
     * Records the name as a normal permission (so it flows through
     * `permission:sync`, appears in the roles matrix, and is role-assignable)
     * AND flags it as an API scope (see {@see apiScopes()}), which
     * `PassportServiceProvider` merges into the Passport catalog and
     * `App\Auth\ScopeRepository` gates by `$user->can($name)` at token
     * issuance. Scope id **is** the permission name — no separate mapping.
     */
    public function registerApiScope(string $name, string $group = 'API', ?string $label = null): void
    {
        $this->register($name, $group, $label);

        $this->apiScopes[] = $name;
    }

    /**
     * Names of permissions declared as API scopes via {@see registerApiScope()}.
     *
     * @return list<string>
     */
    public function apiScopes(): array
    {
        return array_values(array_unique($this->apiScopes));
    }

    /**
     * Registered API scopes as a `name => label` map, for merging into the
     * Passport scope catalog (`PassportServiceProvider::boot()`).
     *
     * @return array<string, string>
     */
    public function apiScopeCatalog(): array
    {
        $catalog = [];

        foreach ($this->apiScopes() as $name) {
            $catalog[$name] = $this->custom[$name]['label'] ?? Str::headline($name);
        }

        return $catalog;
    }

    /**
     * Every permission name known to the registry.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $names = [];

        foreach ($this->grouped() as $group) {
            foreach ($group['permissions'] as $permission) {
                $names[] = $permission['name'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The full permission catalog grouped for display in the roles matrix.
     *
     * @return list<array{key: string, label: string, type: string, scope: string, scope_key: string, permissions: list<array{name: string, ability: ?string, label: string}>}>
     */
    public function grouped(): array
    {
        $groups = [];

        // Per-module access permissions.
        foreach ($this->moduleComponents() as $moduleKey => $module) {
            $groups[] = [
                'key'         => 'module:'.$moduleKey,
                'label'       => __('filament.module_access'),
                'type'        => 'module',
                'scope'       => $module,
                'scope_key'   => $moduleKey,
                'permissions' => [[
                    'name'    => 'access:'.$moduleKey,
                    'ability' => null,
                    'label'   => __('filament.access'),
                ]],
            ];
        }

        // Resources: view / edit / delete per subject.
        $seenSubjects = [];

        foreach ($this->resourceModels() as $model) {
            $subject = Str::kebab(class_basename($model));

            if (isset($seenSubjects[$subject])) {
                continue;
            }

            $seenSubjects[$subject] = true;

            [$scope, $scopeKey] = $this->scopeFor($model);

            $permissions = [];

            foreach ([Ability::View, Ability::Edit, Ability::Delete] as $ability) {
                $permissions[] = [
                    'name'    => $ability->permission($subject),
                    'ability' => $ability->value,
                    'label'   => Str::headline($ability->value),
                ];
            }

            $groups[] = [
                'key'         => 'resource:'.$subject,
                'label'       => Str::headline(Str::plural(class_basename($model))),
                'type'        => 'resource',
                'scope'       => $scope,
                'scope_key'   => $scopeKey,
                'permissions' => $permissions,
            ];
        }

        // Pages that opt in via HasPermissionKey.
        foreach ($this->componentDefinitions() as $component) {
            $groups[] = [
                'key'         => 'page:'.$component['key'],
                'label'       => $component['label'],
                'type'        => 'page',
                'scope'       => $component['scope'],
                'scope_key'   => $component['scope_key'],
                'permissions' => $component['permissions'],
            ];
        }

        // Custom permissions, grouped by their declared group (always core scope).
        $customByGroup = [];

        foreach ($this->customPermissions() as $name => $meta) {
            $customByGroup[$meta['group']][] = [
                'name'    => $name,
                'ability' => null,
                'label'   => $meta['label'],
            ];
        }

        foreach ($customByGroup as $group => $permissions) {
            $groups[] = [
                'key'         => 'custom:'.$group,
                'label'       => $group,
                'type'        => 'custom',
                'scope'       => self::CORE_SCOPE,
                'scope_key'   => self::CORE_SCOPE,
                'permissions' => $permissions,
            ];
        }

        return $groups;
    }

    /**
     * Distinct resource subject slugs mapped to a display label.
     *
     * @return array<string, string>
     */
    public function resourceSubjects(): array
    {
        $subjects = [];

        foreach ($this->resourceModels() as $model) {
            $subjects[Str::kebab(class_basename($model))] = Str::headline(Str::plural(class_basename($model)));
        }

        ksort($subjects);

        return $subjects;
    }

    /**
     * Distinct model classes backing the registered Filament resources.
     *
     * @return list<class-string>
     */
    public function resourceModels(): array
    {
        $models = [];

        foreach (Filament::getPanels() as $panel) {
            foreach ($panel->getResources() as $resource) {
                $model = $resource::getModel();

                if (is_string($model) && $model !== '' && class_exists($model)) {
                    $models[$model] = $model;
                }
            }
        }

        return array_values($models);
    }

    /**
     * The module a class belongs to (display name), or null for core/app classes.
     */
    public function moduleOf(string $class): ?string
    {
        if (!str_starts_with($class, 'Modules\\')) {
            return null;
        }

        $name = strstr(substr($class, strlen('Modules\\')), '\\', true);

        return $name === false ? null : $name;
    }

    /**
     * The permission-safe key for a module.
     *
     * Managed modules (those with a registry_id in the boot cache) key off the
     * slugified registry_id, e.g. `acme/my-addon` => `acme-my-addon`. Every other
     * module falls back to its lower-cased display name, e.g. `VMSAcars` => `vmsacars`.
     */
    public function moduleKey(string $module): string
    {
        $entry = $this->bootCache->all()->first(
            fn (AddonBootCache $entry): bool => $entry->name === $module
        );

        if ($entry !== null && $entry->registryId !== null) {
            return keyed_str(strtolower($entry->registryId));
        }

        return Str::lower($module);
    }

    /**
     * The module-access permission key a panel belongs to, derived from the
     * namespace of its registered components, or null for core panels.
     */
    public function moduleKeyForPanel(Panel $panel): ?string
    {
        foreach ([...$panel->getResources(), ...$panel->getPages(), ...$panel->getWidgets()] as $class) {
            if (is_string($class) && ($module = $this->moduleOf($class)) !== null) {
                return $this->moduleKey($module);
            }
        }

        return null;
    }

    /**
     * Modules that own at least one Filament component: key => display name.
     *
     * @return array<string, string>
     */
    protected function moduleComponents(): array
    {
        $modules = [];

        foreach (Filament::getPanels() as $panel) {
            foreach ([...$panel->getResources(), ...$panel->getPages(), ...$panel->getWidgets()] as $class) {
                if (is_string($class) && ($module = $this->moduleOf($class)) !== null) {
                    $modules[$this->moduleKey($module)] = $module;
                }
            }
        }

        ksort($modules);

        return $modules;
    }

    /**
     * The [display, key] scope for a class.
     *
     * @return array{0: string, 1: string}
     */
    protected function scopeFor(string $class): array
    {
        $module = $this->moduleOf($class);

        return $module !== null
            ? [$module, $this->moduleKey($module)]
            : [self::CORE_SCOPE, self::CORE_SCOPE];
    }

    /**
     * Permission definitions from pages that opt in via HasPermissionKey.
     *
     * Widgets are intentionally excluded: a widget is only reachable through a
     * page/resource the user can already access, so that access is the gate.
     *
     * @return list<array{key: string, label: string, scope: string, scope_key: string, permissions: list<array{name: string, ability: string, label: string}>}>
     */
    protected function componentDefinitions(): array
    {
        $components = [];

        foreach (Filament::getPanels() as $panel) {
            foreach ($panel->getPages() as $class) {
                if (!method_exists($class, 'permissionDefinitions')) {
                    continue;
                }

                /** @var class-string<ProvidesPermissions> $class */
                $key = $class::getPermissionKey();

                [$scope, $scopeKey] = $this->scopeFor($class);

                $components[$key] = [
                    'key'         => $key,
                    'label'       => $class::getPermissionGroupLabel(),
                    'scope'       => $scope,
                    'scope_key'   => $scopeKey,
                    'permissions' => $class::permissionDefinitions(),
                ];
            }
        }

        return array_values($components);
    }

    /**
     * Custom permissions from config plus runtime registrations.
     *
     * @return array<string, array{group: string, label: string}>
     */
    protected function customPermissions(): array
    {
        $permissions = [];

        /** @var array<string, array<int|string, string>> $configured */
        $configured = config('roles.custom_permissions', []);

        foreach ($configured as $group => $entries) {
            foreach ($entries as $name => $label) {
                if (is_int($name)) {
                    $name = $label;
                    $label = Str::headline($name);
                }

                $permissions[$name] = ['group' => $group, 'label' => $label];
            }
        }

        // Runtime registrations win over config for the same name.
        return [...$permissions, ...$this->custom];
    }
}
