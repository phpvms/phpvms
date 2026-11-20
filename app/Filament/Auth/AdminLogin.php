<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Override;

class AdminLogin extends Login
{
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
}
