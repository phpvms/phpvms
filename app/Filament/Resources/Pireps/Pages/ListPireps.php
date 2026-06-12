<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pireps\Pages;

use App\Filament\Resources\Pireps\Actions\PirepFieldsAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Filament\Resources\Pireps\Widgets\PirepStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Override;

class ListPireps extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PirepResource::class;

    /**
     * Custom blade view that renders pireps as cards instead of an embedded table.
     * The page still extends ListRecords so Filament wires the Table object's
     * filters, search, sort, and pagination via Livewire — we just don't render
     * the table markup.
     */
    protected string $view = 'filament.pireps.pages.list-pireps';

    #[Override]
    public function content(Schema $schema): Schema
    {
        // No EmbeddedTable. The custom blade renders filters + cards directly.
        return $schema->components([]);
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            PirepFieldsAction::make(),
        ];
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            PirepStats::class,
        ];
    }
}
