<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PirepResource\Pages;
use App\Filament\Resources\PirepResource\RelationManagers;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use App\Support\Units\Time;
use App\Services\PirepService;
use Filament\Tables\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PirepResource extends Resource
{
    protected static ?string $model = Pirep::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    public static function getNavigationBadge(): ?string
    {
        return Pirep::where('state', PirepState::PENDING)->count() > 0
            ? Pirep::where('state', PirepState::PENDING)->count()
            : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ident')->label('Flight Ident')->searchable(),
                TextColumn::make('user.name')->label('Pilot')->searchable(),
                TextColumn::make('dpt_airport_id')->label('DEP')->searchable(),
                TextColumn::make('arr_airport_id')->label('ARR')->searchable(),
                TextColumn::make('flight_time')->formatStateUsing(fn (int $state): string => Time::minutesToTimeString($state)),
                TextColumn::make('aircraft')->formatStateUsing(fn (Pirep $record): string => $record->aircraft->registration .' - '. $record->aircraft->name),
                TextColumn::make('source')->label('Filed Using')->formatStateUsing(fn (int $state): string => PirepSource::label($state)),
                TextColumn::make('state')->badge()->color(fn (int $state): string => match ($state) {
                    PirepState::PENDING => 'warning',
                    PirepState::ACCEPTED => 'success',
                    PirepState::REJECTED => 'danger',
                    default => 'info',
                })->formatStateUsing(fn (int $state): string => PirepState::label($state)),
                TextColumn::make('submitted_at')->dateTime('d-m-Y H:i')->label('File Date'),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                Filter::make('submitted_at')
                    ->form([
                        DatePicker::make('filed_from'),
                        DatePicker::make('filed_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['filed_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                $data['filed_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '<=', $date),
                            );
                    })
            ])
            ->actions([

                Action::make('accept')
                    ->color('success')
                    ->icon('heroicon-m-check-circle')
                    ->label('Accept')
                    ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::REJECTED))
                    ->action(fn (Pirep $record) => app(PirepService::class)->changeState($record, PirepState::ACCEPTED)),

                Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-m-x-circle')
                    ->label('Reject')
                    ->visible(fn (Pirep $record): bool => ($record->state === PirepState::PENDING || $record->state === PirepState::ACCEPTED))
                    ->action(fn (Pirep $record) => app(PirepService::class)->changeState($record, PirepState::REJECTED)),

                EditAction::make()->form([

                ]),

                DeleteAction::make(),

                Action::make('view')
                    ->color('info')
                    ->icon('heroicon-m-eye')
                    ->label('View Pirep')
                    ->url(fn (Pirep $record): string => route('frontend.pireps.show', $record->id))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPireps::route('/'),
        ];
    }
}
