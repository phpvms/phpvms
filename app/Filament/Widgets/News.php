<?php

namespace App\Filament\Widgets;

use App\Events\NewsAdded;
use App\Events\NewsUpdated;
use App\Models\News as NewsModel;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class News extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    private function formContent(): array
    {
        return [
            TextInput::make('subject')
                ->string()
                ->required(),
            RichEditor::make('body')
                ->required(),
            Toggle::make('send_notifications')
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
                Stack::make([
                    TextColumn::make('subject')
                        ->size(TextSize::Large)
                        ->weight(FontWeight::Bold),

                    TextColumn::make('body')
                        ->color('gray')
                        ->html(),

                    TextColumn::make('user.name')
                        ->formatStateUsing(fn (NewsModel $record): string => $record->user->name.' - '.$record->created_at->diffForHumans())
                        ->alignEnd(),
                ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->schema($this->formContent())
                        ->mutateDataUsing(function (array $data): array {
                            $data['user_id'] = Auth::id();

                            return $data;
                        })
                        ->after(function (array $data, NewsModel $record) {
                            if (get_truth_state($data['send_notifications'])) {
                                event(new NewsUpdated($record));
                            }
                        }),

                    DeleteAction::make(),
                ]),
            ])
            ->headerActions([
                CreateAction::make('create')
                    ->label('Add News')
                    ->icon('heroicon-o-plus-circle')
                    ->size(Size::Small)
                    ->model(NewsModel::class)
                    ->schema($this->formContent())
                    ->mutateDataUsing(function (array $data): array {
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
