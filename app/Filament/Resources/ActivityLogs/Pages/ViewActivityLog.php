<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Tables\ActivityChangesTable;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ViewActivityLog extends ViewRecord implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->hasInfolist() // This method returns `true` if the page has an infolist defined
                    ? $this->getInfolistContentComponent() // This method returns a component to display the infolist that is defined in this resource
                    : $this->getFormContentComponent(), // This method returns a component to display the form that is defined in this resource

                // Add custom table component (to display changes)
                $this->getChangesTableComponent(),

                $this->getRelationManagersContentComponent(), // This method returns a component to display the relation managers that are defined in this resource
            ]);
    }

    public function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->records(fn (): array => $this->getChangesRecords());
    }

    public function table(Table $table): Table
    {
        return ActivityChangesTable::configure($table);
    }

    public function getChangesTableComponent(): Component
    {
        return Section::make(__('activities.changes'))
            ->schema([
                EmbeddedTable::make(),
            ])
            ->columnSpanFull();
    }

    public function getChangesRecords(): array
    {
        /** @var Activity $record */
        $record = $this->getRecord();
        $changes = $record->changes;
        $attributes = $changes['attributes'] ?? [];
        $oldValues = $changes['old'] ?? [];

        $result = [];
        foreach ($attributes as $field => $newValue) {
            $result[] = [
                'field'    => $field,
                'oldValue' => $oldValues[$field] ?? 'N/A',
                'newValue' => $newValue,
            ];
        }

        return $result;
    }
}
