<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserState;
use App\Events\UserStateChanged;
use App\Events\UserStatsChanged;
use App\Filament\Actions\EditDetailsAction;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Concerns\StacksRelationManagers;
use App\Filament\Resources\Users\Actions\RequestEmailVerificationAction;
use App\Filament\Resources\Users\Actions\VerifyEmailAction;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Field;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Override;

/**
 * Same identity → workspace split as the flight and aircraft edit pages: who
 * the pilot is (ident, name, login, airline) is read in the overview and edited
 * only through the drawer it opens, leaving the page form for their standing,
 * placement and notes.
 */
class EditUser extends EditRecord
{
    use ReversePrimaryButtons;
    use StacksRelationManagers;

    private ?UserState $oldState = null;

    private ?int $oldRankId = null;

    protected static string $resource = UserResource::class;

    #[Override]
    public function getHeading(): string|Htmlable
    {
        /** @var User $record */
        $record = $this->getRecord();

        return new HtmlString(sprintf(
            '%s <span class="id fi-header-heading-route">· %s</span>',
            e($record->ident),
            e($record->name),
        ));
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        /** @var User $record */
        $record = $this->getRecord();

        return view('filament.shared.hero-subheading', [
            'meta' => implode(' · ', array_filter([
                $record->airline?->name,
                $record->rank?->name,
                $record->email,
                $record->hasVerifiedEmail()
                    ? __('filament.user_email_verified')
                    : __('filament.user_email_not_verified'),
            ])),
            'chip'    => ['label' => $record->state->getLabel(), 'color' => $record->state->getColor()],
            'figures' => [
                ['value' => number_format($record->flights), 'label' => trans_choice('common.flight', 2)],
                ['value' => number_format(round(($record->flight_time ?? 0) / 60)), 'label' => __('flights.flighthours')],
            ],
        ]);
    }

    /**
     * The fields, awards, type ratings and PIREPs managers are appended by the
     * trait.
     *
     * @return array<string, string>
     */
    protected function jumpBarFormSections(): array
    {
        return [
            'user-information'     => __('filament.user_information'),
            'location-information' => __('filament.location_information'),
        ];
    }

    /**
     * The identity overview sits above the jump bar.
     *
     * A closure, not an array: the content schema is built before the mounted
     * drawer action runs, so an array freezes the cards at their pre-save
     * values and the save reads as one that did not take. `getViewData()`
     * evaluates a closure at render instead.
     */
    protected function contentHeader(): array
    {
        return [
            View::make('components.admin.overview')
                ->viewData(fn (): array => [
                    'cards'      => $this->summaryCards(),
                    'ariaLabel'  => __('filament.user_information'),
                    'editAction' => $this->editAction,
                ]),
        ];
    }

    /**
     * @return array<int, array{icon: Phosphor, tint: string|null, label: string, value: string, note: string}>
     */
    protected function summaryCards(): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        $verifiedAt = $record->email_verified_at;

        return [
            [
                'icon'  => Phosphor::IdentificationCardLight,
                'tint'  => null,
                'label' => __('common.pilot_id'),
                'value' => $record->ident,
                'note'  => (string) $record->airline?->name,
            ],
            [
                'icon'  => Phosphor::UserLight,
                'tint'  => 'blue',
                'label' => __('common.name'),
                'value' => (string) $record->name,
                'note'  => (string) $record->rank?->name,
            ],
            [
                'icon'  => Phosphor::BroadcastLight,
                'tint'  => 'teal',
                'label' => __('filament.user_atc_callsign'),
                'value' => $record->atc,
                'note'  => (string) $record->callsign,
            ],
            [
                'icon'  => Phosphor::SealCheckLight,
                'tint'  => $verifiedAt === null ? 'amber' : 'violet',
                'label' => __('filament.user_email_verified'),
                'value' => $verifiedAt?->toFormattedDateString() ?? __('filament.user_email_not_verified'),
                'note'  => (string) $record->email,
            ],
        ];
    }

    /** The Edit trigger rendered inside the overview's last card. */
    public function editAction(): Action
    {
        $identityFields = UserForm::identityFields();

        $keys = array_map(
            fn (Field $field): string => $field->getName(),
            $identityFields,
        );

        return EditDetailsAction::make([
            UserForm::identityPreview(),
            ...$identityFields,
        ])
            ->modalHeading(__('filament.basic_information'))
            // name, email and password are in the model's $hidden, so the
            // record's array form arrives without them and the drawer would
            // open with two required fields empty. The password box is left
            // blank on purpose: that is how "leave the password alone" is
            // expressed, and prefilling the stored hash would hash it again.
            ->mutateRecordDataUsing(function (array $data) use ($keys): array {
                /** @var User $record */
                $record = $this->getRecord();

                return [
                    ...Arr::only($data, $keys),
                    'name'  => $record->name,
                    'email' => $record->email,
                ];
            })
            ->mutateDataUsing(function (array $data): array {
                if (filled($data['password'] ?? null)) {
                    $data['password'] = Hash::make($data['password']);
                } else {
                    unset($data['password']);
                }

                return $data;
            })
            ->extraModalFooterActions([
                DeleteAction::make()->icon(Phosphor::TrashLight)->cancelParentActions(),
            ]);
    }

    /** The identity fields live in the drawer, not the page form. */
    #[Override]
    public function form(Schema $schema): Schema
    {
        return UserForm::configure($schema, withIdentity: false);
    }

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            VerifyEmailAction::make(),
            RequestEmailVerificationAction::make(),
            // Delete lives in the settings drawer's footer (editAction()).
            ForceDeleteAction::make()->icon(Phosphor::TrashSimpleLight),
            RestoreAction::make()->icon(Phosphor::ArrowUUpLeftLight),
        ];
    }

    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record instanceof User) {
            // notes is in the model's $hidden, so it is missing from the
            // record's array form. Left out, the field renders empty and the
            // next save writes that emptiness over what the pilot's file said.
            $data['notes'] = $this->record->notes;
            $data['transfer_time'] = $this->record->transfer_time / 60;
        }

        return $data;
    }

    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['transfer_time'] *= 60;

        return $data;
    }

    protected function beforeSave(): void
    {
        if ($this->record instanceof User) {
            $this->oldState = $this->record->state;
            $this->oldRankId = $this->record->rank_id;
        }
    }

    protected function afterSave(): void
    {
        if ($this->record instanceof User && $this->oldState !== $this->record->state) {
            event(new UserStateChanged($this->record, $this->oldState));
        }

        if ($this->record instanceof User && $this->oldRankId !== $this->record->rank_id) {
            event(new UserStatsChanged($this->record, 'rank', $this->oldRankId));
        }
    }
}
