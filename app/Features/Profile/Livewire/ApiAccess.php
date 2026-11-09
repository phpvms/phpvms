<?php

declare(strict_types=1);

namespace App\Features\Profile\Livewire;

use App\Features\Profile\Actions\RegenerateApiKey;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The "API access" card: the legacy API key (shown or hidden in the browser)
 * and its regeneration. The API connections drawer is `ApiConnections`.
 */
class ApiAccess extends Component
{
    #[Computed]
    public function apiKey(): ?string
    {
        return $this->pilot()->api_key;
    }

    public function regenerate(): void
    {
        app(RegenerateApiKey::class)->handle($this->pilot());

        unset($this->apiKey);

        $this->dispatch('toast', kind: 'success', text: 'New API key generated!');
    }

    public function render(): View
    {
        return view('livewire.profile.api-access');
    }

    private function pilot(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
