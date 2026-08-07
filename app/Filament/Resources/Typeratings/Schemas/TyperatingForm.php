<?php

namespace App\Filament\Resources\Typeratings\Schemas;

use App\Filament\Forms\Components\InlineMultiSelect;
use App\Models\Subfleet;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TyperatingForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->components([
                Section::make(__('filament.typerating_information'))->schema([
                    TextInput::make('name')
                        ->label(__('common.name'))
                        ->required(),

                    TextInput::make('type')
                        ->label(__('common.type'))
                        ->required(),

                    TextInput::make('description')
                        ->label(__('common.description')),

                    TextInput::make('image_url')
                        ->label(__('common.image_url')),

                    Toggle::make('active')
                        ->label(__('common.active'))
                        ->offIcon(TablerIcon::X)
                        ->offColor('danger')
                        ->onIcon(TablerIcon::Check)
                        ->onColor('success'),

                    // Dense pill-less picker; the subfleet type codes carry
                    // the summary ("B738, A320 +2") and options group under
                    // their airline. Plain pivot, so the relationship sync
                    // is safe here.
                    InlineMultiSelect::make('subfleets')
                        ->label(trans_choice('common.subfleet', 2))
                        ->relationship('subfleets', 'name')
                        ->optionMetas(fn (): array => Subfleet::pluck('type', 'id')->all())
                        ->optionGroups(fn (): array => Subfleet::with('airline:id,name')
                            ->get(['id', 'airline_id'])
                            ->pluck('airline.name', 'id')
                            ->all())
                        ->columnSpanFull(),
                ])
                    ->columnSpanFull()
                    ->columns(),
            ]);
    }
}
