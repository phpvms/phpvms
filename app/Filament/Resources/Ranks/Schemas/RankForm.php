<?php

namespace App\Filament\Resources\Ranks\Schemas;

use App\Filament\Forms\Components\AssetImagePicker;
use App\Models\Asset;
use App\Models\Rank;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RankForm
{
    /**
     * Rank badges render on public profile and roster pages, so they are
     * fetched without a session — same call as the airline mark.
     */
    public static function imageDisk(): string
    {
        return (string) config('filesystems.public_files');
    }

    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->components([
                Section::make(__('filament.rank_information'))
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('common.name'))
                                    ->required()
                                    ->string(),
                            ])
                            ->columnSpanFull()
                            ->columns(),
                        Grid::make()
                            ->schema([
                                TextInput::make('hours')
                                    ->label(trans_choice('common.hour', 2))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0),

                                TextInput::make('acars_base_pay_rate')
                                    ->label(__('filament.rank_acars_base_pay_rate'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.rank_acars_base_pay_rate_hint')),

                                TextInput::make('manual_base_pay_rate')
                                    ->label(__('filament.rank_manual_base_pay_rate'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText(__('filament.rank_manual_base_pay_rate_hint')),

                                Toggle::make('auto_approve_acars')
                                    ->label(__('filament.rank_auto_approve_acars'))
                                    ->offIcon(Phosphor::XLight)
                                    ->offColor('danger')
                                    ->onIcon(Phosphor::CheckLight)
                                    ->onColor('success'),

                                Toggle::make('auto_approve_manual')
                                    ->label(__('filament.rank_auto_approve_manual'))
                                    ->offIcon(Phosphor::XLight)
                                    ->offColor('danger')
                                    ->onIcon(Phosphor::CheckLight)
                                    ->onColor('success'),

                                Toggle::make('auto_promote')
                                    ->label(__('filament.rank_auto_promote'))
                                    ->helperText(__('filament.rank_auto_promote_hint'))
                                    ->offIcon(Phosphor::XLight)
                                    ->offColor('danger')
                                    ->onIcon(Phosphor::CheckLight)
                                    ->onColor('success'),
                            ])
                            ->columnSpanFull()
                            ->columns(3),

                        Section::make(__('filament.rank_images'))
                            ->schema([
                                AssetImagePicker::make(
                                    Asset::SLOT_RANK,
                                    fn (?Rank $record): ?int => $record?->id,
                                    self::imageDisk(),
                                ),
                            ])
                            // The picker is edit-only, so on create the section
                            // would be an empty heading.
                            ->hiddenWhenAllChildComponentsHidden()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
