<?php

declare(strict_types=1);

use App\Features\Hooks\Fill;
use App\Models\User;
use Illuminate\Support\HtmlString;

it('builds a view fill with its data', function (): void {
    $fill = Fill::view('acme::badge', ['n' => 1]);

    expect($fill->kind)->toBe('view')
        ->and($fill->view)->toBe('acme::badge')
        ->and($fill->data)->toBe(['n' => 1]);
});

it('builds an html fill from a string or an Htmlable', function (): void {
    expect(Fill::html('<b>x</b>')->html)->toBe('<b>x</b>')
        ->and(Fill::html(new HtmlString('<i>y</i>'))->html)->toBe('<i>y</i>')
        ->and(Fill::html('z')->kind)->toBe('html');
});

it('builds a vue fill with ref strings and nested values in props', function (): void {
    $fill = Fill::vue('AcmeBadge', '/ext/acme/badge.js', ['flight' => '@flight', 'opts' => ['a' => [1, 2]]]);

    expect($fill->kind)->toBe('vue')
        ->and($fill->component)->toBe('AcmeBadge')
        ->and($fill->module)->toBe('/ext/acme/badge.js')
        ->and($fill->props)->toBe(['flight' => '@flight', 'opts' => ['a' => [1, 2]]]);
});

it('rejects a module without a component', function (): void {
    Fill::vue('', '/ext/acme/badge.js');
})->throws(InvalidArgumentException::class, 'requires a "component"');

it('rejects an Eloquent model in props at any depth', function (): void {
    Fill::vue('AcmeBadge', null, ['deep' => ['list' => [new User()]]]);
})->throws(InvalidArgumentException::class, 'Eloquent models');

it('emits the entry shape the SPA slot registry reads', function (): void {
    $entry = Fill::vue('AcmeBadge', '/ext/acme/badge.js', ['flight' => '@flight'])->toSpaEntry('flights.row.actions', 7);

    expect($entry)->toBe([
        'slot'      => 'flights.row.actions',
        'component' => 'AcmeBadge',
        'module'    => '/ext/acme/badge.js',
        'view'      => null,
        'props'     => ['flight' => '@flight'],
        'order'     => 7,
    ]);
});

it('refuses to send a non-vue fill to the SPA', function (): void {
    Fill::html('<p>x</p>')->toSpaEntry('profile.cards', 1);
})->throws(LogicException::class);
