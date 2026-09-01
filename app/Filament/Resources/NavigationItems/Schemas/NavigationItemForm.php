<?php

declare(strict_types=1);

namespace App\Filament\Resources\NavigationItems\Schemas;

use App\Enums\NavigationTarget;
use App\Enums\PageType;
use App\Models\NavigationItem;
use App\Models\Page;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Route;

class NavigationItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.navigation_items.label'))
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('label')
                                    ->label(__('filament.navigation_items.label'))
                                    ->required()
                                    ->string(),

                                TextInput::make('icon')
                                    ->label(__('common.icon'))
                                    ->string(),
                            ])
                            ->columnSpanFull()
                            ->columns(2),

                        Select::make('parent_id')
                            ->label(__('filament.navigation_items.parent'))
                            ->placeholder(__('filament.navigation_items.parent_none'))
                            ->options(fn (?NavigationItem $record): array => self::parentOptions($record))
                            ->native(false)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make(__('filament.navigation_items.target_type'))
                    ->schema([
                        Select::make('target_type')
                            ->label(__('filament.navigation_items.target_type'))
                            ->options(NavigationTarget::class)
                            ->default(NavigationTarget::PAGE)
                            ->native(false)
                            ->required()
                            ->live(),

                        Select::make('target_value')
                            ->label(__('filament.navigation_items.target_page'))
                            ->options(fn (): array => Page::query()
                                ->where('type', PageType::PAGE)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('target_type') === NavigationTarget::PAGE->value
                                || $get('target_type') === NavigationTarget::PAGE),

                        Select::make('target_value')
                            ->label(__('filament.navigation_items.target_route'))
                            ->options(fn (): array => self::routeOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->visible(fn (Get $get): bool => $get('target_type') === NavigationTarget::ROUTE->value
                                || $get('target_type') === NavigationTarget::ROUTE),

                        TextInput::make('target_value')
                            ->label(__('filament.navigation_items.target_url'))
                            ->url()
                            ->required()
                            ->visible(fn (Get $get): bool => $get('target_type') === NavigationTarget::URL->value
                                || $get('target_type') === NavigationTarget::URL),
                    ])
                    ->columnSpanFull(),

                Section::make(__('filament.navigation_items.options'))
                    ->schema([
                        Grid::make()
                            ->schema([
                                Toggle::make('visible')
                                    ->label(__('filament.navigation_items.visible'))
                                    ->helperText(__('filament.navigation_items.visible_helper'))
                                    ->default(true)
                                    ->offIcon(Phosphor::XLight)
                                    ->offColor('danger')
                                    ->onIcon(Phosphor::CheckLight)
                                    ->onColor('success'),

                                Toggle::make('public')
                                    ->label(__('common.public'))
                                    ->default(true)
                                    ->offIcon(Phosphor::XLight)
                                    ->offColor('danger')
                                    ->onIcon(Phosphor::CheckLight)
                                    ->onColor('success'),

                                Toggle::make('new_window')
                                    ->label(__('filament.should_open_in_new_tab'))
                                    ->offIcon(Phosphor::XLight)
                                    ->offColor('danger')
                                    ->onIcon(Phosphor::CheckLight)
                                    ->onColor('success'),
                            ])
                            ->columnSpanFull()
                            ->columns(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Every item except this one and its own descendants — nesting an item under
     * its own child would orphan the branch from the menu root.
     *
     * @return array<int, string>
     */
    private static function parentOptions(?NavigationItem $record): array
    {
        return NavigationItem::query()
            ->when($record instanceof NavigationItem, fn ($q) => $q->whereKeyNot($record->getKey()))
            ->ordered()
            ->pluck('label', 'id')
            ->reject(fn (string $label, int $id): bool => $record instanceof NavigationItem
                && self::descendsFrom($id, (int) $record->getKey()))
            ->all();
    }

    private static function descendsFrom(int $candidate, int $ancestor): bool
    {
        $item = NavigationItem::find($candidate);

        while ($item?->parent_id !== null) {
            if ($item->parent_id === $ancestor) {
                return true;
            }

            $item = $item->parent;
        }

        return false;
    }

    /**
     * Named GET routes an admin could plausibly link to.
     *
     * @return array<string, string>
     */
    private static function routeOptions(): array
    {
        $options = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || !str_starts_with($name, 'frontend.')) {
                continue;
            }

            // A route with required parameters cannot be linked without them.
            if ($route->parameterNames() !== []) {
                continue;
            }

            $options[$name] = $name;
        }

        ksort($options);

        return $options;
    }
}
