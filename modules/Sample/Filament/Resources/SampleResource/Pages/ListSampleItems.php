<?php

declare(strict_types=1);

namespace Modules\Sample\Filament\Resources\SampleResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Sample\Filament\Resources\SampleResource;

class ListSampleItems extends ListRecords
{
    protected static string $resource = SampleResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
