<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Repositories\ExpenseRepository;
use App\Services\ExportService;
use App\Services\ImportService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')->label('Export to CSV')
                ->action(function (): BinaryFileResponse {
                    $exporter = app(ExportService::class);
                    $expenseRepo = app(ExpenseRepository::class);
                    $expenses = $expenseRepo->all();

                    $path = $exporter->exportExpenses($expenses);
                    return response()->download($path, 'expenses.csv', ['content-type' => 'text/csv',])->deleteFileAfterSend(true);
                })->after(function (): void {
                    Notification::make()
                        ->title('Expenses Exported')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('import')->label('Import from CSV')
                ->form([
                    FileUpload::make('importFile')->acceptedFileTypes(['text/csv'])->disk('local')->directory('import'),
                    Toggle::make('delete')->label('Delete Previous Expenses')->default(false),
                ])->action(function (array $data, Actions\Action $action): void {
                    $importSvc = app(ImportService::class);

                    $path = storage_path('app/'.$data['importFile']);
                    Log::info('Uploaded Expense import file to '.$path);

                    $logs = $importSvc->importExpenses($path, $data['delete']);

                    session(['logs' => $logs]);

                    if (count($logs['errors']) === 0)
                    {
                        Notification::make()
                            ->title('Expenses Imported Successfully')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('There were '.count($logs['errors']).' errors importing expenses')
                            ->body(implode('<br>', $logs['errors']))
                            ->persistent()
                            ->danger()
                            ->send();
                    }
                }),
            Actions\CreateAction::make()->label('Add Expense'),
        ];
    }
}
