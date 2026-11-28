<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Features\Setup\Actions\CompleteSetup;
use App\Models\User;
use App\Support\Countries;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Override;
use Throwable;

/**
 * First-run wizard for the first admin: airline, saved by {@see CompleteSetup}.
 *
 * @property-read Schema $form
 */
class SetupWizard extends Page
{
    protected static ?string $slug = 'setup';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    #[Override]
    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->hasAdminAccess() ?? false;
    }

    #[Override]
    public function getTitle(): string
    {
        return __('installer.setup_wizard.title');
    }

    public function mount(): void
    {
        if (setting('general.setup_completed', true)) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('installer.setup_wizard.save'))
                            ->submit('save'),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('airline_icao')
                    ->label('ICAO')
                    ->maxLength(8)
                    ->string()
                    ->required()
                    ->unique('airlines', 'icao'),

                TextInput::make('airline_name')
                    ->label(__('common.name'))
                    ->string()
                    ->required(),

                Select::make('airline_country')
                    ->label(__('common.country'))
                    ->options(Countries::getSelectList())
                    ->native(false)
                    ->required()
                    ->searchable(),
            ])->columns();
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $user = Filament::auth()->user();

        if (!$user instanceof User) {
            return;
        }

        try {
            app(CompleteSetup::class)->handle($user, [
                'icao'    => $state['airline_icao'],
                'name'    => $state['airline_name'],
                'country' => $state['airline_country'],
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            Notification::make()
                ->title(__('installer.setup_wizard.failed'))
                ->body($throwable->getMessage())
                ->danger()
                ->send();

            return;
        }

        // A null result means another tab already completed setup.
        $this->redirect(Filament::getUrl());
    }
}
