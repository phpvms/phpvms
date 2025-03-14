<?php

namespace App\Filament\Resources\PirepResource\RelationManagers;

use App\Models\Enums\PirepSource;
use App\Models\PirepField;
use App\Models\PirepFieldValue;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FieldValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'field_values';

    public function form(Form $form): Form
    {
        $pirepFieldValues = PirepFieldValue::where('pirep_id', $this->getOwnerRecord()->id)->pluck('name');

        $pirepFields = PirepField::whereNotIn('name', $pirepFieldValues)->pluck('name', 'name')->toArray();

        return $form
            ->schema([
                Forms\Components\Select::make('name')->required()->options($pirepFields),
                Forms\Components\TextInput::make('value')->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextInputColumn::make('value')->disabled(fn (PirepFieldValue $record): bool => $record->pirep->read_only),
                Tables\Columns\TextColumn::make('source')->formatStateUsing(fn (int $state): string => PirepSource::label($state)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Add Pirep Field Value')->hidden($this->getOwnerRecord()->read_only)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['pirep_id'] = $this->getOwnerRecord()->id;
                        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);

                        return $data;
                    }),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}
