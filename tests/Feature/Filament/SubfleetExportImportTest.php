<?php

declare(strict_types=1);

use App\Filament\Exports\SubfleetExporter;
use App\Filament\Imports\SubfleetImporter;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Subfleet;
use Filament\Actions\Exports\ExportColumn;

// An airport id is not necessarily its ICAO, so the hub is exported by ICAO and
// the importer resolves it by ICAO, falling back to the id for older files.
it('exports the subfleet hub as its icao', function (): void {
    $hub = Airport::factory()->create(['id' => 'HUB1', 'icao' => 'KJFK', 'name' => 'John F Kennedy Intl']);
    $subfleet = Subfleet::factory()->create(['hub_id' => $hub->id]);

    $labels = array_map(fn (ExportColumn $column): ?string => $column->getLabel(), SubfleetExporter::getColumns());
    $row = array_combine($labels, SubfleetExporter::test()->export($subfleet->fresh()));

    expect($row['Hub'])->toBe('KJFK');
});

it('imports the subfleet hub from its icao or its id', function (string $value): void {
    $airline = Airline::factory()->create();
    $hub = Airport::factory()->create(['id' => 'HUB1', 'icao' => 'KJFK']);

    $import = SubfleetImporter::test(['airline' => 'airline', 'hub' => 'hub', 'type' => 'type', 'name' => 'name'])
        ->import(['airline' => $airline->icao, 'hub' => $value, 'type' => 'B738', 'name' => 'Boeing 737-800'])
        ->assertImported();

    expect($import->getRecord()?->hub_id)->toBe($hub->id);
})->with(['icao' => 'KJFK', 'id' => 'HUB1']);
