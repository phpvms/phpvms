<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\PageType;
use App\Models\Page;
use App\Services\PageService;
use Closure;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->components([
                Section::make(__('filament.page_information'))
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
                                    ->options(PageType::class)
                                    ->native(false)
                                    ->required(),
                            ])
                            ->columnSpanFull()
                            ->columns(3),

                        TextInput::make('path')
                            ->label(__('filament.system_pages.path'))
                            ->helperText(__('filament.system_pages.path_helper'))
                            ->string()
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                ? Page::normalisePath($state)
                                : null)
                            ->rules([self::pathIsAvailable()])
                            ->columnSpanFull()
                            ->visibleJs(<<<'JS'
                                    $get('type') == '0'
                                JS), // Hardcoded PageType::PAGE

                        Grid::make()
                            ->schema([
                                Toggle::make('public')
                                    ->label(__('common.public'))
                                    ->offIcon(Phosphor::XLight)
                                    ->offColor('danger')
                                    ->onIcon(Phosphor::CheckLight)
                                    ->onColor('success'),

                                Toggle::make('enabled')
                                    ->label(__('common.enabled'))
                                    ->offIcon(Phosphor::XLight)
                                    ->offColor('danger')
                                    ->onIcon(Phosphor::CheckLight)
                                    ->onColor('success'),
                            ])
                            ->columnSpanFull()
                            ->columns(),

                        // Create-only and not stored on the page: CreatePage reads
                        // these once the page exists and builds the menu item.
                        Grid::make()
                            ->schema([
                                Toggle::make('add_to_menu')
                                    ->label(__('filament.navigation_items.add_to_menu'))
                                    ->helperText(__('filament.navigation_items.add_to_menu_helper'))
                                    ->default(true)
                                    ->live()
                                    ->dehydrated(false)
                                    ->offIcon(Phosphor::XLight)
                                    ->offColor('danger')
                                    ->onIcon(Phosphor::CheckLight)
                                    ->onColor('success'),

                                TextInput::make('menu_label')
                                    ->label(__('filament.navigation_items.menu_label'))
                                    ->helperText(__('filament.navigation_items.menu_label_helper'))
                                    ->string()
                                    ->dehydrated(false)
                                    ->visible(fn (Get $get): bool => (bool) $get('add_to_menu')),
                            ])
                            ->columnSpanFull()
                            ->columns()
                            ->hiddenOn('edit')
                            ->visibleJs(<<<'JS'
                                    $get('type') == '0'
                                JS), // Hardcoded PageType::PAGE
                    ])
                    ->columnSpanFull(),

                Section::make(__('common.content'))
                    ->schema([
                        RichEditor::make('draft_body')
                            ->label(__('filament.system_pages.draft_content'))
                            ->helperText(__('filament.system_pages.draft_helper'))
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
                            ->offIcon(Phosphor::XLight)
                            ->offColor('danger')
                            ->onIcon(Phosphor::CheckLight)
                            ->onColor('success')
                            ->visibleJs(<<<'JS'
                                    $get('type') == '1'
                                JS), // Hardcoded PageType::LINK
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Reject a path the site already answers, or one another page (published or
     * draft) already holds. Checked on save rather than discovered at render time, so an author
     * finds out while they are still looking at the field.
     *
     * Wrapped in an outer function because Filament evaluates entries in
     * `rules()` with its own utility injection; the inner closure is the Laravel
     * rule and must be handed back untouched.
     */
    private static function pathIsAvailable(): Closure
    {
        return static fn (?Page $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if (blank($value)) {
                return;
            }

            /** @var PageService $pages */
            $pages = app(PageService::class);
            $path = Page::normalisePath((string) $value);

            if ($pages->pathIsReserved($path)) {
                $fail(__('filament.system_pages.path_taken_route'));

                return;
            }

            if (!$pages->pathIsAvailable($path, $record)) {
                $fail(__('filament.system_pages.path_claimed'));
            }
        };
    }
}
