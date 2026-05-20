<?php

namespace App\Filament\Resources\News\Tables;

use App\Events\NewsUpdated;
use App\Filament\Resources\News\Schemas\NewsForm;
use App\Models\News;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('subject')
                    ->label(__('filament.news_subject'))
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                TextColumn::make('user.name')
                    ->label(trans_choice('common.user', 1))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('common.created_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(fn (Schema $schema): Schema => NewsForm::configure($schema))
                    ->after(function (array $data, News $record): void {
                        if (get_truth_state($data['send_notifications'] ?? false)) {
                            event(new NewsUpdated($record));
                        }
                    }),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
