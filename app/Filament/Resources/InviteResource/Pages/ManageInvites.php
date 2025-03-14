<?php

namespace App\Filament\Resources\InviteResource\Pages;

use App\Filament\Resources\InviteResource;
use App\Models\Invite;
use App\Notifications\Messages\InviteLink;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Notification;

class ManageInvites extends ManageRecords
{
    protected static string $resource = InviteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Invite')
                ->icon('heroicon-o-plus-circle')
                ->mutateFormDataUsing(function (array $data): array {
                    if (!is_null($data['email'])) {
                        $data['usage_limit'] = 1;
                    }

                    $data['token'] = sha1(hrtime(true).\Illuminate\Support\Str::random());

                    return $data;
                })
                ->after(function (Invite $record, array $data): void {
                    if (!is_null($record->email) && !is_null($data['email_link']) && get_truth_state($data['email_link'])) {
                        Notification::route('mail', $record->email)
                            ->notify(new InviteLink($record));
                    }
                }),
        ];
    }
}
