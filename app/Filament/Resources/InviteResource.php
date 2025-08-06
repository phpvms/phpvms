<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InviteResource\Pages\ManageInvites;
use App\Models\Invite;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InviteResource extends Resource
{
    protected static ?string $model = Invite::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->email()
                    ->live()
                    ->helperText('If empty all emails will be allowed to register using the link.'),

                DateTimePicker::make('expires_at')
                    ->native(false)
                    ->minDate(now()->addHour())
                    ->placeholder('Never'),

                TextInput::make('usage_limit')
                    ->numeric()
                    ->minValue(1)
                    ->disabled(fn (Get $get): bool => $get('email') !== null && $get('email') !== '')
                    ->placeholder(function (Get $get): string {
                        if ($get('email') !== null && $get('email') !== '') {
                            return '1';
                        }

                        return 'No Limit';
                    }),

                Toggle::make('email_link')
                    ->label('Email Invite Link')
                    ->helperText('If enabled an email will be sent to the email address above with the invite link.')
                    ->default(false)
                    ->disabled(fn (Get $get): bool => $get('email') === null || $get('email') === '')
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
                TextColumn::make('token')
                    ->badge()
                    ->label('Invite Type')
                    ->formatStateUsing(fn (Invite $record): string => is_null($record->email) ? 'Link' : 'Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('link')
                    ->label('Invited Email/Invite Link')
                    ->formatStateUsing(fn (Invite $record): string => is_null($record->email) ? 'Copy Link' : $record->email)
                    ->copyable(fn (Invite $record): bool => is_null($record->email)),

                TextColumn::make('usage_count')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->formatStateUsing(fn (Invite $record): string => is_null($record->usage_limit) ? 'No Limit' : $record->usage_limit)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->formatStateUsing(fn (Invite $record): string => is_null($record->expires_at) ? 'Never' : $record->expires_at->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInvites::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return setting('general.invite_only_registrations', false);
    }
}
