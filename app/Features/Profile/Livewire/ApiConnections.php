<?php

declare(strict_types=1);

namespace App\Features\Profile\Livewire;

use App\Features\Profile\Actions\CreatePersonalToken;
use App\Features\Profile\Actions\RevokeAuthorizedApp;
use App\Features\Profile\Actions\RevokeToken;
use App\Http\Data\ApiConnectionsData;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The API connections drawer: create a personal access token (its plaintext is
 * shown once), list active tokens and authorized apps, and revoke either.
 *
 * The lists load only while the drawer is open, and again after every change.
 */
class ApiConnections extends Component
{
    public bool $open = false;

    public string $name = '';

    /** @var list<string> */
    public array $scopes = [];

    /** The new token, shown once. Cleared when the drawer closes or opens. */
    public ?string $plainTextToken = null;

    #[On('api-connections.open')]
    public function show(): void
    {
        $this->plainTextToken = null;
        $this->open = true;
    }

    public function updatedOpen(bool $open): void
    {
        if (!$open) {
            $this->plainTextToken = null;
        }
    }

    #[Computed]
    public function connections(): ?ApiConnectionsData
    {
        return $this->open ? ApiConnectionsData::fromUser($this->pilot()) : null;
    }

    public function createToken(): void
    {
        $this->validate([
            'name'     => ['required', 'string', 'max:255'],
            'scopes'   => ['nullable', 'array'],
            'scopes.*' => ['string'],
        ]);

        $this->plainTextToken = app(CreatePersonalToken::class)->handle($this->pilot(), $this->name, $this->scopes);

        $this->reset('name', 'scopes');
        unset($this->connections);
    }

    public function revokeToken(string $tokenId): void
    {
        app(RevokeToken::class)->handle($this->pilot(), $tokenId);

        unset($this->connections);
        $this->dispatch('toast', kind: 'success', text: 'Token revoked.');
    }

    public function revokeApp(string $clientId): void
    {
        app(RevokeAuthorizedApp::class)->handle($this->pilot(), $clientId);

        unset($this->connections);
        $this->dispatch('toast', kind: 'success', text: 'Application access revoked.');
    }

    public function render(): View
    {
        return view('livewire.profile.api-connections');
    }

    private function pilot(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
