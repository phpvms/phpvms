<?php

namespace App\Filament\Imports;

use App\Models\Fare;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * @property Fare $record
 */
class FareImporter extends Importer
{
    protected static ?string $model = Fare::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('price')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('cost')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('capacity')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('type')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('notes'),
            ImportColumn::make('active')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
        ];
    }

    public function resolveRecord(): Fare
    {
        return Fare::withTrashed()->firstOrNew([
            'code' => $this->data['code'],
        ]);
    }

    protected function beforeUpdate(): void
    {
        if ($this->record->trashed()) {
            $this->record->restore();
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your fare import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
