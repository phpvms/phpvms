<?php

declare(strict_types=1);

namespace App\Filament\Resources\AwardSnippets\Schemas;

use App\Services\Awards\Constraints\PirepConstraint;
use App\Services\Awards\Constraints\TourConstraint;
use App\Services\Awards\UserConstraints;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AwardSnippetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('label')
                    ->label(__('common.name'))
                    ->required()
                    ->string()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                        if ($operation === 'create') {
                            $set('name', Str::slug((string) $state));
                        }
                    }),

                // Referencing trees name the snippet by its slug, so renaming
                // one would orphan every award using it. A disabled field is
                // not dehydrated, which is exactly the immutability we want.
                TextInput::make('name')
                    ->label(__('filament.award_snippet_name'))
                    ->helperText(__('filament.award_snippet_name_help'))
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),

                Textarea::make('description')
                    ->label(__('common.description'))
                    ->rows(2)
                    ->columnSpanFull(),

                // A snippet is a fragment of a `users` tree, so it gets that
                // vocabulary without the snippet constraints themselves — no
                // nesting snippets inside snippets from here.
                RuleBuilder::make('conditions')
                    ->label(__('filament.award_conditions'))
                    ->constraints([
                        ...UserConstraints::make(),
                        PirepConstraint::make(),
                        TourConstraint::make(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
