<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pireps\Pages;

use App\Filament\Resources\Pireps\Actions\PirepFieldsAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Filament\Resources\Pireps\Widgets\PirepStats;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Override;
use Throwable;

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

    /**
     * The dashboard activity calendar deep-links here with
     * ?departed_from=...&departed_to=... (one-hour block). Hydrate the
     * `departed` table filter before the table boots so the list is
     * pre-filtered to that time block.
     */
    #[Override]
    public function mount(): void
    {
        parent::mount();

        $from = request()->query('departed_from');
        $to = request()->query('departed_to');

        if (filled($from) || filled($to)) {
            // Deep-linked from the dashboard calendar: pre-filter to that block.
            // Parse defensively — a malformed query value must not 500 the list.
            $this->tableFilters['departed'] = [
                'from' => filled($from) ? $this->parseDateQuery($from) : null,
                'to'   => filled($to) ? $this->parseDateQuery($to) : null,
            ];
        } elseif (session()->has($this->getTableFiltersSessionKey())) {
            // Direct visit (no deep-link params): drop a previously
            // calendar-set `departed` filter so the list isn't stuck on an
            // hour block for the rest of the session — and from the hydrated
            // filters so the current request doesn't render the stale block.
            $filters = session()->get($this->getTableFiltersSessionKey(), []);
            unset($filters['departed']);
            session()->put($this->getTableFiltersSessionKey(), $filters);

            unset($this->tableFilters['departed']);
        }
    }

    /**
     * Parse a query-string date without letting a malformed value throw.
     */
    private function parseDateQuery(string $value): ?string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
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
