<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthClients\Pages;

use App\Filament\Resources\OAuthClients\OAuthClientResource;
use App\Models\OauthClient;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Override;

class EditOAuthClient extends EditRecord
{
    protected static string $resource = OAuthClientResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            $this->rotateSecretAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * Rotate the client's secret, revealing the new plaintext once. Only shown
     * for confidential clients — public (PKCE) clients have no secret.
     */
    private function rotateSecretAction(): Action
    {
        return Action::make('rotateSecret')
            ->label(__('oauth.rotate_secret'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('oauth.rotate_secret_confirm'))
            ->visible(fn (): bool => $this->record instanceof OauthClient && $this->record->confidential())
            ->action(function (): void {
                /** @var OauthClient $client */
                $client = $this->record;

                $plain = Str::random(40);
                // The `secret` cast hashes the value and stashes the plaintext
                // on $client->plainSecret; we reveal $plain directly here.
                $client->forceFill(['secret' => $plain])->save();

                Notification::make()
                    ->title(__('oauth.secret_rotated_title'))
                    ->body(__('oauth.secret_created_body', [
                        'id'     => $client->getKey(),
                        'secret' => $plain,
                    ]))
                    ->persistent()
                    ->warning()
                    ->send();
            });
    }
}
