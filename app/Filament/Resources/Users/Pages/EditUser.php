<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserState;
use App\Events\UserStateChanged;
use App\Events\UserStatsChanged;
use App\Filament\Concerns\HasRelationManagerTabs;
use App\Filament\Concerns\HasSaveBar;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Override;

/**
 * Who the pilot is (ident, name, login, airline) is read in the overview and
 * edited in the page form's User Information section.
 */
class EditUser extends EditRecord
{
    use HasRelationManagerTabs;
    use HasSaveBar {
        afterSave as saveBarAfterSave;
    }

    private ?UserState $oldState = null;

    private ?int $oldRankId = null;

    protected static string $resource = UserResource::class;

    #[Override]
    public function getHeading(): string|Htmlable
    {
        /** @var User $record */
        $record = $this->getRecord();

        return new HtmlString(sprintf(
            '<span class="fi-header-heading-ident">%s</span> <span class="id fi-header-heading-name">· %s</span>%s',
            e($record->ident),
            e($record->name),
            $record->rank
                ? sprintf('<span class="fi-header-heading-rank">%s</span>', e($record->rank->name))
                : '',
        ));
    }

    /**
     * The identity overview sits at the top of the Details tab.
     *
     * A closure, not an array: the content schema is built before a save
     * runs, so an array freezes the cards at their pre-save values and the
     * save reads as one that did not take. `getViewData()` evaluates a closure
     * at render instead.
     */
    protected function contentHeader(): array
    {
        return [
            View::make('components.admin.overview')
                ->viewData(fn (): array => [
                    'cards'     => $this->summaryCards(),
                    'ariaLabel' => __('filament.user_information'),
                ]),
        ];
    }

    /**
     * @return array<int, array{icon: Phosphor, image?: string|null, tint: string|null, label: string, value: string, note: string}>
     */
    protected function summaryCards(): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        $airline = $record->airline;
        $hours = (int) round(($record->flight_time ?? 0) / 60);

        return [
            [
                'icon'  => Phosphor::BuildingsLight,
                'tint'  => null,
                'image' => $airline?->logo_url,
                'label' => __('common.airline'),
                'value' => (string) $airline?->name,
                'note'  => (string) ($airline?->icao ?: $airline?->iata),
            ],
            [
                'icon'  => Phosphor::AirplaneTakeoffLight,
                'tint'  => 'blue',
                'label' => trans_choice('common.flight', 2),
                'value' => number_format($record->flights),
                'note'  => '',
            ],
            [
                'icon'  => Phosphor::ClockLight,
                'tint'  => 'teal',
                'label' => __('flights.flighthours'),
                'value' => number_format($hours),
                'note'  => '',
            ],
            [
                'icon'  => Phosphor::CalendarLight,
                'tint'  => 'violet',
                'label' => __('filament.user_joined'),
                'value' => $record->created_at?->toFormattedDateString() ?? '',
                'note'  => (string) $record->email,
            ],
        ];
    }

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    protected function saveBarDeleteAction(): ?Action
    {
        return DeleteAction::make()->icon(Phosphor::TrashLight);
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
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
            // name and email are $hidden too, and the identity fields are
            // required. The password stays unset (blank): that is how "leave
            // the password alone" is expressed, and prefilling the stored
            // hash would hash it again.
            $data['name'] = $this->record->name;
            $data['email'] = $this->record->email;
            $data['transfer_time'] = $this->record->transfer_time / 60;
        }

        return $data;
    }

    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['transfer_time'] *= 60;

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

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

        $this->saveBarAfterSave();
    }
}
