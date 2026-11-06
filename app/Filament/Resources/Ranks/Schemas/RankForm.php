<?php

namespace App\Filament\Resources\Ranks\Schemas;

use App\Filament\Forms\Components\AssetImagePicker;
use App\Models\Asset;
use App\Models\Rank;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->required()
                            ->string(),

                        TextInput::make('hours')
                            ->label(trans_choice('common.hour', 2))
                            ->required()
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->aside()
                    ->description(__('filament.rank_information_description'))
                    ->columnSpanFull()
                    ->columns(2),

                Section::make(__('filament.rank_pay'))
                    ->schema([
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
                    ])
                    ->aside()
                    ->description(__('filament.rank_pay_description'))
                    ->columnSpanFull()
                    ->columns(2),

                Section::make(__('filament.rank_approval'))
                    ->schema([
                        Toggle::make('auto_approve_acars')
                            ->label(__('filament.rank_auto_approve_acars'))
                            ->columnSpanFull()
                            ->inlineLabel()
                            ->offIcon(Phosphor::XLight)
                            ->offColor('danger')
                            ->onIcon(Phosphor::CheckLight)
                            ->onColor('success'),

                        Toggle::make('auto_approve_manual')
                            ->label(__('filament.rank_auto_approve_manual'))
                            ->columnSpanFull()
                            ->inlineLabel()
                            ->offIcon(Phosphor::XLight)
                            ->offColor('danger')
                            ->onIcon(Phosphor::CheckLight)
                            ->onColor('success'),

                        Toggle::make('auto_promote')
                            ->label(__('filament.rank_auto_promote'))
                            ->columnSpanFull()
                            ->inlineLabel()
                            ->belowLabel(__('filament.rank_auto_promote_hint'))
                            ->offIcon(Phosphor::XLight)
                            ->offColor('danger')
                            ->onIcon(Phosphor::CheckLight)
                            ->onColor('success'),
                    ])
                    ->aside()
                    ->description(__('filament.rank_approval_description'))
                    ->columnSpanFull(),

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
                    ->aside()
                    ->description(__('filament.rank_images_description'))
                    ->columnSpanFull(),
            ]);
    }
}
