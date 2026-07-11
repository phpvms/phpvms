<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthClients\Pages;

use App\Filament\Resources\OAuthClients\OAuthClientResource;
use App\Models\OauthClient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\ClientRepository;
use Override;

class CreateOAuthClient extends CreateRecord
{
    protected static string $resource = OAuthClientResource::class;

    /**
     * The plain-text secret, captured once at creation for the one-time reveal.
     */
    private ?string $plainSecret = null;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $clients = app(ClientRepository::class);
        $name = $data['name'];
        $redirectUris = $data['redirect_uris'] ?? [];

        $client = match ($data['client_type']) {
            'pkce'               => $clients->createAuthorizationCodeGrantClient($name, $redirectUris, confidential: false),
            'client_credentials' => $clients->createClientCredentialsGrantClient($name),
            default              => $clients->createAuthorizationCodeGrantClient($name, $redirectUris, confidential: true),
        };

        // Capture the plaintext secret now — it is hashed at rest and cannot be
        // recovered later (see the one-time reveal in afterCreate()).
        $this->plainSecret = $client->plainSecret;

        return OauthClient::findOrFail($client->getKey());
    }

    protected function afterCreate(): void
    {
        if ($this->plainSecret === null) {
            // Public (PKCE) client — no secret to reveal.
            return;
        }

        Notification::make()
            ->title(__('oauth.secret_created_title'))
            ->body(__('oauth.secret_created_body', [
                'id'     => $this->record->getKey(),
                'secret' => $this->plainSecret,
            ]))
            ->persistent()
            ->warning()
            ->send();
    }
}
