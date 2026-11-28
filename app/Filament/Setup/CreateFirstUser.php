<?php

declare(strict_types=1);

namespace App\Filament\Setup;

use App\Features\Setup\Actions\CreateFirstUser as CreateFirstUserAction;
use App\Features\Setup\Actions\ResolveSiteState;
use App\Features\Setup\Enums\SiteState;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rules\Password;
use Override;

/**
 * Creates the super admin of a new site. It is not Filament's registration, which
 * would add a register link to the login page.
 *
 * @property-read Schema $form
 */
class CreateFirstUser extends SimplePage
{
    protected static ?string $slug = 'first-user';

    protected Width|string|null $maxContentWidth = Width::Large;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return app(ResolveSiteState::class)->handle() === SiteState::FirstUser;
    }

    public static function path(): string
    {
        return url('/'.Filament::getPanel('admin')->getPath().'/first-user');
    }

    // Not checked again on submit: a visitor who lost the race is sent to login, not shown a 403.
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill();
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $user = app(CreateFirstUserAction::class)->handle([
            'name'     => $data['name'] ?? null,
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);

        // Nothing created: either someone else won the race, or the setup lock is held.
        if (!$user instanceof User && !User::withTrashed()->exists()) {
            Notification::make()->title(__('installer.first_user.busy'))->warning()->send();

            return;
        }

        $this->redirect(Filament::getPanel('admin')->getLoginUrl());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('name')
                    ->label(__('common.name'))
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('email')
                    ->label(__('common.email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->label(__('installer.first_user.password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->showAllValidationMessages()
                    ->same('passwordConfirmation')
                    ->validationAttribute(__('installer.first_user.password')),
                TextInput::make('passwordConfirmation')
                    ->label(__('installer.first_user.password_confirmation'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('create')
                ->footer([
                    Actions::make([
                        Action::make('create')
                            ->label(__('installer.first_user.submit'))
                            ->submit('create'),
                    ])->fullWidth()->key('form-actions'),
                ]),
        ]);
    }

    #[Override]
    public function getTitle(): string|Htmlable
    {
        return __('installer.first_user.title');
    }

    #[Override]
    public function getHeading(): string|Htmlable|null
    {
        return __('installer.first_user.title');
    }
}
