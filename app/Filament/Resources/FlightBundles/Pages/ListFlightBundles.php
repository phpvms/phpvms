<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlightBundles\Pages;

use App\Filament\Actions\Drawer;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Filament\Resources\FlightBundles\Schemas\FlightBundleForm;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListFlightBundles extends ListRecords
{
    protected static string $resource = FlightBundleResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Drawer::configure(
                CreateAction::make()
                    ->modal()
                    ->icon(Phosphor::PlusCircleLight)
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
                FlightBundleForm::fields(),
            ),
        ];
    }
}
