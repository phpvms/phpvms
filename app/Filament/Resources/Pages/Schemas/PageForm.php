<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\PageType;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Services\PageService;
use Closure;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    ])
                    ->aside()
                    ->description(__('filament.page_information_description'))
                    ->columnSpanFull()
                    ->columns(3),

                Section::make(__('filament.page_visibility'))
                    ->schema([
                        Toggle::make('public')
                            ->label(__('common.public'))
                            ->columnSpanFull()
                            ->inlineLabel()
                            ->offIcon(Phosphor::XLight)
                            ->offColor('danger')
                            ->onIcon(Phosphor::CheckLight)
                            ->onColor('success'),

                        Toggle::make('enabled')
                            ->label(__('common.enabled'))
                            ->columnSpanFull()
                            ->inlineLabel()
                            ->offIcon(Phosphor::XLight)
                            ->offColor('danger')
                            ->onIcon(Phosphor::CheckLight)
                            ->onColor('success'),
                    ])
                    ->aside()
                    ->description(__('filament.page_visibility_description'))
                    ->columnSpanFull(),

                // Create-only and not stored on the page: CreatePage reads
                // these once the page exists and builds the menu item.
                Section::make(__('filament.page_menu_item'))
                    ->schema([
                        TextInput::make('menu_label')
                            ->label(__('filament.navigation_items.menu_label'))
                            ->helperText(__('filament.navigation_items.menu_label_helper'))
                            ->string()
                            ->dehydrated(false)
                            ->visible(fn (Get $get): bool => (bool) $get('add_to_menu')),

                        Toggle::make('add_to_menu')
                            ->label(__('filament.navigation_items.add_to_menu'))
                            ->belowLabel(__('filament.navigation_items.add_to_menu_helper'))
                            ->columnSpanFull()
                            ->inlineLabel()
                            ->default(true)
                            ->live()
                            ->dehydrated(false)
                            ->offIcon(Phosphor::XLight)
                            ->offColor('danger')
                            ->onIcon(Phosphor::CheckLight)
                            ->onColor('success'),
                    ])
                    ->aside()
                    ->description(__('filament.page_menu_item_description'))
                    ->columnSpanFull()
                    ->hiddenOn('edit')
                    // Menu items have their own permission; page editors
                    // without it do not get a back door to the menu.
                    ->visible(fn (): bool => auth()->user()?->can('create', NavigationItem::class) ?? false)
                    ->visibleJs(<<<'JS'
                            $get('type') == '0'
                        JS), // Hardcoded PageType::PAGE

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
                            ->columnSpanFull()
                            ->inlineLabel()
                            ->offIcon(Phosphor::XLight)
                            ->offColor('danger')
                            ->onIcon(Phosphor::CheckLight)
                            ->onColor('success')
                            ->visibleJs(<<<'JS'
                                    $get('type') == '1'
                                JS), // Hardcoded PageType::LINK
                    ])
                    ->aside()
                    ->description(__('filament.page_content_description'))
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
