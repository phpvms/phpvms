<?php

namespace App\Filament\Resources\FlightBundles\Schemas;

use App\Filament\Forms\Components\InlineMultiSelect;
use App\Models\Subfleet;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FlightBundleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.bundles.sections.details'))
                    ->schema(self::fields())
                    ->columnSpanFull()
                    ->columns(1),
            ]);
    }

    /**
     * The bundle's editable fields, bare of layout — the create page wraps
     * them in a section, the edit drawer lays them out flat.
     *
     * @return array<int, Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->label(__('filament.bundles.fields.name')),

            Textarea::make('description')
                ->rows(3)
                ->label(__('filament.bundles.fields.description')),

            DatePicker::make('start_date')
                ->native(false)
                ->label(__('common.start_date'))
                ->helperText(__('filament.bundles.fields.start_date_helper')),

            DatePicker::make('end_date')
                ->native(false)
                ->afterOrEqual('start_date')
                ->label(__('common.end_date'))
                ->helperText(__('filament.bundles.fields.end_date_helper')),

            self::subfleets(),

            Toggle::make('enabled')
                ->default(true)
                ->label(__('filament.bundles.fields.enabled'))
                ->extraFieldWrapperAttributes(['class' => 'field-rule-above']),
        ];
    }

    /**
     * Bundle-level default subfleets, edited in place instead of through a
     * relation manager. Grouped by airline; airline-less subfleets list first
     * (subfleets.airline_id is nullable even though the factory always fills
     * it — an unguarded ->airline->name here would break the whole option list).
     */
    public static function subfleets(): InlineMultiSelect
    {
        return InlineMultiSelect::make('subfleets')
            ->label(trans_choice('common.subfleet', 2))
            ->relationship('subfleets', 'name')
            ->optionMetas(fn (): array => Subfleet::pluck('type', 'id')->all())
            ->optionGroups(fn (): array => Subfleet::with('airline')
                ->get()
                ->mapWithKeys(fn (Subfleet $subfleet): array => [$subfleet->id => $subfleet->airline?->name])
                ->filter()
                ->all());
    }
}
