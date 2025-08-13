<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_field_id')
                    ->label(trans_choice('common.field', 1))
                    ->relationship('field', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('value')
                    ->label(trans_choice('common.value', 1))
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('field', fn (Builder $subQuery) => $subQuery->where('internal', false)))
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name')),

                TextInputColumn::make('value')
                    ->label(trans_choice('common.value', 1)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, string $model) {
                        $this->getOwnerRecord()->fields()->updateOrCreate([
                            'user_field_id' => $data['user_field_id'],
                        ], $data);
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
            ]);
    }

    public static function getModelLabel(): string
    {
        return trans_choice('common.field', 1);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans_choice('common.field', 2);
    }
}
