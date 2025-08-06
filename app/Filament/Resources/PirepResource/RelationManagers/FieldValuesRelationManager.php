<?php

namespace App\Filament\Resources\PirepResource\RelationManagers;

use App\Models\Enums\PirepSource;
use App\Models\PirepField;
use App\Models\PirepFieldValue;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class FieldValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'field_values';

    public function form(Schema $schema): Schema
    {
        $pirepFieldValues = PirepFieldValue::where('pirep_id', $this->getOwnerRecord()->id)->pluck('name');

        $pirepFields = PirepField::whereNotIn('name', $pirepFieldValues)->pluck('name', 'name')->toArray();

        return $schema
            ->components([
                Select::make('name')->required()->options($pirepFields),
                TextInput::make('value')->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextInputColumn::make('value')->disabled(fn (PirepFieldValue $record): bool => $record->pirep->read_only),
                TextColumn::make('source')->formatStateUsing(fn (int $state): string => PirepSource::label($state)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()->label('Add Pirep Field Value')->hidden($this->getOwnerRecord()->read_only)
                    ->mutateDataUsing(function (array $data): array {
                        $data['pirep_id'] = $this->getOwnerRecord()->id;
                        $data['slug'] = Str::slug($data['name']);

                        return $data;
                    }),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
