<?php

namespace App\Filament\Widgets;

use App\Events\NewsAdded;
use App\Events\NewsUpdated;
use App\Models\News as NewsModel;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class News extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $pollingInterval = null;

    private function formContent(): array
    {
        return [
            Forms\Components\TextInput::make('subject')
                ->string()
                ->required(),
            Forms\Components\RichEditor::make('body')
                ->required(),
            Forms\Components\Toggle::make('send_notifications')
                ->onColor('success')
                ->onIcon('heroicon-m-check-circle')
                ->offColor('danger')
                ->offIcon('heroicon-m-x-circle'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                NewsModel::orderBy('created_at', 'desc')
            )
            ->paginated([2, 10, 25, 50, 100, 'all'])
            ->defaultPaginationPageOption(2)
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('subject')
                        ->size(Tables\Columns\TextColumn\TextColumnSize::Large)
                        ->weight(FontWeight::Bold),

                    Tables\Columns\TextColumn::make('body')
                        ->color('gray')
                        ->html(),

                    Tables\Columns\TextColumn::make('user.name')
                        ->formatStateUsing(fn (NewsModel $record): string => $record->user->name.' - '.$record->created_at->diffForHumans())
                        ->alignEnd(),
                ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->form($this->formContent())
                        ->mutateFormDataUsing(function (array $data): array {
                            $data['user_id'] = Auth::id();

                            return $data;
                        })
                        ->after(function (array $data, NewsModel $record) {
                            if (get_truth_state($data['send_notifications'])) {
                                event(new NewsUpdated($record));
                            }
                        }),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make('create')
                    ->label('Add News')
                    ->icon('heroicon-o-plus-circle')
                    ->size(ActionSize::Small)
                    ->model(NewsModel::class)
                    ->form($this->formContent())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();

                        return $data;
                    })
                    ->after(function (array $data, NewsModel $record) {
                        if (get_truth_state($data['send_notifications'])) {
                            event(new NewsAdded($record));
                        }
                    }),
            ]);
    }
}
