<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AwardResource\Pages\CreateAward;
use App\Filament\Resources\AwardResource\Pages\EditAward;
use App\Filament\Resources\AwardResource\Pages\ListAwards;
use App\Filament\Resources\AwardResource\RelationManagers\UsersRelationManager;
use App\Models\Award;
use App\Services\AwardService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AwardResource extends Resource
{
    protected static ?string $model = Award::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Awards';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $awards = [];

        $award_classes = app(AwardService::class)->findAllAwardClasses();
        foreach ($award_classes as $class_ref => $award) {
            $awards[$class_ref] = $award->name;
        }

        return $schema
            ->components([
                Section::make('Award Information')
                    ->description('These are the awards that pilots can earn. Each award is assigned an award class, which will be run whenever a pilot\'s stats are changed, including after a PIREP is accepted.')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->string(),

                        RichEditor::make('description'),

                        TextInput::make('image_url')
                            ->label('Image URL')
                            ->url(),

                        FileUpload::make('image_file')
                            ->label('Image')
                            ->image()
                            ->imageEditor()
                            ->disk(config('filesystems.public_files'))
                            ->directory('awards'),

                        Grid::make('')
                            ->schema([
                                Select::make('ref_model')
                                    ->label('Award Class')
                                    ->searchable()
                                    ->native(false)
                                    ->options($awards),

                                TextInput::make('ref_model_params')
                                    ->label('Award Class parammeters')
                                    ->string(),
                            ])->columnSpan(1),

                        Toggle::make('active')
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
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('description'),

                ImageColumn::make('image_url')
                    ->height(100),

                IconColumn::make('active')
                    ->label('Active')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Award'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAwards::route('/'),
            'create' => CreateAward::route('/create'),
            'edit'   => EditAward::route('/{record}/edit'),
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
