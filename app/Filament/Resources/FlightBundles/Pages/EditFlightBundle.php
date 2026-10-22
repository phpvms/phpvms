<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlightBundles\Pages;

use App\Enums\BundleType;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Filament\Actions\EditDetailsAction;
use App\Filament\Concerns\AutosavesFields;
use App\Filament\Forms\Components\AssetImagePicker;
use App\Filament\Pages\RouteForge;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Filament\Resources\FlightBundles\Schemas\FlightBundleForm;
use App\Models\Aircraft;
use App\Models\Asset;
use App\Models\FlightBundle;
use Carbon\Carbon;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Override;

/**
 * Identity → workspace → tucked-away settings: a read-only overview
 * (status, flight and subfleet summations, window) sits above the schedules
 * table that is the page's real workload, and the bundle's own fields are
 * edited only through the drawer opened from the overview's last card.
 */
class EditFlightBundle extends EditRecord
{
    use AutosavesFields;

    protected static string $resource = FlightBundleResource::class;

    /** Set by the tours page, which drops the type field from the drawer. */
    protected static bool $forTours = false;

    /**
     * Only the image control autosaves; the rest of the drawer saves on
     * submit. The picker wires itself through its own afterStateUpdated hook.
     *
     * @return list<string>
     */
    protected function autosaveKeys(): array
    {
        return AssetImagePicker::stateKeys(Asset::SLOT_BUNDLE);
    }

    protected function persistAutosavedField(string $key, mixed $value): void
    {
        AssetImagePicker::persist(
            Asset::SLOT_BUNDLE,
            (string) $this->getRecord()->getKey(),
            FlightBundleForm::imageDisk(),
            $key,
            $value,
        );
    }

    protected function autosaveNotificationTitle(): string
    {
        return __('filament.bundles.image_saved');
    }

    #[Override]
    public function getTitle(): string|Htmlable
    {
        /** @var FlightBundle $record */
        $record = $this->getRecord();

        return $record->name;
    }

    /**
     * `Flight Bundles › <name>`. Filament names no record crumb at all here --
     * the resource has no `$recordTitleAttribute` -- and ends the chain on the
     * page label, which only repeats the heading above it.
     *
     * The first crumb comes from the page's own resource so the tours page,
     * which subclasses this one, says `Tours ›` rather than `Flight Bundles ›`.
     */
    #[Override]
    public function getBreadcrumbs(): array
    {
        /** @var FlightBundle $record */
        $record = $this->getRecord();

        return [
            ...$this->getResourceBreadcrumbs(),
            $record->name,
        ];
    }

    /**
     * No page-level form — the table below is the page's content, and the
     * fields live in the Edit drawer.
     */
    #[Override]
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[Override]
    protected function getFormActions(): array
    {
        return [];
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // Above the overview, not inside it: a tour whose legs do not run
            // 1..N cannot be bid at all, so it outranks everything the summary
            // cards report.
            Text::make(fn (): string => $this->tourLegWarning())
                ->color('warning')
                ->icon(Phosphor::WarningLight)
                ->visible(fn (): bool => $this->tourLegWarning() !== ''),

            View::make('components.admin.overview')
                ->viewData([
                    'cards'      => $this->summaryCards(),
                    'ariaLabel'  => __('filament.bundles.sections.details'),
                    'editAction' => $this->editAction,
                ]),

            // Who is mid-tour right now, above the flights table so the admin
            // sees it before they start editing legs.
            Section::make(__('filament.bundles.live_tours.heading'))
                ->description(__('filament.bundles.live_tours.description'))
                // Closure, so switching the type in the drawer reveals the panel
                // without a reload. The rows behind it are read once at build
                // time; a bundle only just made a tour has no runs to show.
                ->visible(fn (): bool => $this->isTour())
                ->schema([
                    View::make('filament.resources.flight-bundle.live-tours')
                        ->viewData(['tours' => $this->liveTours()]),
                ]),

            $this->getRelationManagersContentComponent(),
        ]);
    }

    /**
     * The bundle's `in_progress` runs, newest last.
     *
     * Straight off the (bundle_id, status) index; `legs_completed`,
     * `legs_total` and `flight_id` are columns, so the `legs` JSON is never
     * touched. A `flights` bundle has no runs to look for, and gets no query.
     *
     * @return Collection<int, UserTour>
     */
    private function liveTours(): Collection
    {
        if (!$this->isTour()) {
            return new Collection();
        }

        return UserTour::query()
            ->where('bundle_id', $this->getRecord()->getKey())
            ->where('status', TourStatus::InProgress)
            ->with(['user', 'flight.airline'])
            ->orderBy('started_at')
            ->get();
    }

    private function isTour(): bool
    {
        /** @var FlightBundle $record */
        $record = $this->getRecord();

        return $record->type === BundleType::Tour;
    }

    /**
     * The bundle's leg-numbering complaint, or '' when there is none. Only a
     * tour has a leg sequence to be wrong about; a `flights` bundle stays
     * silent.
     */
    private function tourLegWarning(): string
    {
        /** @var FlightBundle $record */
        $record = $this->getRecord();

        if ($record->type !== BundleType::Tour) {
            return '';
        }

        return FlightBundleForm::tourLegWarning($record);
    }

    /** The Edit trigger rendered inside the overview's last card. */
    public function editAction(): Action
    {
        return EditDetailsAction::make(FlightBundleForm::fields(static::$forTours))
            ->modalHeading(__('filament.bundles.edit_details'))
            ->modalDescription(__('filament.bundles.edit_details_description'))
            ->extraModalFooterActions([
                DeleteAction::make()->icon(Phosphor::TrashLight),
            ]);
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            $this->forgeFlightsAction(),
            ForceDeleteAction::make()->icon(Phosphor::TrashSimpleLight),
            RestoreAction::make()->icon(Phosphor::ArrowUUpLeftLight),
        ];
    }

    /**
     * Open RouteForge with this bundle as the target and the tour topology
     * already chosen. `fresh=1` tells the SPA to drop any stored draft, which
     * would otherwise render the resume banner instead of the prefilled form.
     *
     * Tour bundles only: the link hard-codes the tour topology, so offering it
     * on a `flights` bundle would forge the wrong shape.
     */
    private function forgeFlightsAction(): Action
    {
        return Action::make('forgeFlights')
            ->label(__('filament.bundles.forge_flights'))
            ->icon(Phosphor::StackLight)
            ->visible(fn (): bool => $this->isTour() && RouteForge::canAccess())
            ->url(function (): string {
                /** @var FlightBundle $record */
                $record = $this->getRecord();

                return RouteForge::getUrl().'?'.http_build_query([
                    'topology'    => 'tour',
                    'bundle'      => $record->getKey(),
                    'bundle_name' => $record->name,
                    'fresh'       => 1,
                ]);
            });
    }

    /**
     * @return array<int, array{icon: Phosphor, tint: string|null, label: string, value: string, note: string}>
     */
    protected function summaryCards(): array
    {
        /** @var FlightBundle $record */
        $record = $this->getRecord();

        $flightsTotal = $record->flights()->count();
        $flightsEnabled = $record->flights()->where('enabled', true)->count();
        $subfleetCount = $record->subfleets()->count();
        $tailCount = Aircraft::query()
            ->whereIn('subfleet_id', $record->subfleets()->select('subfleets.id'))
            ->count();

        $window = ($record->start_date === null && $record->end_date === null)
            ? __('filament.bundles.window_always')
            : $this->formatWindowDate($record->start_date).' → '.$this->formatWindowDate($record->end_date);

        return [
            [
                'icon'  => Phosphor::PowerLight,
                'tint'  => null,
                'label' => __('common.status'),
                'value' => $record->enabled ? __('common.enabled') : __('common.disabled'),
                'note'  => $record->visible ? 'Visible to pilots' : 'Hidden from pilots',
            ],
            [
                'icon'  => Phosphor::AirplaneLight,
                'tint'  => 'blue',
                'label' => trans_choice('common.flight', 2),
                'value' => number_format($flightsTotal),
                'note'  => $flightsEnabled.' enabled · '.($flightsTotal - $flightsEnabled).' disabled',
            ],
            [
                'icon'  => Phosphor::StackSimpleLight,
                'tint'  => 'teal',
                'label' => trans_choice('common.subfleet', 2),
                'value' => number_format($subfleetCount),
                'note'  => $tailCount.' '.Str::plural('tail', $tailCount),
            ],
            [
                'icon'  => Phosphor::CalendarDotLight,
                'tint'  => 'violet',
                'label' => __('filament.bundles.window'),
                'value' => $window,
                'note'  => filled($record->description)
                    ? Str::limit($record->description, 60)
                    : __('filament.bundles.no_description'),
            ],
        ];
    }

    /** The window is open-ended on either side; a dash marks the open end. */
    private function formatWindowDate(?Carbon $date): string
    {
        if (!$date instanceof Carbon) {
            return '—';
        }

        return $date->year === now()->year ? $date->format('j M') : $date->format('j M Y');
    }
}
