<?php

namespace App\Filament\Resources\Modules\Actions;

use App\Services\ModuleService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CreateModule
{
    public static function make(): Action
    {
        return
            Action::make('create')
                ->label( __('filament-actions::create.single.label', ['label' => __('common.module')]))
                ->icon(Heroicon::OutlinedPlusCircle)
                ->schema([
                    Section::make(__('filament.module_informations'))
                        ->description(__('filament.module_description'))
                        ->schema([
                            Radio::make('method')
                                ->label(__('filament.module_method'))
                                ->options([
                                    'upload'       => __('filament.module_method_zip'),
                                    'autodiscover' => __('filament.module_method_autodiscover'),
                                ])
                                ->default('autodiscover')
                                ->required()
                                ->inline(),

                            FileUpload::make('moduleZip')
                                ->label(__('filament.module_zip'))
                                ->requiredIf('method', 'upload')
                                ->visibleJs(<<<'JS'
                                    $get('method') === 'upload'
                                JS)
                                ->disk('local')
                                ->directory('modules')
                                ->preserveFilenames(),

                            Select::make('moduleId')
                                ->label(__('common.name'))
                                ->requiredIf('method', 'autodiscover')
                                ->visibleJs(<<<'JS'
                                    $get('method') === 'autodiscover'
                                JS)
                                ->options(app(ModuleService::class)->scan()),

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

                    Notification::make()
                        ->title(__('filament-actions::create.single.notifications.created.title'))
                        ->success()
                        ->send();
                });
    }
}
