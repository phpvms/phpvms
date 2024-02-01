<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InviteResource\Pages;
use App\Models\Invite;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InviteResource extends Resource
{
    protected static ?string $model = Invite::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->live()
                    ->helperText('If empty all emails will be allowed to register using the link.'),

                Forms\Components\DateTimePicker::make('expires_at')
                    ->native(false)
                    ->minDate(now()->addHour())
                    ->placeholder('Never'),

                Forms\Components\TextInput::make('usage_limit')
                    ->numeric()
                    ->minValue(1)
                    ->disabled(fn (Forms\Get $get): bool => $get('email') !== null && $get('email') !== '')
                    ->placeholder(function (Forms\Get $get): string {
                        if ($get('email') !== null && $get('email') !== '') {
                            return '1';
                        }

                        return 'No Limit';
                    }),

                Forms\Components\Toggle::make('email_link')
                    ->label('Email Invite Link')
                    ->helperText('If enabled an email will be sent to the email address above with the invite link.')
                    ->default(false)
                    ->disabled(fn (Forms\Get $get): bool => $get('email') === null || $get('email') === '')
                    ->offIcon('heroicon-m-x-circle')
                    ->offColor('danger')
                    ->onIcon('heroicon-m-check-circle')
                    ->onColor('success'),
            ])
            ->columns();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('token')
                    ->badge()
                    ->label('Invite Type')
                    ->formatStateUsing(fn (Invite $record): string => is_null($record->email) ? 'Link' : 'Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('link')
                    ->label('Invited Email/Invite Link')
                    ->formatStateUsing(fn (Invite $record): string => is_null($record->email) ? 'Copy Link' : $record->email)
                    ->copyable(fn (Invite $record): bool => is_null($record->email)),

                Tables\Columns\TextColumn::make('usage_count')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->formatStateUsing(fn (Invite $record): string => is_null($record->usage_limit) ? 'No Limit' : $record->usage_limit)
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->formatStateUsing(fn (Invite $record): string => is_null($record->expires_at) ? 'Never' : $record->expires_at->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInvites::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return setting('general.invite_only_registrations', false);
    }
}
