<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlightBundles\Pages;

use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Filament\Resources\FlightBundles\Schemas\FlightBundleForm;
use App\Models\FlightBundle;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Override;

/**
 * Identity → workspace → tucked-away settings: a read-only summary strip
 * (status, flight and subfleet summations, window) sits above the schedules
 * table that is the page's real workload, and the bundle's own fields are
 * edited only through the slideover opened from the strip's last card.
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
     * Keep the page's own form empty so mounting doesn't hydrate the resource
     * form (and its subfleet option queries) for fields that never render —
     * the slideover builds its own schema.
     */
    #[Override]
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.resources.flight-bundle.summary-strip')
                ->viewData(['record' => $this->getRecord()]),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    /**
     * The Edit-details trigger rendered inside the summary strip's last card.
     * The drawer is the Tailwind Plus branded-header pattern: md width,
     * accent header (styled via .drawer-branded in the admin theme), flat
     * fields with no section card.
     */
    public function editAction(): Action
    {
        return EditAction::make()
            ->label(__('common.edit'))
            ->icon(TablerIcon::Pencil)
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->modalHeading(__('filament.bundles.edit_details'))
            ->modalDescription(__('filament.bundles.edit_details_description'))
            ->extraModalWindowAttributes(['class' => 'drawer-branded'])
            ->extraModalFooterActions([
                DeleteAction::make(),
            ])
            ->schema(fn (Schema $schema): Schema => $schema
                ->components(FlightBundleForm::fields())
                ->columns(1));
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
