<?php

namespace App\Filament\Resources\Pireps\Pages;

use App\Filament\Resources\Pireps\Actions\AcceptAction;
use App\Filament\Resources\Pireps\Actions\RejectAction;
use App\Filament\Resources\Pireps\PirepResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewPirep extends ViewRecord
{
    protected static string $resource = PirepResource::class;

    /**
     * Custom blade view that renders the PIREP detail layout.
     * The page extends ViewRecord so Filament resolves the record from the
     * URL and applies policy checks; we just opt out of the default infolist
     * rendering and provide our own markup.
     */
    protected string $view = 'filament.resources.pireps.pages.view-pirep';

    #[\Override]
    public function content(Schema $schema): Schema
    {
        // No default infolist — the custom blade renders the detail layout.
        return $schema->components([]);
    }

    /**
     * Skip ViewRecord's default form/infolist fill. The page renders a custom
     * blade that reads $record directly, so we don't need (or want) Filament
     * to hydrate a form schema from the model attributes. Pirep has custom
     * value-object casts (Fuel, Distance) which break NumberStateCast.
     */
    #[\Override]
    protected function fillForm(): void
    {
        // no-op
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            AcceptAction::make(),
            RejectAction::make(),
            EditAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    #[\Override]
    public function mount(int|string $record): void
    {
        parent::mount($record);

        // 'fields' is an Attribute accessor, not a relation — don't eager-load it.
        $this->record->loadMissing([
            'user',
            'aircraft',
            'airline',
            'dpt_airport',
            'arr_airport',
            'comments.user',
            'transactions',
        ]);
    }
}
