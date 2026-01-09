<?php

namespace App\Filament\Imports;

use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Expense;
use App\Models\Subfleet;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

class ExpenseImporter extends Importer
{
    protected static ?string $model = Expense::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('airline')
                ->relationship(resolveUsing: 'icao'),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('amount')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('type')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('flight_type'),
            ImportColumn::make('charge_to_user')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('multiplier')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('active')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('ref_model_type')
                ->guess(['ref_model']),
            ImportColumn::make('ref_model_id'),
        ];
    }

    protected function beforeFill(): void
    {
        if (!array_key_exists('ref_model_type', $this->data) || $this->data['ref_model_type'] == '') {
            $this->data['ref_model_type'] = Expense::class;

            return;
        }

        if (!str_contains($this->data['ref_model_type'], 'App\Models\\')) {
            $this->data['ref_model_type'] = 'App\Models\\'.$this->data['ref_model_type'];
        }

        $class = $this->data['ref_model_type'];
        $id = $this->data['ref_model_id'];

        if ($class === Aircraft::class) {
            Log::info('Trying to import expense on aircraft, registration: '.$id);

            if (is_numeric($id)) {
                $obj = Aircraft::where('id', $id)->first();
            } else {
                $obj = Aircraft::where('registration', $id)->first();
            }
        } elseif ($class === Airport::class) {
            Log::info('Trying to import expense on airport, icao: '.$id);
            $obj = Airport::where('icao', $id)->first();
        } elseif ($class === Subfleet::class) {
            Log::info('Trying to import expense on subfleet, type: '.$id);
            if (is_numeric($id)) {
                $obj = Subfleet::where('id', $id)->first();
            } else {
                $obj = Subfleet::where('type', $id)->first();
            }
        } else {
            throw new RowImportFailedException('Unknown ref_model_type: '.$this->data['ref_model_type']);
        }

        if (!$obj) {
            throw new RowImportFailedException('Could not find '.$this->data['ref_model_type'].' with id '.$id);
        }

        $this->data['ref_model_id'] = $obj->id;
    }

    public function resolveRecord(): Expense
    {
        return Expense::firstOrNew([
            'name' => $this->data['name'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your expense import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
