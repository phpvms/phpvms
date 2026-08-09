<?php

namespace App\Filament\Resources\Awards\Schemas;

use App\Enums\AwardTrigger;
use App\Models\Award;
use App\Services\Awards\Constraints\PirepConstraint;
use App\Services\Awards\SnippetConstraints;
use App\Services\Awards\UserConstraints;
use App\Services\AwardService;
use Closure;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
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

class AwardForm
{
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
                    ->columnSpanFull()
                    ->visible(fn (?Award $record, string $operation): bool => $operation === 'edit' && $record?->ref_model_type === null),
            ]);
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

            // The badge comes from an upload or a remote URL, never both —
            // tabs make that either/or explicit instead of two live fields.
            Tabs::make()
                ->tabs([
                    Tab::make(__('common.image'))
                        ->schema([
                            FileUpload::make('image_file')
                                ->hiddenLabel()
                                ->image()
                                ->imageEditor()
                                ->disk(config('filesystems.public_files'))
                                ->directory('awards'),
                        ]),

                    Tab::make(__('common.image_url'))
                        ->schema([
                            TextInput::make('image_url')
                                ->hiddenLabel()
                                ->url(),
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
