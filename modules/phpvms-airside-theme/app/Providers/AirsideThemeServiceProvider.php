<?php

declare(strict_types=1);

namespace Modules\PhpvmsAirsideTheme\Providers;

use App\Contracts\Modules\ServiceProvider;
use App\Features\Themes\ThemeOptions;
use App\Filament\Support\ExpandableEditor;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Blade;
use Modules\PhpvmsAirsideTheme\Support\AirsideVite;
use Modules\PhpvmsAirsideTheme\Support\NavigationIcon;
use Override;
use TallStackUi\Facades\TallStackUi;

/**
 * airside: the bundled Blade + Livewire pilot theme.
 *
 * Everything TallStackUI-specific lives here, never in core: the component
 * prefix, the icon pack, the `<x-vms::*>` component namespace and the
 * `@airsideVite` directive. Views reach the request-scoped ShellData with
 * `@inject('shell', ShellData::class)`. Components render with TallStackUI's
 * defaults except for a small soft customisation: avatar border and sidebar
 * widths (see `boot()`).
 */
class AirsideThemeServiceProvider extends ServiceProvider
{
    public const string COMPONENT_PREFIX = 'ts-';

    /**
     * TallStackUI's internal icon names (its config `custom.guide` keys) and
     * the Phosphor icon that stands in for each.
     *
     * @var array<string, string>
     */
    public const array ICON_GUIDE = [
        'arrow-down-tray'      => 'download-simple',
        'arrow-path'           => 'arrow-clockwise',
        'arrow-trending-up'    => 'trend-up',
        'arrow-trending-down'  => 'trend-down',
        'arrow-up-tray'        => 'upload-simple',
        'arrow-uturn-left'     => 'arrow-u-up-left',
        'arrow-uturn-right'    => 'arrow-u-up-right',
        'arrows-pointing-in'   => 'arrows-in',
        'arrows-pointing-out'  => 'arrows-out',
        'backspace'            => 'backspace',
        'bars-3-bottom-left'   => 'text-align-left',
        'bars-4'               => 'list',
        'calendar'             => 'calendar',
        'check'                => 'check',
        'check-circle'         => 'check-circle',
        'chevron-double-left'  => 'caret-double-left',
        'chevron-double-right' => 'caret-double-right',
        'chevron-down'         => 'caret-down',
        'chevron-left'         => 'caret-left',
        'chevron-right'        => 'caret-right',
        'chevron-up'           => 'caret-up',
        'chevron-up-down'      => 'caret-up-down',
        'clipboard'            => 'clipboard',
        'clipboard-document'   => 'clipboard-text',
        'clock'                => 'clock',
        'cloud-arrow-up'       => 'cloud-arrow-up',
        'code-bracket'         => 'code',
        'code-bracket-square'  => 'code-block',
        'computer-desktop'     => 'desktop',
        'document-arrow-down'  => 'file-arrow-down',
        'document-check'       => 'file-text',
        'document-text'        => 'file-text',
        'ellipsis-vertical'    => 'dots-three-vertical',
        'exclamation-circle'   => 'warning-circle',
        'eye'                  => 'eye',
        'eye-slash'            => 'eye-slash',
        'information-circle'   => 'info',
        'link'                 => 'link',
        'list-bullet'          => 'list-bullets',
        'magnifying-glass'     => 'magnifying-glass',
        'minus'                => 'minus',
        'moon'                 => 'moon',
        'numbered-list'        => 'list-numbers',
        'photo'                => 'image',
        'plus'                 => 'plus',
        'question-mark-circle' => 'question',
        'swatch'               => 'swatches',
        'sun'                  => 'sun',
        'trash'                => 'trash',
        'x-circle'             => 'x-circle',
        'x-mark'               => 'x',
    ];

    #[Override]
    public function register(): void
    {
        parent::register();

        // TallStackUI reads the prefix while it registers its components,
        // which happens after every provider's register(). `tallstackui` is the
        // key its config file is published under; `ts-ui` is the merged copy
        // the package reads at runtime, and it is merged before this runs.
        config([
            'tallstackui.prefix' => self::COMPONENT_PREFIX,
            'ts-ui.prefix'       => self::COMPONENT_PREFIX,
        ]);

        $this->useAdminIconPack();
    }

    /**
     * Avatars drop TallStackUI's default `border-2`. The avatar has no ring or
     * outline class in another block; `border.radius` stays so it stays round.
     */
    private function customizeAvatar(): void
    {
        TallStackUi::customize()->avatar()->block('border.base', 'border-0');
    }

    /**
     * Sidebar width. TallStackUI ships `w-72`; the pilot menu is short, so the
     * rail takes 16rem and the layout's content padding follows it. The
     * collapsed rail is 4rem: every icon centre sits 2rem from the left edge,
     * so the icons stay put while the rail slides and need no re-centring.
     * These classes must be in the theme build: `resources/css/styles.css`
     * sources this directory.
     */
    private function customizeSidebarWidth(): void
    {
        TallStackUi::customize()
            ->sideBar()
            ->block('desktop.wrapper.first.size', 'md:w-64')
            ->block('desktop.sizes.expanded', 'w-64')
            ->block('desktop.sizes.collapsed', 'w-16');

        TallStackUi::customize()
            ->layout()
            ->block('wrapper.second.expanded', 'md:pl-64')
            ->block('wrapper.second.collapsed', 'md:pl-16');

        // The pilot menu dropdown in the sidebar footer: the package wraps
        // its trigger in an inline-block, which shrinks to the avatar on the
        // collapsed rail and leaves it off-centre. Full width lets the
        // trigger's own layout centre it.
        TallStackUi::customize()
            ->dropdown(scope: 'airside.pilot-menu')
            ->block('wrapper.first', 'flex')
            ->block('wrapper.second', 'relative block w-full text-left');
    }

    /**
     * The signed-in shell: a white page with hairline-bordered rounded surfaces
     * (the dispatch strip, widgets), a grey sidebar panel (`vms-shell-sidebar`)
     * and a white pill for the current item. The blocks keep layout utilities and
     * add `vms-*` hooks; colours (and dark mode) are set by those hooks in
     * `resources/css/airside.css`.
     */
    private function customizeShell(): void
    {
        TallStackUi::customize()
            ->layout()
            ->block('main', 'vms-shell-main mx-auto w-full max-w-full p-(--vms-gutter)');

        // The strip: the airline, then the dispatch status, the clocks pushed right.
        TallStackUi::customize()
            ->layout('header')
            ->block('wrapper.base', 'vms-shell-header mx-(--vms-gutter) mt-(--vms-gutter) flex shrink-0 items-center gap-x-4 rounded-full px-4 sm:px-5')
            ->block('slots.wrapper-multi-slot', 'gap-x-5')
            ->block('slots.right', 'ml-auto flex items-center')
            ->block('button.icon.size', 'vms-shell-icon size-6')
            ->block('collapse.icon.size', 'vms-shell-icon size-5');

        TallStackUi::customize()
            ->sideBar()
            ->block('desktop.wrapper.second', 'vms-shell-sidebar flex grow flex-col pb-4 transition-[width] duration-300')
            ->block('desktop.footer', 'shrink-0 overflow-hidden px-2 pt-3')
            ->block('mobile.wrapper.fourth', 'vms-shell-sidebar flex grow flex-col pb-4')
            ->block('mobile.footer', 'shrink-0 px-2 py-4')
            ->block('desktop.wrapper.fifth', 'flex flex-1 flex-col gap-y-1 px-2')
            ->block('mobile.wrapper.sixth', 'flex flex-1 flex-col gap-y-1 px-2');

        TallStackUi::customize()
            ->sideBar('item')
            ->block('group.button', 'vms-shell-item flex w-full cursor-pointer items-center rounded-full px-3.5 py-2.5 text-left text-sm font-medium transition-all')
            ->block('group.button.collapsed', 'relative')
            ->block('group.icon.base', 'vms-shell-icon size-5 shrink-0')
            ->block('group.icon.collapse.base', 'vms-shell-icon-muted ml-auto size-4 shrink-0 transition-all')
            ->block('group.icon.collapse.rotate', 'rotate-180')
            ->block('group.group', 'pl-0')
            ->block('item.wrapper.base', 'py-px')
            ->block('item.wrapper.border', 'pl-8')
            ->block('item.state.base', 'group flex items-center rounded-full px-3.5 py-2.5 text-sm font-medium transition-all')
            ->block('item.state.current', 'vms-shell-item-current font-semibold shadow-xs')
            ->block('item.state.normal', 'vms-shell-item')
            ->block('item.state.collapsed', 'relative')
            ->block('item.icon', 'vms-shell-icon size-5 shrink-0 transition-all');

        // The dashboard widget card: a surface with no shadow, header and body padding as in the mockup.
        TallStackUi::customize()
            ->card(scope: 'airside.widget')
            ->block('wrapper.second', 'vms-widget-card flex w-full flex-col overflow-hidden')
            ->block('border.radius.lg', 'rounded-(--vms-radius-lg)')
            ->block('header.wrapper.base', 'flex items-center justify-between px-5.5 pt-5 pb-3.5')
            ->block('header.wrapper.border', 'border-0')
            ->block('header.text.color', 'vms-widget-card-title')
            ->block('body', 'vms-widget-card-body grow px-5.5 pb-5.5 in-[.vms-widget--bare]:p-0');
    }

    /**
     * Draw TallStackUI's icons from the Phosphor pack the admin panel uses
     * (BasePanelProvider, light style). `type` names the BladeUI package, which
     * switches the icon component to custom mode; `custom.guide` maps every
     * icon the components use internally to its Phosphor light counterpart.
     * Views pass pack icons as `phosphor.<name>.light`.
     */
    private function useAdminIconPack(): void
    {
        $guide = [];

        foreach (self::ICON_GUIDE as $internal => $phosphor) {
            $guide[$internal] = 'phosphor-'.$phosphor.'-'.NavigationIcon::STYLE;
        }

        foreach (['tallstackui', 'ts-ui'] as $root) {
            config([
                $root.'.components.icon.1.type'         => 'codeat3/blade-phosphor-icons',
                $root.'.components.icon.1.custom.guide' => $guide,
            ]);
        }
    }

    #[Override]
    public function boot(): void
    {
        parent::boot();

        Blade::anonymousComponentNamespace('components', 'vms');

        $this->customizeSidebarWidth();
        $this->customizeShell();
        $this->customizeAvatar();

        $this->registerViteDirective();
        $this->registerThemeOptions();
    }

    /**
     * The form on the Theme admin page. The closure builds fresh components on
     * each call and holds no request state, so it is safe under Octane.
     */
    private function registerThemeOptions(): void
    {
        app(ThemeOptions::class)->register('airside', fn (string $theme): array => [
            Tab::make(__('airside::customize.tab_css'))->schema([
                ThemeOptions::colorSchemeSelect('phpvms')
                    ->label(__('airside::customize.color_scheme')),
                ExpandableEditor::make(CodeEditor::make('custom_css')
                    ->label(__('airside::customize.custom_css'))
                    ->language(Language::Css)
                    ->meta('emit', 'css')),
            ]),
            Tab::make(__('airside::customize.tab_inserts'))->schema([
                ExpandableEditor::make(CodeEditor::make('header')
                    ->label(__('airside::customize.header'))
                    ->language(Language::Html)
                    ->meta('emit', 'head')),
                ExpandableEditor::make(CodeEditor::make('footer')
                    ->label(__('airside::customize.footer'))
                    ->language(Language::Html)
                    ->meta('emit', 'footer')),
            ]),
            Tab::make(__('airside::customize.tab_layout'))->schema([
                Select::make('layout')
                    ->label(__('airside::customize.layout'))
                    ->options([
                        'sidebar' => __('airside::customize.layout_sidebar'),
                        'topbar'  => __('airside::customize.layout_topbar'),
                    ])
                    ->in(['sidebar', 'topbar'])
                    ->default('sidebar')
                    ->required(),
                Select::make('drawer_style')
                    ->label(__('airside::customize.drawer_style'))
                    ->helperText(__('airside::customize.drawer_style_help'))
                    ->options([
                        'slide' => __('airside::customize.drawer_style_slide'),
                        'modal' => __('airside::customize.drawer_style_modal'),
                    ])
                    ->in(['slide', 'modal'])
                    ->default('slide')
                    ->required(),
                Select::make('ready_map_scope')
                    ->label(__('airside::customize.ready_map_scope'))
                    ->options([
                        'country'   => __('airside::customize.ready_map_scope_country'),
                        'continent' => __('airside::customize.ready_map_scope_continent'),
                        'world'     => __('airside::customize.ready_map_scope_world'),
                    ])
                    ->in(['country', 'continent', 'world'])
                    ->default('country')
                    ->required(),
                Select::make('ready_map_style')
                    ->label(__('airside::customize.ready_map_style'))
                    ->options([
                        'outline' => __('airside::customize.ready_map_style_outline'),
                        'dotted'  => __('airside::customize.ready_map_style_dotted'),
                    ])
                    ->in(['outline', 'dotted'])
                    ->default('dotted')
                    ->required(),
                Select::make('ready_map_size')
                    ->label(__('airside::customize.ready_map_size'))
                    ->options([
                        'compact' => __('airside::customize.ready_map_size_compact'),
                        'hero'    => __('airside::customize.ready_map_size_hero'),
                    ])
                    ->in(['compact', 'hero'])
                    ->default('compact')
                    ->required(),
            ]),
        ]);
    }

    private function registerViteDirective(): void
    {
        $this->app->singleton(AirsideVite::class, fn (): AirsideVite => new AirsideVite($this->addonBasePath().'/public/hot'));

        Blade::directive('airsideVite', fn (string $expression): string => '<?php echo app('.var_export(AirsideVite::class, true).')('.$expression.'); ?>');
    }

    #[Override]
    protected function addonNamespace(): string
    {
        return 'airside';
    }
}
