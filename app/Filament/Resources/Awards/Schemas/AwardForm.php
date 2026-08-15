<?php

namespace App\Filament\Resources\Awards\Schemas;

use App\Enums\AwardTrigger;
use App\Models\Award;
use App\Services\Awards\AwardRunService;
use App\Services\Awards\Constraints\PirepConstraint;
use App\Services\Awards\CriteriaCompilationFailed;
use App\Services\Awards\SnippetConstraints;
use App\Services\Awards\UserConstraints;
use App\Services\AwardService;
use App\Services\ImageUploadService;
use Closure;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class AwardForm
{
    /** Where the icon picker's glyphs come from, linked from the field itself. */
    private const string ICON_SET_URL = 'https://tabler.io/icons';

    /**
     * What the icon dropdown shows before anything is typed.
     *
     * Without these the field opens empty, which reads as a broken text box
     * rather than a picker. Alphabetical order would be no better -- the set
     * starts at `a-b` and `abacus` -- so this is a hand-picked shortlist of the
     * glyphs an award actually wants, with search reaching the other ~7200.
     */
    private const array STARTER_ICONS = [
        'trophy', 'award', 'medal', 'medal-2', 'certificate', 'crown',
        'star', 'diamond', 'flag', 'shield-check', 'thumb-up', 'heart-filled',
        'plane', 'plane-departure', 'plane-arrival', 'route', 'world', 'map-pin',
        'clock', 'clock-hour-4', 'calendar', 'target-arrow', 'rocket', 'bolt',
        'moon', 'sun', 'flame', 'mountain', 'anchor', 'building-arch',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.awards_information'))
                    ->description(__('filament.awards_description'))
                    ->schema([...self::infoFields(), self::enabledField()])
                    ->columnSpanFull()
                    ->columns(2)
                    ->hiddenOn('edit'),

                // Rules-based awards only, and only once the award exists —
                // the constraint set is built around the saved record's
                // trigger. EditAward hydrates and persists the tree.
                Section::make(__('filament.award_conditions'))
                    ->description(__('filament.award_conditions_description'))
                    ->schema(fn (?Award $record): array => [self::criteriaField($record)])
                    // Sits at the left of the same footer the save pair uses:
                    // it answers a question about the criteria directly above
                    // it, so it belongs with them rather than in the page
                    // header. See ReversePrimaryButtons::getFormContentComponent().
                    ->footerActions([self::runTestAction()])
                    ->columnSpanFull()
                    ->visible(fn (?Award $record, string $operation): bool => $operation === 'edit' && $record?->ref_model_type === null),
            ]);
    }

    /**
     * Dry run: who the criteria currently match, without granting anything.
     *
     * The result is a table in the modal rather than a notification, because
     * "six users" is only useful if you can see which six -- an unexpected
     * count is the first sign that a rule is broader than intended. A tree
     * that will not compile reports that here too, since it is the answer to
     * the same question.
     */
    private static function runTestAction(): Action
    {
        return Action::make('runTest')
            ->label(__('filament.award_run_test'))
            ->icon(TablerIcon::Flask)
            ->color('gray')
            ->visible(fn (?Award $record): bool => $record?->rule !== null)
            ->modalHeading(__('filament.award_run_test'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament.award_run_test_close'))
            ->modalContent(fn (Award $record): View => self::runTestResults($record));
    }

    /**
     * The dry run behind that button, as a renderable view.
     *
     * Separate from the action so it can be exercised directly: driving a
     * schema-component action through Livewire's test harness proves the
     * plumbing, not the answer, and this is where the answer is decided.
     */
    public static function runTestResults(Award $award): View
    {
        try {
            return view('filament.awards.run-test-results', [
                'users' => app(AwardRunService::class)->run($award, grant: false),
                'error' => null,
            ]);
        } catch (CriteriaCompilationFailed $criteriaCompilationFailed) {
            return view('filament.awards.run-test-results', [
                'users' => collect(),
                'error' => $criteriaCompilationFailed->getMessage(),
            ]);
        }
    }

    /**
     * The award's criteria: an ordinary form field over the whole `users`
     * vocabulary, the PIREP constraint, and one constraint per saved snippet.
     *
     * The triggering-PIREP scope is only offered to a PIREP-triggered award,
     * and a tree that carries it is refused under the nightly trigger — the
     * scope binds nothing there, so the rule would silently widen to every
     * PIREP the pilot has ever filed.
     */
    public static function criteriaField(?Award $record): RuleBuilder
    {
        $isPirepTriggered = $record?->trigger === AwardTrigger::Pirep;

        return RuleBuilder::make('conditions')
            ->hiddenLabel()
            ->constraints([
                ...UserConstraints::make(),
                PirepConstraint::make()->allowTriggeringPirepScope($isPirepTriggered),
                ...SnippetConstraints::make(),
            ])
            ->rules(fn (): array => [
                static function (string $attribute, mixed $value, Closure $fail) use ($isPirepTriggered): void {
                    if (!$isPirepTriggered && is_array($value) && PirepConstraint::treeUsesTriggeringPirepScope($value)) {
                        $fail(__('filament.award_conditions_pirep_scope'));
                    }
                },
            ]);
    }

    /**
     * The award-type selection — legacy class/params vs rules trigger — shown
     * in the edit page's settings drawer below the identity fields. The
     * virtual `type` toggle pairs with injectTypeIntoFillData() /
     * mutateTypeFields() on whatever schema hosts these fields.
     *
     * @return array<int, Component>
     */
    public static function typeFields(): array
    {
        $awards = [];

        $award_classes = app(AwardService::class)->findAllAwardClasses();
        foreach ($award_classes as $class_ref => $award) {
            $awards[$class_ref] = $award->name;
        }

        return [
            ToggleButtons::make('type')
                ->label(__('filament.award_type'))
                ->options([
                    'legacy' => __('filament.award_type_legacy'),
                    'rules'  => __('filament.award_type_rules'),
                ])
                ->default(fn (?Award $record): string => $record?->ref_model_type !== null ? 'legacy' : 'rules')
                ->grouped()
                ->live()
                ->required(),

            Select::make('ref_model_type')
                ->label(__('filament.award_class'))
                ->required(fn (Get $get): bool => $get('type') === 'legacy')
                ->searchable()
                ->native(false)
                ->options($awards)
                ->visible(fn (Get $get): bool => $get('type') === 'legacy'),

            TextInput::make('ref_model_params')
                ->required(fn (Get $get): bool => $get('type') === 'legacy')
                ->label(__('filament.award_class_param'))
                ->string()
                ->visible(fn (Get $get): bool => $get('type') === 'legacy'),

            Radio::make('trigger')
                ->label(__('filament.award_trigger'))
                ->options(AwardTrigger::class)
                ->descriptions([
                    AwardTrigger::Pirep->value => __('filament.award_trigger_pirep_description'),
                    AwardTrigger::User->value  => __('filament.award_trigger_user_description'),
                ])
                ->default(AwardTrigger::Pirep->value)
                ->live()
                ->required(fn (Get $get): bool => $get('type') === 'rules')
                ->visible(fn (Get $get): bool => $get('type') === 'rules'),
        ];
    }

    /**
     * The award's identity fields — the create page's only section, and the
     * edit page's settings drawer (EditDetailsAction).
     *
     * @return array<int, Component>
     */
    public static function infoFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('common.name'))
                ->required()
                ->string(),

            // A Select rather than a text box with a datalist: the list is short
            // enough to show whole, and a datalist input renders no dropdown
            // arrow in Chrome, so it reads as a plain text field. Filament's
            // create-option button is the "type my own" escape hatch, and a
            // category joins the list as soon as an award carrying it is saved
            // (categoryOptions() reads the distinct values back out).
            Select::make('category')
                ->label(__('common.category'))
                ->options(fn (): array => Award::categoryOptions())
                ->searchable()
                ->native(false)
                ->createOptionForm([
                    TextInput::make('category')
                        ->label(__('common.category'))
                        ->required()
                        ->string()
                        ->maxLength(64),
                ])
                // Uppercased on the way in so "Milestone" and "MILESTONE" do
                // not become two entries in that list.
                ->createOptionUsing(fn (array $data): string => Str::upper(trim((string) $data['category'])))
                // A category is its own label, and a just-created one is not in
                // the options yet -- nothing is saved until the award is.
                ->getOptionLabelUsing(fn (?string $value): ?string => $value),

            // The badge comes from an upload, a remote URL, or an icon, never
            // more than one — tabs make that either/or explicit instead of
            // three live fields.
            Tabs::make()
                ->tabs([
                    Tab::make(__('common.image'))
                        ->schema([
                            FileUpload::make('image_file')
                                ->hiddenLabel()
                                ->image()
                                ->imageEditor()
                                ->disk(config('filesystems.public_files'))
                                ->directory('awards')
                                // Converts to WebP through the shared upload
                                // service instead of storing whatever format
                                // was dropped; see ImageUploadService.
                                ->saveUploadedFileUsing(
                                    fn (FileUpload $component, TemporaryUploadedFile $file): string => app(ImageUploadService::class)->storeFilamentUpload($component, $file)
                                ),
                        ]),

                    Tab::make(__('common.image_url'))
                        ->schema([
                            TextInput::make('image_url')
                                ->hiddenLabel()
                                ->url(),
                        ]),

                    Tab::make(__('common.icon'))
                        ->schema([
                            Select::make('icon')
                                ->hiddenLabel()
                                // Shown before anything is typed. Filament hands
                                // `options` to the JS regardless of the search
                                // callback, so the two coexist: this is the
                                // opening list, searchIcons() is the whole set.
                                ->options(fn (): array => self::starterIcons())
                                // HtmlString, not a plain string: helperText
                                // escapes what it is given, so an anchor would
                                // otherwise render as visible markup.
                                ->helperText(new HtmlString(
                                    '<a href="'.self::ICON_SET_URL.'" target="_blank" rel="noopener noreferrer" class="underline hover:no-underline">'
                                    .e(__('filament.award_icon_browse'))
                                    .'</a>'
                                ))
                                ->searchable()
                                ->native(false)
                                ->allowHtml()
                                ->placeholder(__('filament.award_icon_placeholder'))
                                ->getSearchResultsUsing(fn (string $search): array => self::searchIcons($search))
                                ->getOptionLabelUsing(fn (?string $value): ?string => $value === null ? null : self::iconOptionLabel($value)),
                        ]),
                ])
                ->columnSpanFull(),

            RichEditor::make('description')
                ->label(__('common.description'))
                ->extraAttributes(['class' => 'rich-editor-hover-toolbar'])
                ->columnSpanFull(),
        ];
    }

    /**
     * The shortlist above, rendered as pickable options.
     *
     * @return array<string, string>
     */
    public static function starterIcons(): array
    {
        $options = [];

        foreach (self::STARTER_ICONS as $name) {
            $icon = 'tabler-'.$name;
            $options[$icon] = self::iconOptionLabel($icon);
        }

        return $options;
    }

    /**
     * Tabler icon options matching a search term.
     *
     * The set is ~7200 icons, far too many to hand a Select as a flat option
     * array, so this backs `getSearchResultsUsing()` and caps what it returns.
     * Spaces are treated as hyphens so "plane takeoff" finds `plane-takeoff`.
     *
     * Public for the same reason as the other members here — the answer it
     * gives is worth testing directly, rather than through a Select.
     *
     * @return array<string, string>
     */
    public static function searchIcons(string $search): array
    {
        $needle = str_replace(' ', '-', mb_strtolower(trim($search)));

        $options = [];

        foreach (TablerIcon::cases() as $case) {
            if ($needle !== '' && !str_contains($case->value, $needle)) {
                continue;
            }

            $icon = 'tabler-'.$case->value;
            $options[$icon] = self::iconOptionLabel($icon);

            if (count($options) >= 50) {
                break;
            }
        }

        // Nothing in the set matched, so offer the term itself. That is how a
        // name from outside Tabler gets in -- a heroicon, or an icon a theme or
        // add-on registers with blade-icons. It is stored verbatim and rendered
        // by name, so anything blade-icons can resolve works here.
        if ($options === [] && $needle !== '') {
            $options[$needle] = self::iconOptionLabel($needle);
        }

        return $options;
    }

    /**
     * One icon rendered as its own option label: the glyph, then its name.
     *
     * Icons are dropped and renamed between releases of the icon set, so a
     * name saved months ago may no longer resolve — that falls back to the
     * bare name rather than throwing on a page that is only listing options.
     */
    private static function iconOptionLabel(string $icon): string
    {
        $name = str_replace('-', ' ', Str::after($icon, 'tabler-'));

        try {
            $svg = svg($icon, 'w-5 h-5 shrink-0')->toHtml();
        } catch (Throwable) {
            return e($name);
        }

        return '<span class="flex items-center gap-2">'.$svg.'<span>'.e($name).'</span></span>';
    }

    /**
     * The on/off switch, always the last field on whatever schema hosts it,
     * separated from the settings above by a rule (bundle drawer convention).
     */
    public static function enabledField(): Toggle
    {
        return Toggle::make('active')
            ->label(__('common.enabled'))
            ->offIcon(icon: TablerIcon::X)
            ->offColor('danger')
            ->onIcon(icon: TablerIcon::Check)
            ->onColor('success')
            ->default(true)
            ->columnSpanFull()
            ->extraFieldWrapperAttributes(['class' => 'field-rule-above']);
    }

    /**
     * The virtual `type` selector's `default()` only applies when a Filament
     * form is filled with no data at all (i.e. a Create page) — Filament's
     * hydration skips defaults entirely when an edit record's attributes are
     * being filled, so an Edit page must inject `type` into the data being
     * filled itself, from the record's own `ref_model_type` column.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function injectTypeIntoFillData(array $data): array
    {
        $data['type'] = ($data['ref_model_type'] ?? null) !== null ? 'legacy' : 'rules';

        return $data;
    }

    /**
     * Nulls the fields belonging to whichever award type wasn't selected,
     * and drops the virtual `type` selector before it reaches the model
     * (spec "Editing a legacy award" / "the two configurations are mutually
     * exclusive on one award"). The conditions tree lives on the AwardRule
     * row, not the awards table — switching to legacy deletes that row in
     * the caller's after-hook (Award::saveConditionsTree(null)).
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function mutateTypeFields(array $data): array
    {
        $type = $data['type'] ?? 'rules';
        unset($data['type']);

        if ($type === 'legacy') {
            $data['trigger'] = null;
        } else {
            $data['ref_model_type'] = null;
            $data['ref_model_params'] = null;
        }

        return $data;
    }
}
