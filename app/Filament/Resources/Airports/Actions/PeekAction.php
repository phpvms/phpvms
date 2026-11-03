<?php

declare(strict_types=1);

namespace App\Filament\Resources\Airports\Actions;

use App\Filament\Resources\Airports\AirportResource;
use App\Models\Airport;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Read-only slide-over for an airport row: the list's recordAction. Header
 * strip carries prev/next through the current table page and "Open full page".
 */
class PeekAction
{
    public const string NAME = 'peek';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label(__('filament-actions::view.single.label'))
            ->icon(Phosphor::EyeLight)
            ->slideOver()
            // Read-only: leaving via "Open full page" loses nothing, so skip
            // the vendor's leave-page prompt for open actions.
            ->unsavedChangesAlert(false)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading(fn (Airport $record): string => $record->icao)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalFooterActionsAlignment(Alignment::Start)
            ->extraModalWindowAttributes(['class' => 'pirep-panel'])
            ->extraModalOverlayAttributes(['class' => 'pirep-panel-overlay'])
            ->modalFooterActions([])
            ->modalContent(function (Airport $record, HasTable $livewire): Htmlable {
                $record = $record->fresh() ?? $record;

                return view('filament.airports.panel.peek', [
                    'record'     => $record,
                    'eyebrow'    => self::eyebrow($record),
                    'openUrl'    => AirportResource::getUrl('edit', ['record' => $record]),
                    'navigation' => self::navigation($livewire, $record),
                ]);
            });
    }

    /**
     * "Location · COUNTRY", whichever parts are filled.
     */
    public static function eyebrow(Airport $record): string
    {
        return implode(' · ', array_filter([
            $record->location,
            filled($record->country) ? strtoupper($record->country) : null,
        ], filled(...)));
    }

    /**
     * Position and neighbours of the record within the table's current page.
     *
     * @return array{position: int, total: int, previous: string|null, next: string|null}
     */
    private static function navigation(HasTable $livewire, Airport $record): array
    {
        $records = $livewire->getTableRecords();
        $keys = ($records instanceof Paginator ? collect($records->items()) : collect($records))
            ->map(fn (Airport $row): string => $livewire->getTableRecordKey($row))
            ->values();

        $index = $keys->search((string) $record->getKey());

        return [
            'position' => $index === false ? 0 : $index + 1,
            'total'    => $keys->count(),
            'previous' => $index === false ? null : $keys->get($index - 1),
            'next'     => $index === false ? null : $keys->get($index + 1),
        ];
    }
}
