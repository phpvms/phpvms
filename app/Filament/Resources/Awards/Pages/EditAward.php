<?php

declare(strict_types=1);

namespace App\Filament\Resources\Awards\Pages;

use App\Filament\Actions\EditDetailsAction;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Awards\AwardResource;
use App\Filament\Resources\Awards\Schemas\AwardForm;
use App\Models\Award;
use App\Models\AwardRule;
use App\Models\User;
use App\Services\Awards\AwardRunService;
use App\Services\Awards\CriteriaCompilationFailed;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Override;

/**
 * Same identity → workspace split as the flight-bundle edit page: a read-only
 * overview (status, award type, recipients) sits above the criteria
 * workspace, and the award's identity, type and trigger are edited only
 * through the drawer opened from the overview's last card.
 *
 * The page form is the criteria builder. Its tree lives on the AwardRule row
 * rather than an awards column, so it is filled and saved by hand here.
 *
 * @extends EditRecord<Award>
 */
class EditAward extends EditRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = AwardResource::class;

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('components.admin.overview')
                ->viewData([
                    'cards'      => $this->summaryCards(),
                    'ariaLabel'  => __('filament.awards_information'),
                    'editAction' => $this->editAction,
                ]),
            $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    /** The Edit trigger rendered inside the overview's last card. */
    public function editAction(): Action
    {
        return EditDetailsAction::make([
            ...AwardForm::infoFields(),
            ...AwardForm::typeFields(),
            AwardForm::enabledField(),
        ])
            ->modalHeading(__('filament.awards_information'))
            ->extraModalFooterActions([
                DeleteAction::make()->cancelParentActions(),
            ])
            ->mutateRecordDataUsing(function (array $data): array {
                $data = AwardForm::injectTypeIntoFillData($data);

                if (str_starts_with((string) ($data['image_url'] ?? ''), 'awards/')) {
                    $data['image_file'] = $data['image_url'];
                    unset($data['image_url']);
                }

                return $data;
            })
            ->mutateDataUsing(function (array $data): array {
                $data = AwardForm::mutateTypeFields($data);

                if (!empty($data['image_file'])) {
                    $data['image_url'] = $data['image_file'];
                }

                return $data;
            })
            ->after(function (Award $record): void {
                // Switching to legacy retires the ruleset row (and its fact
                // pivot) — the two configurations are mutually exclusive.
                if ($record->ref_model_type !== null) {
                    $record->saveConditionsTree(null);
                }
            });
    }

    /**
     * The criteria tree is not an `awards` column — hydrate it from the
     * ruleset row.
     *
     * A tree the builder cannot read opens empty rather than fataling the
     * page: `Builder` assumes every node is an array and throws a TypeError on
     * anything else, which leaves the award uneditable and so unrepairable.
     * The admin is told, because saving from here replaces what is stored.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $tree = $this->getRecord()->rule->conditions ?? [];

        if (!AwardRule::isWellFormedTree($tree)) {
            Notification::make()
                ->warning()
                ->title(__('filament.award_conditions_unreadable'))
                ->body(__('filament.award_conditions_unreadable_body'))
                ->persistent()
                ->send();

            $tree = [];
        }

        $data['conditions'] = $tree;

        return $data;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['conditions']);

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record->ref_model_type !== null) {
            return;
        }

        // An empty tree retires the ruleset rather than storing a criteria-less
        // one: an empty tree compiles to an unfiltered users query, which would
        // grant the award to everybody.
        $tree = $this->data['conditions'] ?? [];

        $record->saveConditionsTree($tree === [] ? null : $tree);
    }

    /** Legacy awards have no page-level form — everything lives in the drawer. */
    #[Override]
    protected function getFormActions(): array
    {
        if ($this->getRecord()->ref_model_type !== null) {
            return [];
        }

        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            // "Run test" lives in the criteria section's footer (AwardForm),
            // next to the tree it reports on.
            Action::make('runNow')
                ->label(__('filament.award_run_now'))
                ->icon(TablerIcon::PlayerPlay)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('filament.award_run_now_confirm'))
                ->visible(fn (Award $record): bool => $record->rule !== null)
                ->action(fn (Award $record) => $this->runAward($record, grant: true)),

            // TODO(rules-based-awards §7): the JRE Export action lived here.
            // Delete lives in the settings drawer's footer (editAction()).
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Both run buttons: dry run reports who would be affected, run-now grants.
     * A tree that cannot be compiled fails closed in the service, so report the
     * failure rather than a misleading count of zero.
     */
    private function runAward(Award $award, bool $grant): void
    {
        try {
            $this->notifyRunResults(app(AwardRunService::class)->run($award, $grant));
        } catch (CriteriaCompilationFailed $criteriaCompilationFailed) {
            Notification::make()
                ->title(__('filament.award_run_failed'))
                ->body($criteriaCompilationFailed->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * One notification for both run buttons: how many users the award newly
     * affects, naming the first 25.
     *
     * @param Collection<int, User> $users
     */
    private function notifyRunResults(Collection $users): void
    {
        if ($users->isEmpty()) {
            Notification::make()
                ->title(__('filament.award_run_none'))
                ->send();

            return;
        }

        $names = $users->take(25)->map(fn ($user): string => (string) $user->name);

        if ($users->count() > 25) {
            $names->push('… +'.($users->count() - 25));
        }

        Notification::make()
            ->title(__('filament.award_run_affected', ['count' => $users->count()]))
            ->body($names->implode(', '))
            ->send();
    }

    /**
     * @return array<int, array{icon: TablerIcon, tint: string|null, label: string, value: string, note: string}>
     */
    protected function summaryCards(): array
    {
        $record = $this->getRecord();

        $isRules = $record->ref_model_type === null;
        $recipients = $record->users()->count();

        return [
            [
                'icon'  => TablerIcon::Power,
                'tint'  => null,
                'label' => __('common.status'),
                'value' => $record->active ? __('common.active') : __('common.inactive'),
                'note'  => filled($record->description)
                    ? Str::limit(strip_tags($record->description), 60)
                    : '',
            ],
            [
                'icon'  => TablerIcon::Trophy,
                'tint'  => 'violet',
                'label' => __('filament.award_type'),
                'value' => $isRules ? __('filament.award_type_rules') : __('filament.award_type_legacy'),
                'note'  => $isRules
                    ? ''
                    : class_basename((string) $record->ref_model_type),
            ],
            [
                'icon'  => TablerIcon::Target,
                'tint'  => 'red',
                'label' => __('filament.award_trigger'),
                'value' => $record->trigger?->getLabel(),
                'note'  => $isRules
                    ? '  '
                    : class_basename((string) $record->ref_model_type),
            ],
            [
                'icon'  => TablerIcon::Users,
                'tint'  => 'teal',
                'label' => trans_choice('common.user', 2),
                'value' => number_format($recipients),
                'note'  => '',
            ],
        ];
    }
}
