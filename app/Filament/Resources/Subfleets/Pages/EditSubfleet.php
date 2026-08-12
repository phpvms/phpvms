<?php

namespace App\Filament\Resources\Subfleets\Pages;

use App\Filament\Actions\EditDetailsAction;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Concerns\StacksRelationManagers;
use App\Filament\Resources\Subfleets\Schemas\SubfleetForm;
use App\Filament\Resources\Subfleets\SubfleetResource;
use App\Models\File;
use App\Models\Subfleet;
use App\Services\FileService;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Override;

/**
 * Same identity → workspace split as the flight edit page: airline, type and
 * name are read in the overview and edited only through the drawer it opens,
 * leaving the page form for what the subfleet actually costs and can fly.
 */
class EditSubfleet extends EditRecord
{
    use ReversePrimaryButtons;
    use StacksRelationManagers;

    protected static string $resource = SubfleetResource::class;

    /** The type is the identifier admins recognise; the name is the gloss. */
    #[Override]
    public function getHeading(): string|Htmlable
    {
        /** @var Subfleet $record */
        $record = $this->getRecord();

        return new HtmlString(sprintf(
            '%s <span class="id fi-header-heading-route">%s</span>',
            e($record->type),
            e($record->name),
        ));
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        /** @var Subfleet $record */
        $record = $this->getRecord();

        return view('filament.shared.hero-subheading', [
            'meta' => implode(' · ', array_filter([
                $record->airline?->name,
                $record->fuel_type?->getLabel(),
                $record->home?->icao,
            ])),
            'figures' => array_filter([
                ['value' => number_format($record->aircraft()->count()), 'label' => __('common.aircraft')],
                ['value' => number_format($record->flights()->count()), 'label' => trans_choice('common.flight', 2)],
                $record->max_range_nm ? ['value' => number_format($record->max_range_nm), 'label' => __('common.nautical_miles_short')] : null,
            ]),
        ]);
    }

    /**
     * `Subfleets › <type> - <name>`. Filament ends the chain on the page
     * label, which only repeats what the heading above it already says.
     *
     * Both halves, because the name alone does not identify a subfleet -- a
     * B738 and a B738-WL are both "Boeing 737-800". The type is what tells
     * them apart, the same way the registration does for an aircraft.
     */
    #[Override]
    public function getBreadcrumbs(): array
    {
        /** @var Subfleet $record */
        $record = $this->getRecord();

        return [
            SubfleetResource::getUrl() => SubfleetResource::getBreadcrumb(),
            self::subfleetCrumb($record),
        ];
    }

    /** Shared with the nested aircraft page, which links back to this one. */
    public static function subfleetCrumb(Subfleet $subfleet): string
    {
        return $subfleet->type.' - '.$subfleet->name;
    }

    /**
     * The six relation managers (aircraft, ranks, typeratings, fares,
     * expenses, files) are appended by the trait.
     *
     * @return array<string, string>
     */
    protected function jumpBarFormSections(): array
    {
        return [
            'subfleet-information'   => __('filament.subfleet_information'),
            'costs'                  => __('filament.subfleets.sections.costs'),
            'operational-capability' => __('filament.subfleets.sections.operational_capability'),
        ];
    }

    /** The identity overview sits above the jump bar. */
    protected function contentHeader(): array
    {
        return [
            View::make('components.admin.overview')
                ->viewData([
                    'cards'      => $this->summaryCards(),
                    'ariaLabel'  => __('filament.subfleet_information'),
                    'editAction' => $this->editAction,
                ]),
        ];
    }

    /**
     * @return array<int, array{icon: TablerIcon, tint: string|null, label: string, value: string, note: string}>
     */
    protected function summaryCards(): array
    {
        /** @var Subfleet $record */
        $record = $this->getRecord();

        $aircraft = $record->aircraft()->count();

        return [
            [
                'icon'  => TablerIcon::BuildingArch,
                'tint'  => null,
                'label' => __('common.airline'),
                'value' => (string) $record->airline?->name,
                'note'  => (string) $record->airline?->icao,
            ],
            [
                'icon'  => TablerIcon::Plane,
                'tint'  => 'blue',
                'label' => __('common.type'),
                'value' => (string) $record->type,
                'note'  => (string) $record->name,
            ],
            [
                'icon'  => TablerIcon::MapPin,
                'tint'  => 'teal',
                'label' => __('airports.home'),
                'value' => (string) ($record->home?->icao ?: '—'),
                'note'  => (string) $record->home?->name,
            ],
            [
                'icon'  => TablerIcon::Stack2,
                'tint'  => 'violet',
                'label' => __('common.aircraft'),
                'value' => number_format($aircraft),
                'note'  => number_format($record->flights()->count()).' '.trans_choice('common.flight', 2),
            ],
        ];
    }

    /** The Edit trigger rendered inside the overview's last card. */
    public function editAction(): Action
    {
        return EditDetailsAction::make(SubfleetForm::editDrawerFields())
            ->modalHeading(__('filament.subfleet_information'))
            ->modalDescription(__('filament.subfleet_description'))
            ->extraModalFooterActions([
                DeleteAction::make()->cancelParentActions(),
            ]);
    }

    /** The identity fields live in the drawer, not the page form. */
    #[Override]
    public function form(Schema $schema): Schema
    {
        return SubfleetForm::configure($schema, withIdentity: false, withHomeAirport: false);
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
            // Delete lives in the settings drawer's footer (editAction()).
            ForceDeleteAction::make()->before(function (Subfleet $record): void {
                $record->files()->each(function (File $file): void {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make(),
        ];
    }
}
