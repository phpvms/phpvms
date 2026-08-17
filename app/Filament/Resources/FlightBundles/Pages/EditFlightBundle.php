<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlightBundles\Pages;

use App\Filament\Actions\EditDetailsAction;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Filament\Resources\FlightBundles\Schemas\FlightBundleForm;
use App\Models\Aircraft;
use App\Models\FlightBundle;
use Carbon\Carbon;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
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
    protected static string $resource = FlightBundleResource::class;

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
     */
    #[Override]
    public function getBreadcrumbs(): array
    {
        /** @var FlightBundle $record */
        $record = $this->getRecord();

        return [
            FlightBundleResource::getUrl() => FlightBundleResource::getBreadcrumb(),
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
            View::make('components.admin.overview')
                ->viewData([
                    'cards'      => $this->summaryCards(),
                    'ariaLabel'  => __('filament.bundles.sections.details'),
                    'editAction' => $this->editAction,
                ]),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    /** The Edit trigger rendered inside the overview's last card. */
    public function editAction(): Action
    {
        return EditDetailsAction::make(FlightBundleForm::fields())
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
            ForceDeleteAction::make()->icon(Phosphor::TrashSimpleLight),
            RestoreAction::make()->icon(Phosphor::ArrowUUpLeftLight),
        ];
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
