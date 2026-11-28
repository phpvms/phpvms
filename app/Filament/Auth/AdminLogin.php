<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Http\Middleware\SetupGate;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Override;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AdminLogin extends Login
{
    #[Override]
    public function authenticate(): ?LoginResponse
    {
        if (($target = $this->upgradeRedirect()) !== null) {
            $this->redirect($target);

            return null;
        }

        return parent::authenticate();
    }

    // Named inputs with standard autocomplete tokens let password managers autofill.
    #[Override]
    protected function getEmailFormComponent(): Component
    {
        $component = parent::getEmailFormComponent();
        assert($component instanceof TextInput);

        return $component
            ->autocomplete('username')
            ->extraInputAttributes(['name' => 'email']);
    }

    #[Override]
    protected function getPasswordFormComponent(): Component
    {
        $component = parent::getPasswordFormComponent();
        assert($component instanceof TextInput);

        return $component->extraInputAttributes(['name' => 'password']);
    }

    // The submit goes to /livewire/update, outside the panel middleware.
    private function upgradeRedirect(): ?string
    {
        $response = app(SetupGate::class)->answer(request());

        return $response instanceof RedirectResponse ? $response->getTargetUrl() : null;
    }
}
