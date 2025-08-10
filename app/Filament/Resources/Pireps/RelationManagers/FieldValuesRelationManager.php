<?php

namespace App\Filament\Resources\Pireps\RelationManagers;

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
use Illuminate\Database\Eloquent\Model;
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
                Select::make('name')
                    ->label(__('common.name'))
                    ->required()
                    ->options($pirepFields),

                TextInput::make('value')
                    ->label(trans_choice('common.value', 1))
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name')),

                TextInputColumn::make('value')
                    ->label(trans_choice('common.value', 1))
                    ->disabled(fn (PirepFieldValue $record): bool => $record->pirep->read_only),

                TextColumn::make('source')
                    ->label(__('pireps.source'))
                    ->formatStateUsing(fn (int $state): string => PirepSource::label($state)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden($this->getOwnerRecord()->read_only)
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

    public static function getModelLabel(): string
    {
        return trans_choice( 'pireps.field_value', 1);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans_choice('pireps.field_value', 2);
    }
}
