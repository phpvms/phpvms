<?php

declare(strict_types=1);

namespace Modules\PhpvmsAirsideTheme\Providers;

use App\Contracts\Modules\ServiceProvider;
use App\Features\Themes\ThemeFields;
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
     * collapsed width is left at the package default. These classes must be
     * in the theme build: `resources/css/airside.css` sources this directory.
     */
    private function customizeSidebarWidth(): void
    {
        TallStackUi::customize()
            ->sideBar()
            ->block('desktop.wrapper.first.size', 'md:w-64')
            ->block('desktop.sizes.expanded', 'w-64');

        TallStackUi::customize()
            ->layout()
            ->block('wrapper.second.expanded', 'md:pl-64');

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
     * The signed-in shell on the landing look: content and the dispatch strip on
     * rounded surfaces over the canvas, the sidebar with no panel or border and
     * a white pill for the current item. Colours come from the `--vms-*` tokens
     * in `resources/css/airside.css`, so dark mode follows them.
     */
    private function customizeShell(): void
    {
        TallStackUi::customize()
            ->layout()
            ->block('main', 'vms-shell-main mx-auto w-full max-w-full p-(--vms-gutter)');

        // The strip: the airline, then the dispatch status, the clocks pushed right.
        TallStackUi::customize()
            ->layout('header')
            ->block('wrapper.base', 'vms-shell-header mx-(--vms-gutter) mt-(--vms-gutter) flex shrink-0 items-center gap-x-4 rounded-full bg-(--vms-surface) px-4 text-(--vms-ink) sm:px-5')
            ->block('slots.wrapper-multi-slot', 'gap-x-5')
            ->block('slots.right', 'ml-auto flex items-center')
            ->block('button.icon.size', 'size-6 text-(--vms-ink-2)')
            ->block('collapse.icon.size', 'size-5 text-(--vms-ink-2)');

        TallStackUi::customize()
            ->sideBar()
            ->block('desktop.wrapper.second', 'flex grow flex-col pb-4 transition-[width] duration-300')
            ->block('desktop.footer', 'shrink-0 overflow-hidden px-2 pt-3')
            ->block('mobile.wrapper.fourth', 'flex grow flex-col bg-(--vms-canvas) pb-4')
            ->block('mobile.footer', 'shrink-0 px-2 py-4')
            ->block('desktop.wrapper.fifth', 'flex flex-1 flex-col gap-y-1 px-2')
            ->block('mobile.wrapper.sixth', 'flex flex-1 flex-col gap-y-1 px-2');

        TallStackUi::customize()
            ->sideBar('item')
            ->block('group.button', 'flex w-full cursor-pointer items-center rounded-full px-3.5 py-2.5 text-left text-sm font-medium text-(--vms-ink-2) transition-all hover:bg-(--vms-surface)/60 hover:text-(--vms-ink)')
            ->block('group.button.collapsed', 'relative justify-center')
            ->block('group.icon.base', 'size-5 shrink-0 text-(--vms-ink-2)')
            ->block('group.icon.collapse.base', 'ml-auto size-4 shrink-0 text-(--vms-ink-3) transition-all')
            ->block('group.icon.collapse.rotate', 'rotate-180')
            ->block('group.group', 'pl-0')
            ->block('item.wrapper.base', 'py-px')
            ->block('item.wrapper.border', 'pl-8')
            ->block('item.state.base', 'group flex items-center rounded-full px-3.5 py-2.5 text-sm font-medium transition-all')
            ->block('item.state.current', 'bg-(--vms-surface) font-semibold text-(--vms-ink) shadow-xs')
            ->block('item.state.normal', 'text-(--vms-ink-2) hover:bg-(--vms-surface)/60 hover:text-(--vms-ink)')
            ->block('item.icon', 'size-5 shrink-0 text-(--vms-ink-2) transition-all');

        // The dashboard widget card: a surface with no shadow, header and body padding as in the mockup.
        TallStackUi::customize()
            ->card(scope: 'airside.widget')
            ->block('wrapper.second', 'flex w-full flex-col overflow-hidden bg-(--vms-surface) text-(--vms-ink)')
            ->block('border.radius.lg', 'rounded-(--vms-radius-lg)')
            ->block('header.wrapper.base', 'flex items-center justify-between px-5.5 pt-5 pb-3.5')
            ->block('header.wrapper.border', 'border-0')
            ->block('header.text.color', 'text-(--vms-ink)')
            ->block('body', 'grow px-5.5 pb-5.5 text-(--vms-ink)');
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
                ThemeOptions::colorSchemeSelect('style_css')
                    ->label(__('airside::customize.color_scheme')),
                ExpandableEditor::make(CodeEditor::make('style_css')
                    ->label(__('airside::customize.style_css'))
                    ->language(Language::Css)
                    ->default(fn (): string => app(ThemeFields::class)->defaultFor($theme, 'styles.css'))
                    ->meta('emit', 'css')),
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
