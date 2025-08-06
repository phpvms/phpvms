<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Enums\PageType;
use App\Models\Page;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Pages';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page Information')->schema([
                    Grid::make('')->schema([
                        TextInput::make('name')
                            ->label('Page Name')
                            ->required()
                            ->string(),

                        TextInput::make('icon')
                            ->string(),

                        Select::make('type')
                            ->label('Page Type')
                            ->options(PageType::select())
                            ->default(PageType::PAGE)
                            ->required()
                            ->native(false)
                            ->live(),
                    ])->columns(3),
                    Grid::make('')->schema([
                        Toggle::make('public')
                            ->offIcon('heroicon-m-x-circle')
                            ->offColor('danger')
                            ->onIcon('heroicon-m-check-circle')
                            ->onColor('success'),

                        Toggle::make('enabled')
                            ->offIcon('heroicon-m-x-circle')
                            ->offColor('danger')
                            ->onIcon('heroicon-m-check-circle')
                            ->onColor('success')
                            ->default(true),
                    ])->columns(2),
                ]),
                Section::make('Content')->schema([
                    RichEditor::make('body')
                        ->label('Page Content')
                        ->required(fn (Get $get): bool => $get('type') == PageType::PAGE)
                        ->visible(fn (Get $get): bool => $get('type') == PageType::PAGE),

                    TextInput::make('link')
                        ->label('Page Link')
                        ->url()
                        ->required(fn (Get $get): bool => $get('type') == PageType::LINK)
                        ->visible(fn (Get $get): bool => $get('type') == PageType::LINK),

                    Toggle::make('new_window')
                        ->label('Open In New Window')
                        ->offIcon('heroicon-m-x-circle')
                        ->offColor('danger')
                        ->onIcon('heroicon-m-check-circle')
                        ->onColor('success')
                        ->visible(fn (Get $get): bool => $get('type') == PageType::LINK),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => PageType::label($state)),

                IconColumn::make('public')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),
                IconColumn::make('enabled')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Page'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit'   => EditPage::route('/{record}/edit'),
        ];
    }
}
