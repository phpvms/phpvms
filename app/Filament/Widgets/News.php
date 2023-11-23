<?php

namespace App\Filament\Widgets;

use App\Models\News as NewsModel;
use Filament\Forms;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class News extends BaseWidget
{
    protected static ?string $pollingInterval = null;
    public function table(Table $table): Table
    {
        return $table
            ->query(
                NewsModel::orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\Layout\Grid::make()->schema([
                    Tables\Columns\TextColumn::make('subject')
                        ->size(Tables\Columns\TextColumn\TextColumnSize::Large)
                        ->weight(FontWeight::Bold),

                    Tables\Columns\TextColumn::make('created_at')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->size(Tables\Columns\TextColumn\TextColumnSize::Small)
                        ->alignEnd()
                        ->formatStateUsing(fn (string $state): string => 'Delete')
                        ->action(
                            Action::make('delete')
                                ->requiresConfirmation()
                                ->color('danger')
                                ->action(fn (NewsModel $record) => $record->delete())
                        ),

                    Tables\Columns\TextColumn::make('body')
                        ->columnSpan(2)
                        ->html(),

                    Tables\Columns\TextColumn::make('user.name')
                        ->formatStateUsing(fn (NewsModel $record): string => $record->user->name . ' - ' . $record->created_at->diffForHumans())
                        ->columnSpan(2)
                        ->alignEnd(),
                ])->columns(2)
            ])
            ->headerActions([
              Tables\Actions\CreateAction::make('create')
                  ->label('Add News')
                  ->icon('heroicon-o-plus-circle')
                  ->size(ActionSize::Small)
                  ->model(NewsModel::class)
                  ->form([
                      Forms\Components\TextInput::make('subject')
                          ->string()
                          ->required(),
                      Forms\Components\RichEditor::make('body')
                          ->required(),
                    ])
                  ->mutateFormDataUsing(function (array $data): array {
                      $data['user_id'] = Auth::id();

                      return $data;
                  }),
            ]);
    }
}
