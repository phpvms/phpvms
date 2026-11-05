<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Dashboard\DashboardWidget;
use App\Features\Dashboard\DashboardWidgets;

it('builds widgets only when the registry is read', function (): void {
    $built = 0;
    app(DashboardWidgets::class)->register('alpha', function () use (&$built): DashboardWidget {
        $built++;

        return gridWidget('alpha');
    });
    $registry = app(DashboardWidgets::class);

    expect($registry->has('alpha'))->toBeTrue()
        ->and($built)->toBe(0);

    $registry->all();
    $registry->find('alpha');

    expect($built)->toBe(1);
});

it('lets the last registration override a duplicate id', function (): void {
    registerWidgets(gridWidget('alpha'), gridWidget('beta'));
    registerWidgets(DashboardWidget::view('alpha', 'x::override', 'Override', 'star'));

    $registry = app(DashboardWidgets::class);

    expect(array_keys($registry->all()))->toBe(['beta', 'alpha'])
        ->and($registry->find('alpha')?->view)->toBe('x::override')
        ->and($registry->find('missing'))->toBeNull();
});

it('removes a widget whether it is registered before or after the removal', function (): void {
    $registry = app(DashboardWidgets::class);
    registerWidgets(gridWidget('early'));
    $registry->all();

    $registry->remove('early');
    $registry->remove('late');
    registerWidgets(gridWidget('late'), gridWidget('kept'));

    expect(array_keys($registry->all()))->toBe(['kept'])
        ->and($registry->has('late'))->toBeFalse();
});

it('rejects a closure that builds a widget with another id', function (): void {
    app(DashboardWidgets::class)->register('alpha', fn (): DashboardWidget => gridWidget('beta'));

    expect(fn () => app(DashboardWidgets::class)->all())->toThrow(InvalidArgumentException::class);
});

it('accepts a numeric id', function (): void {
    registerWidgets(gridWidget('123'), gridWidget('alpha'));

    $registry = app(DashboardWidgets::class);

    expect($registry->find('123')?->id)->toBe('123')
        ->and($registry->find('alpha')?->id)->toBe('alpha');
});

it('builds widgets again for each locale', function (): void {
    app(DashboardWidgets::class)->register('alpha', fn (): DashboardWidget => DashboardWidget::livewire(
        'alpha', 'x::alpha', app()->getLocale() === 'fr' ? 'Bonjour' : 'Hello', 'star',
    ));
    $registry = app(DashboardWidgets::class);

    app()->setLocale('en');
    $english = $registry->find('alpha')?->title;
    app()->setLocale('fr');
    $french = $registry->find('alpha')?->title;

    expect([$english, $french])->toBe(['Hello', 'Bonjour']);
});

it('builds the default layout by zone, defaults only, sorted by order', function (): void {
    registerWidgets(
        gridWidget('late', order: 50),
        gridWidget('first', order: 10),
        gridWidget('tie-a', order: 20),
        gridWidget('optional', default: false),
        gridWidget('side', zone: 'sidebar'),
        gridWidget('tie-b', order: 20),
    );

    expect(app(DashboardWidgets::class)->defaultLayout())->toBe([
        'grid'    => ['first', 'tie-a', 'tie-b', 'late'],
        'sidebar' => ['side'],
    ]);
});

it('sanitizes a saved layout', function (): void {
    registerWidgets(
        gridWidget('alpha'),
        gridWidget('beta', zone: 'sidebar'),
        gridWidget('gamma', default: false),
    );
    $registry = app(DashboardWidgets::class);

    expect($registry->sanitize([
        'grid'    => ['gamma', 'unknown', 'gamma', 42, 'alpha'],
        'sidebar' => ['alpha', 'beta'],
    ]))->toBe(['grid' => ['gamma', 'alpha'], 'sidebar' => ['beta']]);

    // A widget stays in the zone it was saved in, even if declared for another.
    expect($registry->sanitize(['grid' => ['beta'], 'sidebar' => []]))
        ->toBe(['grid' => ['beta'], 'sidebar' => []]);

    foreach (['junk', null, 5, [], ['other' => ['alpha']]] as $garbage) {
        expect($registry->sanitize($garbage))->toBe($registry->defaultLayout());
    }
});

it('rejects invalid definitions', function (callable $make): void {
    expect($make)->toThrow(InvalidArgumentException::class);
})->with([
    'bad id'          => [fn (): DashboardWidget => DashboardWidget::livewire('Bad_Id', 'x::y', 'T', 'star')],
    'bad zone'        => [fn (): DashboardWidget => DashboardWidget::livewire('ok', 'x::y', 'T', 'star', zone: 'footer')],
    'bad span'        => [fn (): DashboardWidget => DashboardWidget::livewire('ok', 'x::y', 'T', 'star', span: 3)],
    'empty view'      => [fn (): DashboardWidget => DashboardWidget::view('ok', '', 'T', 'star')],
    'empty component' => [fn (): DashboardWidget => DashboardWidget::livewire('ok', ' ', 'T', 'star')],
]);
