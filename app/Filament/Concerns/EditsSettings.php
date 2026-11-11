<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Support\SettingFieldFactory;
use App\Support\Settings\SettingDefinition;
use App\Support\SettingsTabs;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The settings-page form shared by the core and addon settings pages: a
 * sticky category nav beside one section per settings section, each with its
 * own Save, plus a Save for the whole page.
 *
 * The page supplies the settings to show, how to save them and the labels;
 * fields come from {@see SettingFieldFactory} and are keyed by setting key, so
 * the form state of `general.site_name` is `data.general.site_name`.
 *
 * @property-read Schema $form
 */
trait EditsSettings
{
    use FormActionIcons;

    public ?array $data = [];

    public string $previousUrl;

    /**
     * The settings the page edits: visible ones only.
     *
     * @return Collection<int, SettingDefinition>
     */
    abstract protected function settingDefinitions(): Collection;

    /**
     * The stored value of one setting.
     */
    abstract protected function settingValue(SettingDefinition $definition): mixed;

    /**
     * Save form values, keyed by setting key, in one transaction.
     *
     * @param  array<string, mixed> $values
     * @return string|null          an extra line for the success notification
     *
     * @throws ValidationException
     */
    abstract protected function persistSettings(array $values): ?string;

    abstract protected function sectionLabel(string $section): string;

    abstract protected function cancelUrl(): string;

    public function mount(): void
    {
        $this->fillForm();
        $this->previousUrl = url()->previous();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    $this->getFormActionsComponents(),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data');
    }

    protected function fillForm(): void
    {
        $values = [];

        foreach ($this->settingDefinitions() as $definition) {
            $values[$definition->key] = SettingFieldFactory::formValue($this->settingValue($definition));
        }

        $this->form->fill(Arr::undot($values));
    }

    public function save(): void
    {
        abort_unless(static::canEdit(), 403);

        try {
            $this->saveValues(trans_choice('common.setting', 2).' saved successfully', Arr::dot($this->form->getState()));
        } catch (Halt) {
            return;
        }
    }

    /**
     * Saves one section only. The whole form is still validated (Filament's
     * getState() validates everything), but only that section's settings are
     * written, whatever else was edited.
     */
    public function saveGroup(string $section): void
    {
        abort_unless(static::canEdit(), 403);

        try {
            $keys = $this->settingDefinitions()
                ->filter(fn (SettingDefinition $definition): bool => $definition->section === $section)
                ->map(fn (SettingDefinition $definition): string => $definition->key)
                ->all();

            $this->saveValues($this->sectionLabel($section).' settings saved', Arr::only(Arr::dot($this->form->getState()), $keys));
        } catch (Halt) {
            return;
        }
    }

    /**
     * @param array<string, mixed> $values
     *
     * @throws ValidationException with each message keyed on its field's state path
     */
    private function saveValues(string $title, array $values): void
    {
        try {
            $body = $this->persistSettings($values);
        } catch (ValidationException $validationException) {
            throw ValidationException::withMessages(
                collect($validationException->errors())->mapWithKeys(fn (array $messages, string $key): array => ['data.'.$key => $messages])->all()
            );
        }

        Notification::make()
            ->success()
            ->title($title)
            ->body($body)
            ->send();
    }

    /**
     * Whether the Save actions are shown. They are always enforced by
     * {@see save()} and {@see saveGroup()} whatever this returns.
     */
    protected function showsSaveActions(): bool
    {
        return true;
    }

    public function getFormActionsComponents(): Actions
    {
        return Actions::make($this->reversePrimaryButtons([
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ]));
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->submit('save')
            ->visible($this->showsSaveActions())
            ->keyBindings(['mod+s']);
    }

    protected function getCancelFormAction(): Action
    {
        $url = $this->previousUrl ?? $this->cancelUrl();

        return Action::make('cancel')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
            ->alpineClickHandler(
                FilamentView::hasSpaMode($url)
                    ? 'document.referrer ? window.history.back() : Livewire.navigate('.Js::from($url).')'
                    : 'document.referrer ? window.history.back() : (window.location.href = '.Js::from($url).')',
            )
            ->color('gray');
    }

    /**
     * @return array<int, Grid>
     */
    protected function getFormSchema(): array
    {
        $sections = [];
        $navLinks = [];
        $ids = [];

        foreach (SettingsTabs::group($this->settingDefinitions()) as $section => $settings) {
            $section = (string) $section;
            $id = $this->sectionId($section, $ids);
            $navLinks[] = ['id' => $id, 'label' => $this->sectionLabel($section)];

            // Not collapsible: the heads are plain headings and the nav is how
            // you move around, so a stray click on a title would only hide it.
            $sections[] = Section::make($this->sectionLabel($section))
                ->id($id)
                ->key($id)
                ->schema($settings->map(fn (SettingDefinition $definition): Field => SettingFieldFactory::make($definition))->all())
                // Flex, not ->alignment() on Actions: .fi-ac.fi-align-end is
                // row-reverse. The Flex does the pushing right.
                ->footer([
                    Flex::make([
                        Actions::make([
                            Action::make('saveSection-'.$id)
                                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                                ->icon(Phosphor::FloppyDiskLight)
                                ->visible($this->showsSaveActions())
                                ->action(fn () => $this->saveGroup($section)),
                        ])->grow(false),
                    ])->grow()->alignment(Alignment::End),
                ]);
        }

        // The nav column and the category sections sit side by side from lg up
        // (see .settings-page in the admin theme); one outer Section holds
        // the categories so the page stays a single sheet region.
        return [
            Grid::make(1)
                ->extraAttributes(['class' => 'settings-page'])
                ->schema([
                    View::make('filament.pages.settings.nav')
                        ->viewData(['links' => $navLinks]),
                    Section::make()
                        ->schema([View::make('filament.pages.settings.empty'), ...$sections]),
                ])
                ->columnSpanFull()
                ->disabled(!static::canEdit()),
        ];
    }

    /**
     * A section id unique on the page. The id also keys the section and names
     * its Save action, and the nav finds sections by id, so sections whose
     * names slug alike ("Fuel", "fuel") get a numbered suffix instead.
     *
     * @param array<string, true> $taken ids already used on the page
     */
    private function sectionId(string $section, array &$taken): string
    {
        $slug = Str::slug($section);
        $base = 'settings-'.($slug === '' ? 'group' : $slug);
        $id = $base;

        for ($suffix = 2; isset($taken[$id]); $suffix++) {
            $id = $base.'-'.$suffix;
        }

        $taken[$id] = true;

        return $id;
    }
}
