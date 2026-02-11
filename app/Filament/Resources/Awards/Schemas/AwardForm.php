<?php

namespace App\Filament\Resources\Awards\Schemas;

use App\Services\AwardService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AwardForm
{
    public static function configure(Schema $schema): Schema
    {
        $awards = [];

        $award_classes = app(AwardService::class)->findAllAwardClasses();
        foreach ($award_classes as $class_ref => $award) {
            $awards[$class_ref] = $award->name;
        }

        return $schema
            ->components([
                Section::make(__('filament.awards_informations'))
                    ->description(__('filament.awards_description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->required()
                            ->string(),

                        TextInput::make('image_url')
                            ->label(__('common.image_url'))
                            ->url(),

                        RichEditor::make('description')
                            ->label(__('common.description'))
                            ->columnSpan(2),

                        FileUpload::make('image_file')
                            ->label(__('common.image'))
                            ->image()
                            ->imageEditor()
                            ->disk(config('filesystems.public_files'))
                            ->directory('awards'),

                        Grid::make()
                            ->schema([
                                Select::make('ref_model_type')
                                    ->label(__('filament.award_class'))
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->options($awards),

                                TextInput::make('ref_model_params')
                                    ->required()
                                    ->label(__('filament.award_class_param'))
                                    ->string(),
                            ])
                            ->columns(1)
                            ->columnSpan(1),

                        Toggle::make('active')
                            ->label(__('common.active'))
                            ->offIcon(icon: Heroicon::XCircle)
                            ->offColor('danger')
                            ->onIcon(icon: Heroicon::CheckCircle)
                            ->onColor('success')
                            ->default(true),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }
}
