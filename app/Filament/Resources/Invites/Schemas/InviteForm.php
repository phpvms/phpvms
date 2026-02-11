<?php

namespace App\Filament\Resources\Invites\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InviteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label(__('common.email'))
                    ->email()
                    ->live()
                    ->helperText(__('filament.invite_email_hint')),

                DateTimePicker::make('expires_at')
                    ->native(false)
                    ->minDate(now()->addHour())
                    ->label(__('common.expires_at'))
                    ->placeholder(__('common.never')),

                TextInput::make('usage_limit')
                    ->label(__('invites.usage_limit'))
                    ->numeric()
                    ->minValue(1)
                    ->disabled(fn (Get $get): bool => $get('email') !== null && $get('email') !== '')
                    ->placeholder(function (Get $get): string {
                        if ($get('email') !== null && $get('email') !== '') {
                            return '1';
                        }

                        return __('invites.no_limit');
                    }),

                Toggle::make('email_link')
                    ->label(__('invites.email_link'))
                    ->helperText(__('filament.invite_email_link_hint'))
                    ->disabled(fn (Get $get): bool => $get('email') === null || $get('email') === '')
                    ->offIcon(Heroicon::XCircle)
                    ->offColor('danger')
                    ->onIcon(Heroicon::CheckCircle)
                    ->onColor('success'),
            ])
            ->columns();
    }
}
