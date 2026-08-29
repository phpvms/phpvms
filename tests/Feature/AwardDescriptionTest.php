<?php

declare(strict_types=1);

use App\Http\Data\AwardData;
use App\Models\Award;

it('flattens markup to plain text on write', function (string $stored, ?string $expected): void {
    $award = Award::factory()->create(['description' => $stored]);

    expect($award->refresh()->description)->toBe($expected);
})->with([
    'emptied editor'      => ['<p></p>', ''],
    'nbsp only'           => ['<p>&nbsp;</p>', ''],
    'single paragraph'    => ['<p>Ten flights into KJFK.</p>', 'Ten flights into KJFK.'],
    'two paragraphs'      => ['<p>One</p><p>Two</p>', "One\nTwo"],
    'line break'          => ['First<br>Second', "First\nSecond"],
    'inline markup'       => ['<p>Fly <strong>ten</strong> legs.</p>', 'Fly ten legs.'],
    'entities decoded'    => ['<p>Tea &amp; biscuits</p>', 'Tea & biscuits'],
    'already plain'       => ['Just text.', 'Just text.'],
    'plain with newlines' => ["One\nTwo", "One\nTwo"],
]);

it('leaves a null description null', function (): void {
    expect(Award::factory()->create(['description' => null])->refresh()->description)->toBeNull();
});

it('does not run two sentences together across block boundaries', function (): void {
    // A bare strip_tags() would yield "OneTwo" here — the case the newline
    // substitution in Award::toPlainText() exists for.
    expect(Award::toPlainText('<p>One</p><p>Two</p>'))->not->toContain('OneTwo');
});

it('reports an award with no real description as null to the profile page', function (): void {
    $award = Award::factory()->create(['description' => '<p></p>']);

    expect(AwardData::fromModel($award->refresh())->description)->toBeNull();
});

it('passes a real description through to the profile page intact', function (): void {
    $award = Award::factory()->create(['description' => '<p>Ten flights into KJFK.</p>']);

    expect(AwardData::fromModel($award->refresh())->description)->toBe('Ten flights into KJFK.');
});
