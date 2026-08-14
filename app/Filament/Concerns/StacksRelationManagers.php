<?php

namespace App\Filament\Concerns;

use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Renders an edit page's relation managers as anchored sections stacked below
 * the form, with a sticky jump bar above it, instead of Filament's tab bar.
 *
 * A tab bar hides every relation but one and gives no sense of how much is
 * attached to the record; stacking them puts the whole record on one page and
 * lets the jump bar carry the counts.
 *
 * A page using this only has to name its form sections — the relation-manager
 * half of the bar is derived from the managers themselves, so adding a manager
 * to the resource adds its entry to the bar with no further wiring.
 */
trait StacksRelationManagers
{
    /**
     * The form's own sections, listed in the jump bar ahead of the relation
     * managers. Keys are the DOM ids set with `Section::make()->id(...)`.
     *
     * @return array<string, string>
     */
    abstract protected function jumpBarFormSections(): array;

    /**
     * Optional record switcher pinned to the end of the jump bar, as a list of
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
     * Each manager draws its own complete card (heading + actions + table), so
     * it only needs a plain anchor holder around it — no second card.
     */
    public function getRelationManagersContentComponent(): Component
    {
        $ownerRecord = $this->getRecord();
        $livewireData = ['ownerRecord' => $ownerRecord, 'pageClass' => static::class];

        $sections = [];
        foreach ($this->getRelationManagers() as $manager) {
            $managerClass = $this->normalizeRelationManagerClass($manager);

            $sections[] = Group::make()
                ->id(self::relationManagerAnchor($managerClass))
                ->extraAttributes(['class' => 'scroll-mt-32'])
                ->schema([
                    Livewire::make(
                        $managerClass,
                        [
                            ...$livewireData,
                            ...(($manager instanceof RelationManagerConfiguration)
                                ? [...$manager->relationManager::getDefaultProperties(), ...$manager->getProperties()]
                                : $managerClass::getDefaultProperties()),
                        ],
                    )->key($managerClass),
                ])
                ->columnSpanFull();
        }

        return Grid::make()
            ->columns(1)
            ->schema($sections);
    }

    /**
     * The jump bar belongs with the content rather than the header band: the
     * band scrolls away, the jump bar has to stay put.
     */
    /**
     * Components rendered above the jump bar — an overview, typically.
     *
     * @return array<int, Component>
     */
    protected function contentHeader(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            ...$this->contentHeader(),
            View::make('filament.shared.section-nav')
                ->viewData([
                    'sectionLinks'  => $this->getSectionLinks(),
                    'switcher'      => $this->jumpBarSwitcher(),
                    'switcherLabel' => $this->jumpBarSwitcherLabel(),
                ]),
            $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    /**
     * @return array<int, array{id: string, label: string, count: int|null}>
     */
    protected function getSectionLinks(): array
    {
        $links = [];

        foreach ($this->jumpBarFormSections() as $id => $label) {
            $links[] = ['id' => $id, 'label' => $label, 'count' => null];
        }

        /** @var Model $record */
        $record = $this->getRecord();

        foreach ($this->getRelationManagers() as $manager) {
            $managerClass = $this->normalizeRelationManagerClass($manager);

            $links[] = [
                'id'    => self::relationManagerAnchor($managerClass),
                'label' => $managerClass::getTitle($record, static::class),
                'count' => self::relationCount($record, $managerClass),
            ];
        }

        return $links;
    }

    /** `FieldValuesRelationManager` anchors at `#field-values`. */
    private static function relationManagerAnchor(string $managerClass): string
    {
        return Str::of(class_basename($managerClass))
            ->beforeLast('RelationManager')
            ->kebab()
            ->value();
    }

    /**
     * A manager whose relationship the owner does not expose (a nested
     * resource reached another way) contributes no count rather than throwing.
     */
    private static function relationCount(Model $record, string $managerClass): ?int
    {
        $relationship = $managerClass::getRelationshipName();

        if (!method_exists($record, $relationship)) {
            return null;
        }

        return $record->{$relationship}()->count();
    }
}
