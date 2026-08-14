<?php

declare(strict_types=1);

namespace App\Services\Awards;

use App\Models\AwardSnippet;
use App\Services\Awards\Constraints\SnippetConstraint;
use Filament\QueryBuilder\Constraints\Constraint;

/**
 * One constraint per saved snippet, to hand to a `RuleBuilder` alongside
 * `UserConstraints::make()`.
 *
 * Built fresh on every call: `Constraint::model()` mutates rather than
 * clones, so a cached instance would leak the last model it was asked about.
 */
class SnippetConstraints
{
    /**
     * @param  int                    $depth how many snippet references deep this set sits; expansion stops past `SnippetConstraint::MAX_DEPTH`
     * @return array<int, Constraint>
     */
    public static function make(int $depth = 0): array
    {
        return AwardSnippet::query()
            ->orderBy('label')
            ->get()
            ->map(fn (AwardSnippet $snippet): Constraint => SnippetConstraint::forSnippet($snippet, $depth))
            ->all();
    }
}
