<?php

namespace App\Filament\Resources\News\Pages;

use App\Events\NewsAdded;
use App\Filament\Resources\News\NewsResource;
use App\Models\News;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Override;

class ListNews extends ListRecords
{
    protected static string $resource = NewsResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle)
                ->mutateDataUsing(function (array $data): array {
                    $data['user_id'] = Auth::id();

                    return $data;
                })
                ->after(function (array $data, News $record): void {
                    if (get_truth_state($data['send_notifications'] ?? false)) {
                        event(new NewsAdded($record));
                    }
                }),
        ];
    }
}
