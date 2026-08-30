<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthConnections\Schemas;

use App\Models\OAuthConnection;
use App\Services\OAuth\SocialiteProviderRegistry;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class OAuthConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('social_login.sections.connection'))
                ->icon(Phosphor::LinkLight)
                ->collapsible()
                ->persistCollapsed()
                ->schema(self::fields())
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    /**
     * Bare fields are used by the branded create and edit drawers.
     *
     * @return array<int, Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('connection_id')
                ->label(__('social_login.fields.connection_id'))
                ->helperText(__('social_login.fields.connection_id_help'))
                ->required()
                ->string()
                ->maxLength(64)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true)
                ->disabledOn('edit'),

            TextInput::make('display_name')
                ->label(__('social_login.fields.display_name'))
                ->helperText(__('social_login.fields.display_name_help'))
                ->required()
                ->string()
                ->maxLength(255)
                ->disabled(self::managed(...)),

            Select::make('protocol')
                ->label(__('social_login.fields.protocol'))
                ->options([
                    'oauth2' => __('social_login.protocols.oauth2'),
                    'oidc'   => __('social_login.protocols.oidc'),
                ])
                ->helperText(__('social_login.fields.protocol_help'))
                ->afterStateHydrated(function (Select $component, ?OAuthConnection $record): void {
                    if ($record instanceof OAuthConnection) {
                        $component->state(self::registry()->protocolFor($record->provider));
                    }
                })
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    $provider = $state === 'oidc' ? 'openidconnect' : null;

                    $set('provider', $provider);
                    self::applyProviderDefaults($provider, $set);
                })
                ->live()
                ->native(false)
                ->required()
                ->disabled(self::managed(...))
                ->columnSpanFull(),

            Select::make('provider')
                ->label(__('social_login.fields.provider'))
                ->options(self::providerOptions())
                ->disableOptionWhen(fn (string $value): bool => !self::registry()->isInstalled($value))
                ->helperText(function (Get $get): string {
                    $definition = self::registry()->find((string) $get('provider'));
                    if ($definition === null) {
                        return '';
                    }

                    return __('social_login.fields.provider_help', [
                        'package' => $definition['package'],
                        'status'  => $definition['installed']
                            ? __('social_login.status.installed')
                            : __('social_login.status.not_installed'),
                    ]);
                })
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    self::applyProviderDefaults($state, $set);
                })
                ->live()
                ->native(false)
                ->required(fn (Get $get): bool => $get('protocol') === 'oauth2')
                ->visible(fn (Get $get): bool => $get('protocol') === 'oauth2')
                ->disabled(self::managed(...))
                ->columnSpanFull(),

            Group::make()
                ->schema(fn (Get $get): array => self::providerFields((string) $get('provider')))
                ->columns(2)
                ->columnSpanFull(),

            Toggle::make('enabled')
                ->label(__('social_login.fields.enabled'))
                ->helperText(__('social_login.fields.enabled_help'))
                ->offIcon(Phosphor::XLight)
                ->offColor('danger')
                ->onIcon(Phosphor::CheckLight)
                ->onColor('success')
                ->default(false)
                ->extraFieldWrapperAttributes(['class' => 'field-rule-above'])
                ->columnSpanFull(),

            Toggle::make('login_enabled')
                ->label(__('social_login.fields.login_enabled'))
                ->default(false),

            Toggle::make('registration_enabled')
                ->label(__('social_login.fields.registration_enabled'))
                ->default(false),

            Toggle::make('linking_enabled')
                ->label(__('social_login.fields.linking_enabled'))
                ->default(false),

            TextInput::make('sort_order')
                ->label(__('social_login.fields.sort_order'))
                ->integer()
                ->minValue(0)
                ->default(0),
        ];
    }

    public static function prepareData(array $data, ?OAuthConnection $record = null): array
    {
        $protocol = Arr::pull($data, 'protocol');

        if ($protocol === 'oidc' && $record?->managed_by === null) {
            $data['provider'] = 'openidconnect';
        }

        if (blank($data['client_secret'] ?? null)) {
            unset($data['client_secret']);
        }

        $data['configuration'] = (array) ($data['configuration'] ?? []);

        if ($record?->managed_by !== null) {
            return Arr::only($data, [
                'enabled',
                'login_enabled',
                'registration_enabled',
                'linking_enabled',
                'sort_order',
            ]);
        }

        return $data;
    }

    /**
     * @return array<int, Component>
     */
    private static function providerFields(string $provider): array
    {
        $definition = self::registry()->find($provider);
        if ($definition === null) {
            return [];
        }

        return array_map(
            self::providerField(...),
            $definition['fields'],
        );
    }

    /**
     * @param array{key: string, label: string, type: string, required: bool, default?: mixed, placeholder?: string, helperText?: string, rules?: list<string>} $definition
     */
    private static function providerField(array $definition): Component
    {
        $key = $definition['key'];
        $statePath = in_array($key, ['client_id', 'client_secret', 'scopes'], true)
            ? $key
            : 'configuration.'.$key;

        if ($definition['type'] === 'tags') {
            return TagsInput::make($statePath)
                ->label($definition['label'])
                ->helperText($definition['helperText'] ?? __('social_login.fields.scopes_help'))
                ->placeholder($definition['placeholder'] ?? null)
                ->default($definition['default'] ?? [])
                ->rules($definition['rules'] ?? [])
                ->required($definition['required'])
                ->disabled(self::managed(...))
                ->columnSpanFull();
        }

        $input = TextInput::make($statePath)
            ->label($definition['label'])
            ->placeholder($definition['placeholder'] ?? null)
            ->default($definition['default'] ?? null)
            ->rules($definition['rules'] ?? [])
            ->required($definition['required'])
            ->disabled(self::managed(...));

        if ($definition['type'] === 'url') {
            $input->url();
        }

        if ($definition['type'] === 'password') {
            $input
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                ->placeholder('••••••••••••')
                ->helperText(fn (string $operation): string => $operation === 'edit'
                    ? __('social_login.fields.client_secret_edit_help')
                    : __('social_login.fields.client_secret_create_help'))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->afterStateHydrated(function (TextInput $component, string $operation): void {
                    if ($operation === 'edit') {
                        $component->state(null);
                    }
                })
                ->dehydrated(fn (?string $state, ?OAuthConnection $record): bool => $record?->managed_by === null && filled($state));
        } else {
            $input->helperText($definition['helperText'] ?? null);
        }

        return $input;
    }

    /**
     * @return array<string, string>
     */
    private static function providerOptions(): array
    {
        return collect(self::registry()->all())
            ->where('protocol', 'oauth2')
            ->mapWithKeys(fn (array $definition): array => [$definition['key'] => $definition['label']])
            ->all();
    }

    private static function applyProviderDefaults(?string $provider, Set $set): void
    {
        $definition = self::registry()->find((string) $provider);
        $defaults = collect($definition['fields'] ?? [])
            ->filter(fn (array $field): bool => array_key_exists('default', $field))
            ->mapWithKeys(fn (array $field): array => [$field['key'] => $field['default']]);

        $set('client_id', null);
        $set('client_secret', null);
        $set('scopes', []);
        $set('configuration', []);

        foreach ($defaults as $key => $value) {
            $statePath = in_array($key, ['client_id', 'client_secret', 'scopes'], true)
                ? $key
                : 'configuration.'.$key;

            $set($statePath, $value);
        }
    }

    private static function registry(): SocialiteProviderRegistry
    {
        return app(SocialiteProviderRegistry::class);
    }

    private static function managed(?OAuthConnection $record): bool
    {
        return $record?->managed_by !== null;
    }
}
