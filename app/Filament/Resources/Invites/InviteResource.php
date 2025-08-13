<?php

namespace App\Filament\Resources\Invites;

use App\Filament\Resources\Invites\Pages\ManageInvites;
use App\Filament\Resources\Invites\Schemas\InviteForm;
use App\Filament\Resources\Invites\Tables\InvitesTable;
use App\Models\Invite;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InviteResource extends Resource
{
    protected static ?string $model = Invite::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return InviteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvitesTable::configure($table);
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

    public static function getModelLabel(): string
    {
        return __('common.invite');
    }
}
