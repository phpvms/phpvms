<?php

declare(strict_types=1);

use App\Features\Dashboard\Livewire\LatestNews;

it('turns an html body into a short plain-text excerpt', function (): void {
    $excerpt = LatestNews::excerpt('<p>Hello   <strong>pilots</strong></p>'.str_repeat('<p>word </p>', 100));

    expect($excerpt)->toStartWith('Hello pilots word word')
        ->not->toContain('<')
        ->and(mb_strlen($excerpt))->toBeLessThanOrEqual(143);
});
