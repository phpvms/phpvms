<?php

namespace App\Filament\Imports;

use App\Models\Fare;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Services\FareService;
use App\Services\FleetService;
use App\Support\Utils;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * @property Subfleet $record
 */
class SubfleetImporter extends Importer
{
    protected static ?string $model = Subfleet::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('airline')
                ->relationship(resolveUsing: 'icao'),
            ImportColumn::make('hub')
                ->relationship(),
            ImportColumn::make('type')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('simbrief_type'),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('cost_block_hour')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('cost_delay_minute')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('fuel_type')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('ground_handling_multiplier')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('cargo_capacity')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('fuel_capacity')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('gross_weight')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('fares')
                ->fillRecordUsing(function (): void {}),
            ImportColumn::make('ranks')
                ->fillRecordUsing(function (): void {}),
        ];
    }

    protected function beforeUpdate(): void
    {
        if ($this->record->trashed()) {
            $this->record->restore();
        }
    }

    protected function afterSave(): void
    {
        if (array_key_exists('fares', $this->data) && $this->data['fares'] !== '') {
            $this->processFares($this->record, $this->data['fares']);
        }

        if (array_key_exists('ranks', $this->data) && $this->data['ranks'] !== '') {
            $this->processRanks($this->record, $this->data['ranks']);
        }
    }

    public function resolveRecord(): Subfleet
    {
        return Subfleet::withTrashed()->firstOrNew([
            'type' => $this->data['type'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your subfleet import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    /**
     * Parse all of the fares in the multi-format
     */
    private function processFares(Subfleet $subfleet, $col): void
    {
        $fares = Utils::parseMultiColumnValues($col);
        foreach ($fares as $fare_code => $fare_attributes) {
            if (\is_int($fare_code)) {
                $fare_code = $fare_attributes;
                $fare_attributes = [];
            }

            $fare = Fare::firstOrCreate(['code' => $fare_code], ['name' => $fare_code]);
            app(FareService::class)->setForSubfleet($subfleet, $fare, $fare_attributes);
            $fare->save();
        }
    }

    /**
     * Parse all of the rakns in the multi-format
     */
    private function processRanks(Subfleet $subfleet, $col): void
    {
        $ranks = Utils::parseMultiColumnValues($col);
        foreach ($ranks as $rank_id => $rank_attributes) {
            if (!\is_array($rank_attributes)) {
                $rank_id = $rank_attributes;
                $rank_attributes = [];
            }

            $rank = Rank::firstOrCreate(['id' => $rank_id], ['name' => 'Imported rank '.$rank_id]);
            app(FleetService::class)->addSubfleetToRank($subfleet, $rank, $rank_attributes);
            $rank->save();
        }
    }
}
