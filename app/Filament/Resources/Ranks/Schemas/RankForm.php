<?php

namespace App\Filament\Resources\Ranks\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RankForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->components([
                Section::make(__('filament.rank_informations'))
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('common.name'))
                                    ->required()
                                    ->string(),

                                TextInput::make('image_url')
                                    ->label(__('common.image_url'))
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
                                    ->offIcon(Heroicon::XCircle)
                                    ->offColor('danger')
                                    ->onIcon(Heroicon::CheckCircle)
                                    ->onColor('success'),

                                Toggle::make('auto_approve_manual')
                                    ->label(__('filament.rank_auto_approve_manual'))
                                    ->offIcon(Heroicon::XCircle)
                                    ->offColor('danger')
                                    ->onIcon(Heroicon::CheckCircle)
                                    ->onColor('success'),

                                Toggle::make('auto_promote')
                                    ->label(__('filament.rank_auto_promote'))
                                    ->helperText(__('filament.rank_auto_promote_hint'))
                                    ->offIcon(Heroicon::XCircle)
                                    ->offColor('danger')
                                    ->onIcon(Heroicon::CheckCircle)
                                    ->onColor('success'),
                            ])
                            ->columnSpanFull()
                            ->columns(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
