<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Enums\PageType;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationGroup = 'Config';
    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Pages';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Page Information')->schema([
                    Forms\Components\Grid::make('')->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Page Name')
                            ->required()
                            ->string(),

                        Forms\Components\TextInput::make('icon')
                            ->string(),

                        Forms\Components\Select::make('type')
                            ->label('Page Type')
                            ->options(PageType::select())
                            ->default(PageType::PAGE)
                            ->required()
                            ->native(false)
                            ->live(),
                    ])->columns(3),
                    Forms\Components\Grid::make('')->schema([
                        Forms\Components\Toggle::make('public')
                            ->offIcon('heroicon-m-x-circle')
                            ->offColor('danger')
                            ->onIcon('heroicon-m-check-circle')
                            ->onColor('success'),

                        Forms\Components\Toggle::make('enabled')
                            ->offIcon('heroicon-m-x-circle')
                            ->offColor('danger')
                            ->onIcon('heroicon-m-check-circle')
                            ->onColor('success')
                            ->default(true),
                    ])->columns(2),
                ]),
                Forms\Components\Section::make('Content')->schema([
                    Forms\Components\RichEditor::make('body')
                        ->label('Page Content')
                        ->required(fn (Forms\Get $get): bool => $get('type') == PageType::PAGE)
                        ->visible(fn (Forms\Get $get): bool => $get('type') == PageType::PAGE),

                    Forms\Components\TextInput::make('link')
                        ->label('Page Link')
                        ->url()
                        ->required(fn (Forms\Get $get): bool => $get('type') == PageType::LINK)
                        ->visible(fn (Forms\Get $get): bool => $get('type') == PageType::LINK),

                    Forms\Components\Toggle::make('new_window')
                        ->label('Open In New Window')
                        ->offIcon('heroicon-m-x-circle')
                        ->offColor('danger')
                        ->onIcon('heroicon-m-check-circle')
                        ->onColor('success')
                        ->visible(fn (Forms\Get $get): bool => $get('type') == PageType::LINK),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->formatStateUsing(fn (int $state): string => PageType::label($state)),

                Tables\Columns\IconColumn::make('public')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                Tables\Columns\IconColumn::make('enabled')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
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
            'index'  => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit'   => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
