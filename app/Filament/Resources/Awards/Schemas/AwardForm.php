<?php

namespace App\Filament\Resources\Awards\Schemas;

use App\Enums\AwardTrigger;
use App\Filament\Forms\Components\RuleBuilder;
use App\Models\Award;
use App\Models\AwardFact;
use App\Services\Awards\FactAllowlist;
use App\Services\Awards\PredefinedFacts;
use App\Services\Awards\RuleEvaluator;
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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AwardForm
{
    /**
     * Predefined fact names that resolve to a number (design.md D2 /
     * PredefinedFacts) — everything else (state, airport codes) is left
     * without an inputType override.
     *
     * @var list<string>
     */
    private const array NUMERIC_PREDEFINED_FACTS = [
        'flights',
        'flight_time',
        'rank_id',
        'airline_id',
        'months_since_joined',
        'pirep.landing_rate',
    ];

    public static function configure(Schema $schema): Schema
    {
        $awards = [];

        $award_classes = app(AwardService::class)->findAllAwardClasses();
        foreach ($award_classes as $class_ref => $award) {
            $awards[$class_ref] = $award->name;
        }

        return $schema
            ->components([
                Section::make(__('filament.awards_information'))
                    ->description(__('filament.awards_description'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->required()
                            ->string(),

                        TextInput::make('image_url')
                            ->label(__('common.image_url'))
                            ->url(),

                        RichEditor::make('description')
                            ->label(__('common.description'))
                            ->columnSpan(2),

                        FileUpload::make('image_file')
                            ->label(__('common.image'))
                            ->image()
                            ->imageEditor()
                            ->disk(config('filesystems.public_files'))
                            ->directory('awards'),

                        Toggle::make('active')
                            ->label(__('common.active'))
                            ->offIcon(icon: TablerIcon::X)
                            ->offColor('danger')
                            ->onIcon(icon: TablerIcon::Check)
                            ->onColor('success')
                            ->default(true),
                    ])
                    ->columnSpanFull()
                    ->columns(2),

                Section::make(__('filament.award_criteria'))
                    ->schema([
                        ToggleButtons::make('type')
                            ->label(__('filament.award_type'))
                            ->options([
                                'legacy' => __('filament.award_type_legacy'),
                                'rules'  => __('filament.award_type_rules'),
                            ])
                            ->default(fn (?Award $record): string => $record?->ref_model_type !== null ? 'legacy' : 'rules')
                            ->grouped()
                            ->live()
                            ->required()
                            ->columnSpanFull(),

                        Grid::make()
                            ->schema([
                                Select::make('ref_model_type')
                                    ->label(__('filament.award_class'))
                                    ->required(fn (Get $get): bool => $get('type') === 'legacy')
                                    ->searchable()
                                    ->native(false)
                                    ->options($awards),

                                TextInput::make('ref_model_params')
                                    ->required(fn (Get $get): bool => $get('type') === 'legacy')
                                    ->label(__('filament.award_class_param'))
                                    ->string(),
                            ])
                            ->columns(1)
                            ->columnSpan(1)
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
                            ->visible(fn (Get $get): bool => $get('type') === 'rules')
                            ->columnSpanFull(),

                        RuleBuilder::make('conditions')
                            ->label(__('filament.award_conditions'))
                            ->catalog(static fn (Get $get): array => self::catalogFor($get('trigger')))
                            ->required(fn (Get $get): bool => $get('type') === 'rules')
                            ->rules(static fn (Get $get): array => $get('type') === 'rules' ? [self::conditionsRule($get)] : [])
                            ->visible(fn (Get $get): bool => $get('type') === 'rules')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    /**
     * Field catalog for the rule builder: predefined facts scoped to the
     * chosen trigger, plus every saved metric fact — grouped so they render
     * as two optgroups in the island (design.md D2, spec "PIREP facts are
     * trigger-scoped").
     *
     * @return array<int, array<string, mixed>>
     */
    private static function catalogFor(mixed $triggerValue): array
    {
        $trigger = $triggerValue instanceof AwardTrigger
            ? $triggerValue
            : (AwardTrigger::tryFrom((string) $triggerValue) ?? AwardTrigger::Pirep);

        $predefined = collect(app(PredefinedFacts::class)->names($trigger))
            ->map(static fn (string $name): array => [
                'name'  => $name,
                'label' => Str::headline(str_replace('.', ' ', $name)),
                'group' => __('filament.award_predefined_facts'),
                ...(in_array($name, self::NUMERIC_PREDEFINED_FACTS, true) ? ['inputType' => 'number'] : []),
            ]);

        $metric = AwardFact::query()->get(['name', 'label'])
            ->map(static fn (AwardFact $fact): array => [
                'name'      => $fact->name,
                'label'     => $fact->label,
                'group'     => __('filament.award_metric_facts'),
                'inputType' => 'number',
            ]);

        return $predefined->concat($metric)->values()->all();
    }

    /**
     * Rejects a conditions tree referencing a fact name outside the
     * trigger's catalog, or an operator outside FactAllowlist::OPERATORS
     * (spec "Server-side validation of the tree").
     */
    private static function conditionsRule(Get $get): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $tree = is_array($value) ? $value : json_decode((string) $value, true);

            if (!is_array($tree)) {
                $fail(__('filament.award_conditions_invalid'));

                return;
            }

            $catalogNames = collect(self::catalogFor($get('trigger')))->pluck('name')->all();
            $evaluator = app(RuleEvaluator::class);

            foreach ($evaluator->referencedFacts($tree) as $field) {
                if (!in_array($field, $catalogNames, true)) {
                    $fail(__('filament.award_conditions_unknown_fact', ['field' => $field]));

                    return;
                }
            }

            if (($operator = self::firstUnsupportedOperator($tree)) !== null) {
                $fail(__('filament.award_conditions_unsupported_operator', ['field' => $operator['field'], 'operator' => $operator['operator']]));
            }
        };
    }

    /**
     * @return array{field: string, operator: string}|null
     */
    private static function firstUnsupportedOperator(array $node): ?array
    {
        if (!isset($node['rules']) || !is_array($node['rules'])) {
            return null;
        }

        foreach ($node['rules'] as $child) {
            if (!is_array($child)) {
                continue;
            }

            if (isset($child['rules'])) {
                if (($found = self::firstUnsupportedOperator($child)) !== null) {
                    return $found;
                }

                continue;
            }

            $operator = $child['operator'] ?? null;

            if (is_string($operator) && !FactAllowlist::isAllowedOperator($operator)) {
                return ['field' => (string) ($child['field'] ?? '?'), 'operator' => $operator];
            }
        }

        return null;
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
     * exclusive on one award").
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function mutateTypeFields(array $data): array
    {
        $type = $data['type'] ?? 'rules';
        unset($data['type']);

        if ($type === 'legacy') {
            $data['conditions'] = null;
            $data['trigger'] = null;
        } else {
            $data['ref_model_type'] = null;
            $data['ref_model_params'] = null;
        }

        return $data;
    }
}
