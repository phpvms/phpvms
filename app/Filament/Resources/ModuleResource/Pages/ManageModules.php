<?php

namespace App\Filament\Resources\ModuleResource\Pages;

use App\Filament\Resources\ModuleResource;
use App\Services\ModuleService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ManageModules extends ManageRecords
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Our custom create action
            Action::make('create')->label('Add Module')->icon('heroicon-o-plus-circle')
                ->schema([
                    Section::make('Module informations')
                        ->description('If you choose to upload a module zip file it will be installed and enabled automatically. Please not that module folder must be on top level of the zip and the zip name must be EXACTLY equal to the name of the module folder inside. If you choose to enable an already uploaded module, you have to upload it in the modules folder')
                        ->schema([
                            Radio::make('method')->options([
                                'upload'       => 'Upload module zip file',
                                'autodiscover' => 'Enable new module (already uploaded in modules folder)',
                            ])->default('upload')->required()->live()->inline(),
                            FileUpload::make('moduleZip')->required(fn (Get $get): bool => $get('method') == 'upload')->visible(fn (Get $get): bool => $get('method') == 'upload')->disk('local')->directory('modules')->preserveFilenames(),
                            Select::make('moduleId')->required(fn (Get $get): bool => $get('method') == 'autodiscover')->visible(fn (Get $get): bool => $get('method') == 'autodiscover')->label('Name')->options(app(ModuleService::class)->scan()),

                        ]),
                ])
                ->action(function (array $data) {
                    $moduleSvc = app(ModuleService::class);
                    if ($data['method'] == 'autodiscover') {
                        $moduleName = $moduleSvc->scan()[(int) $data['moduleId']];
                        $moduleSvc->addModule($moduleName);
                    } else {
                        $moduleSvc->installModule(new UploadedFile(
                            storage_path('app/'.$data['moduleZip']),
                            explode('/', $data['moduleZip'])[array_key_last(explode('/', $data['moduleZip']))]
                        ));

                        Storage::delete(storage_path('app/'.$data['moduleZip']));
                    }
                }),
        ];
    }
}
