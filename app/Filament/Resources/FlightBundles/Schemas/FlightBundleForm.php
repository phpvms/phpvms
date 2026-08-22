<?php

namespace App\Filament\Resources\FlightBundles\Schemas;

use App\Enums\BundleType;
use App\Models\FlightBundle;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
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

            Select::make('type')
                ->label(__('filament.bundles.fields.type'))
                ->options(BundleType::class)
                ->default(BundleType::Flights)
                ->selectablePlaceholder(false)
                ->native(false)
                ->required()
                // Live so the leg warning below appears the moment the admin
                // picks Tour, rather than only after a save.
                ->live()
                ->helperText(__('filament.bundles.fields.type_helper')),

            Text::make(fn (?FlightBundle $record): string => self::tourLegWarning($record))
                ->key('tour_leg_warning')
                ->color('warning')
                ->icon(Phosphor::WarningLight)
                ->visible(fn (Get $get, ?FlightBundle $record): bool => self::isTour($get('type'))
                    && self::tourLegWarning($record) !== ''),

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
     * Form state for `type` arrives as a BundleType when the schema was filled
     * from the model, which casts it, and as the raw string when it came from
     * the select's own default or a test's fillForm(). Both mean tour.
     */
    private static function isTour(mixed $type): bool
    {
        return $type === BundleType::Tour || $type === BundleType::Tour->value;
    }

    /**
     * What is wrong with this bundle's leg numbering, phrased for an admin, or
     * an empty string when there is nothing to say.
     *
     * Only the reason and the leg come out of FlightBundle::tourLegSequence();
     * the sentence is built here so the bundle page and the edit drawer say the
     * same thing. An unsaved bundle has no flights to inspect yet, so it gets
     * the standing rule instead of a finding.
     *
     * Returns '' rather than null so callers can hand it straight to a Text
     * component, which types its content as string|Htmlable|Closure|null and
     * would render a null as an empty band.
     */
    public static function tourLegWarning(?FlightBundle $record): string
    {
        if (!$record instanceof FlightBundle) {
            return __('filament.bundles.tour_legs.unsaved');
        }

        $sequence = $record->tourLegSequence();

        if ($sequence['valid']) {
            return '';
        }

        return match ($sequence['problem']) {
            'empty'     => __('filament.bundles.tour_legs.empty'),
            'duplicate' => __('filament.bundles.tour_legs.duplicate', ['leg' => $sequence['leg']]),
            default     => __('filament.bundles.tour_legs.missing', [
                'leg'   => $sequence['leg'],
                'count' => $sequence['flights']->count(),
            ]),
        };
    }

    public static function subfleets(): Select
    {
        return Select::make('subfleets')
            ->label(trans_choice('common.subfleet', 2))
            ->relationship('subfleets', 'name')
            ->multiple()
            ->searchable()
            ->preload();
    }
}
