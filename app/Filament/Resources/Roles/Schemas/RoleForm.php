<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Schemas;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Services\PermissionRegistry;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    TextInput::make('name')
                        ->label(__('common.name'))
                        ->required()
                        ->maxLength(255)
                        ->disabled(fn (?Role $record): bool => RoleResource::isSuperAdmin($record))
                        ->columnSpan(1),

                    Toggle::make('disable_activity_checks')
                        ->label(__('filament.disable_activity_checks'))
                        ->helperText(__('filament.disable_activity_checks_help'))
                        ->default(false)
                        ->columnSpan(1),
                ])
                ->columns(),

            // Super-admins bypass every check, so their grants are not editable.
            Section::make(__('filament.role_permissions'))
                ->visible(fn (?Role $record): bool => RoleResource::isSuperAdmin($record))
                ->schema([
                    TextEntry::make('super_admin_notice')
                        ->hiddenLabel()
                        ->state(__('filament.role_super_admin_notice')),
                ]),

            Section::make(__('filament.role_permissions'))
                ->description(__('filament.role_permissions_help'))
                ->hidden(fn (?Role $record): bool => RoleResource::isSuperAdmin($record))
                ->schema([
                    Tabs::make('permissions')
                        ->tabs(static::permissionTabs())
                        ->columnSpanFull(),
                ]),
        ])->columns(1);
    }

    /**
     * One tab per scope (phpVMS core + each module), holding that scope's
     * permission matrix.
     *
     * @return list<Tab>
     */
    protected static function permissionTabs(): array
    {
        $scopes = [];

        foreach (RoleResource::permissionGroups() as $group) {
            $key = $group['scope_key'];

            if (!isset($scopes[$key])) {
                $scopes[$key] = [
                    'label' => $key === PermissionRegistry::CORE_SCOPE
                        ? (string) __('filament.permissions_core_tab')
                        : $group['scope'],
                    'groups' => [],
                ];
            }

            $scopes[$key]['groups'][] = $group;
        }

        // Keep the core tab first, modules after.
        uksort($scopes, fn (string $a, string $b): int => match (true) {
            $a === PermissionRegistry::CORE_SCOPE => -1,
            $b === PermissionRegistry::CORE_SCOPE => 1,
            default                               => strcmp($a, $b),
        });

        $tabs = [];

        foreach ($scopes as $scope) {
            $tabs[] = Tab::make($scope['label'])
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])
                        ->schema(array_map(static::permissionFieldset(...), $scope['groups'])),
                ]);
        }

        return $tabs;
    }

    /**
     * A compact bordered fieldset for a single permission group.
     *
     * @param array{key: string, label: string, type: string, scope: string, scope_key: string, permissions: list<array{name: string, ability: ?string, label: string}>} $group
     */
    protected static function permissionFieldset(array $group): Fieldset
    {
        $options = [];

        foreach ($group['permissions'] as $permission) {
            $options[$permission['name']] = $permission['label'];
        }

        // Short ability labels (view/edit/delete) sit in a row; module-access
        // and custom permissions tend to have longer labels, so they stack.
        $columns = $group['type'] === 'resource' || $group['type'] === 'page' ? 3 : 1;

        return Fieldset::make($group['label'])
            ->columnSpan(1)
            ->columns(1)
            ->schema([
                CheckboxList::make('permissions.'.RoleResource::safeKey($group['key']))
                    ->hiddenLabel()
                    ->options($options)
                    ->bulkToggleable()
                    ->columns($columns),
            ]);
    }
}
