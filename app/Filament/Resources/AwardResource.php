<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AwardResource\Pages;
use App\Models\Award;
use App\Services\AwardService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AwardResource extends Resource
{
    protected static ?string $model = Award::class;

    protected static ?string $navigationGroup = 'Config';
    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Awards';
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    public static function form(Form $form): Form
    {
        $awards = [];

        $award_classes = app(AwardService::class)->findAllAwardClasses();
        foreach ($award_classes as $class_ref => $award) {
            $awards[$class_ref] = $award->name;
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Award Information')
                    ->description('These are the awards that pilots can earn. Each award is assigned an award class, which will be run whenever a pilot\'s stats are changed, including after a PIREP is accepted.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->string(),

                        Forms\Components\TextInput::make('image_url')
                            ->string(),

                        Forms\Components\Textarea::make('description')
                            ->autosize(),

                        Forms\Components\Grid::make('')
                            ->schema([
                                Forms\Components\Select::make('ref_model')
                                    ->label('Award Class')
                                    ->searchable()
                                    ->native(false)
                                    ->options($awards),

                                Forms\Components\TextInput::make('ref_model_params')
                                    ->label('Award Class parammeters')
                                    ->string(),
                        ])->columnSpan(1),

                        Forms\Components\Toggle::make('active')
                            ->offIcon('heroicon-m-x-circle')
                            ->offColor('danger')
                            ->onIcon('heroicon-m-check-circle')
                            ->onColor('success')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description'),

                Tables\Columns\ImageColumn::make('image_url')
                    ->height(100),

                Tables\Columns\IconColumn::make('active')
                    ->label('Active')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Award'),
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
            'index'  => Pages\ListAwards::route('/'),
            'create' => Pages\CreateAward::route('/create'),
            'edit'   => Pages\EditAward::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
