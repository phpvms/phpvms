<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\Enums\PageType;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->components([
                Section::make(__('filament.page_informations'))
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('common.name'))
                                    ->required()
                                    ->string(),

                                TextInput::make('icon')
                                    ->label(__('common.icon'))
                                    ->string(),

                                Select::make('type')
                                    ->label(__('common.type'))
                                    ->options(PageType::select())
                                    ->default(PageType::PAGE)
                                    ->required()
                                    ->native(false),
                            ])
                            ->columnSpanFull()
                            ->columns(3),

                        Grid::make()
                            ->schema([
                                Toggle::make('public')
                                    ->label(__('common.public'))
                                    ->offIcon(Heroicon::XCircle)
                                    ->offColor('danger')
                                    ->onIcon(Heroicon::CheckCircle)
                                    ->onColor('success'),

                                Toggle::make('enabled')
                                    ->label(__('common.enabled'))
                                    ->offIcon(Heroicon::XCircle)
                                    ->offColor('danger')
                                    ->onIcon(Heroicon::CheckCircle)
                                    ->onColor('success'),
                            ])
                            ->columnSpanFull()
                            ->columns(),
                    ])
                    ->columnSpanFull(),

                Section::make(__('common.content'))
                    ->schema([
                        RichEditor::make('body')
                            ->label(__('common.content'))
                            ->requiredIf('type', PageType::PAGE)
                            ->visibleJs(<<<'JS'
                                    $get('type') == '0'
                                JS), // Hardcoded PageType::PAGE

                        TextInput::make('link')
                            ->label(__('common.link'))
                            ->url()
                            ->requiredIf('type', PageType::LINK)
                            ->visibleJs(<<<'JS'
                                    $get('type') == '1'
                                JS), // Hardcoded PageType::LINK

                        Toggle::make('new_window')
                            ->label(__('filament.should_open_in_new_tab'))
                            ->offIcon(Heroicon::XCircle)
                            ->offColor('danger')
                            ->onIcon(Heroicon::CheckCircle)
                            ->onColor('success')
                            ->visibleJs(<<<'JS'
                                    $get('type') == '1'
                                JS), // Hardcoded PageType::LINK
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
