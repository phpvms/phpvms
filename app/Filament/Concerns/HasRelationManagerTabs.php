<?php

namespace App\Filament\Concerns;

use BackedEnum;
use Closure;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Gives an edit page Filament's combined tab bar: the record's form is the
 * first tab ("Details") and every relation manager is a tab after it, with its
 * row count as the badge. The active tab lives in the `relation` query string
 * (`?relation=<manager key>`), so a tab survives a reload and can be linked.
 *
 * The form tab carries the page's own chrome: `contentHeader()` components
 * (an overview, typically) above an optional record switcher. Keeping the
 * overview inside the tab leaves the tab strip directly on the banner's bottom
 * edge.
 */
trait HasRelationManagerTabs
{
    /**
     * Optional record switcher shown above the form, as a list of
     * `['label' => ..., 'url' => ...]`. Empty means no switcher.
     *
     * @return array<int, array{label: string, url: string}>
     */
    protected function jumpBarSwitcher(): array
    {
        return [];
    }

    /** The switcher's own record, shown as its resting option. */
    protected function jumpBarSwitcherLabel(): string
    {
        return '';
    }

    /**
     * Components rendered at the top of the form tab, above the switcher --
     * an overview, typically.
     *
     * @return array<int, Component>
     */
    protected function contentHeader(): array
    {
        return [];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return __('filament.details_tab');
    }

    public function getContentTabIcon(): string|BackedEnum|Htmlable|null
    {
        return Phosphor::ClipboardTextLight;
    }

    public function getContentTabComponent(): Tab
    {
        // Built once per render: the switcher can run a query, and both the
        // hidden check and the view data need it.
        $switcher = null;

        return Tab::make($this->getContentTabLabel())
            ->icon($this->getContentTabIcon())
            ->schema([
                ...$this->contentHeader(),
                View::make('filament.shared.section-nav')
                    // No switcher: no bar, rather than an empty strip.
                    ->hidden(function () use (&$switcher): bool {
                        $switcher = $this->jumpBarSwitcher();

                        return $switcher === [];
                    })
                    ->viewData(function () use (&$switcher): array {
                        return [
                            'switcher'      => $switcher ?? $this->jumpBarSwitcher(),
                            'switcherLabel' => $this->jumpBarSwitcherLabel(),
                        ];
                    }),
                $this->getFormContentComponent(),
            ]);
    }

    /** The count of related rows rides on the manager's tab as its badge. */
    public function getRelationManagerTabComponent(string|int $key): ?Tab
    {
        $tab = parent::getRelationManagerTabComponent($key);
        $manager = $this->getCachedRelationManagers()[$key] ?? null;

        if ($tab === null || $manager === null || $manager instanceof RelationGroup) {
            return $tab;
        }

        $count = $this->relationManagerRowCount($this->normalizeRelationManagerClass($manager));

        $tab->icon(self::relationManagerTabIcon($this->normalizeRelationManagerClass($manager)::getRelationshipName()));

        return $count === null ? $tab : $tab->badge($count);
    }

    /** Folder tabs all carry an icon; managers are matched by relationship name. */
    private static function relationManagerTabIcon(string $relationship): BackedEnum
    {
        return match ($relationship) {
            'ranks'       => Phosphor::MedalLight,
            'typeratings' => Phosphor::CertificateLight,
            'aircraft'    => Phosphor::AirplaneTiltLight,
            'subfleets'   => Phosphor::StackLight,
            'field_values',
            'fields'       => Phosphor::TextboxLight,
            'flights'      => Phosphor::AirplaneTakeoffLight,
            'pireps'       => Phosphor::ListChecksLight,
            'awards'       => Phosphor::StarLight,
            'users'        => Phosphor::UsersLight,
            'transactions' => Phosphor::MoneyLight,
            'fares',
            'expenses'  => Phosphor::CurrencyDollarLight,
            'comments'  => Phosphor::ChatCircleLight,
            'revisions' => Phosphor::ClockCounterClockwiseLight,
            'files'     => Phosphor::PaperclipLight,
            default     => Phosphor::ListBulletsLight,
        };
    }

    /**
     * Managers are keyed by their relationship name, so the active tab reads
     * `?relation=pireps` rather than a list position.
     *
     * @return array<string|int, class-string<RelationManager>|RelationGroup|RelationManagerConfiguration>
     */
    protected function getAllRelationManagers(): array
    {
        $keyed = [];

        foreach ($this->getResource()::getRelations() as $key => $manager) {
            if (!$manager instanceof RelationGroup) {
                $key = $this->normalizeRelationManagerClass($manager)::getRelationshipName();
            }

            $keyed[$key] = $manager;
        }

        return $keyed;
    }

    /**
     * The row count of the manager's own table query, so the badge and the
     * table cannot disagree (a table that hides drafts, say). A manager whose
     * relationship the owner does not expose (a nested resource reached
     * another way) contributes no count rather than throwing.
     *
     * @param class-string<RelationManager> $managerClass
     */
    private function relationManagerRowCount(string $managerClass): ?int
    {
        $record = $this->getRecord();

        if (!method_exists($record, $managerClass::getRelationshipName())) {
            return null;
        }

        $manager = new $managerClass();
        $manager->ownerRecord = $record;
        $manager->pageClass = static::class;

        $table = Closure::bind(
            fn (): Table => $this->table($this->makeTable()),
            $manager,
            $managerClass,
        )();

        return $table->getQuery()?->count();
    }
}
