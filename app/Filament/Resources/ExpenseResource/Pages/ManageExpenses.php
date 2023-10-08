<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Resources\ExpenseResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')->arguments(['resourceTitle' => 'expenses', 'exportType' => ImportExportType::EXPENSES]),
            ImportAction::make('import')->arguments(['resourceTitle' => 'expenses', 'importType' => ImportExportType::EXPENSES]),
            Actions\CreateAction::make()->label('Add Expense')->icon('heroicon-o-plus-circle'),
        ];
    }
}
