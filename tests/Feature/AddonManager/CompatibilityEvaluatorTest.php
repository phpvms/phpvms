<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\Support\BootCache;
use Modules\AddonManager\Services\CompatibilityEvaluator;

beforeEach(function (): void {
    app(BootCache::class)->delete();
    $this->artisan('phpvms:addons-prime')->assertSuccessful();
    app(AddonAutoLoader::class)->register(app());
});

it('marks an entry compatible when constraints are met', function (): void {
    $result = app(CompatibilityEvaluator::class)->evaluate([
        'min_php'    => '5.0',
        'min_phpvms' => '1.0',
    ]);

    expect($result['compatible'])->toBeTrue();
    expect($result['reason'])->toBeNull();
});

it('marks an entry incompatible and explains why when phpvms is too old', function (): void {
    $result = app(CompatibilityEvaluator::class)->evaluate([
        'min_php'    => '5.0',
        'min_phpvms' => '999.0',
    ]);

    expect($result['compatible'])->toBeFalse();
    expect($result['reason'])->toContain('phpvms ≥ 999.0');
});

it('compares semver-aware so 2.10 outranks 2.9', function (): void {
    // Running PHP is 8.x, so a min of 2.10.0 must read as satisfied (8 > 2.10),
    // and a string compare of "8.4" vs "2.10" would also pass — the real ordering
    // check: 2.10.0 must be treated as newer than 2.9.0.
    $newer = app(CompatibilityEvaluator::class)->evaluate(['min_php' => '2.10.0']);
    $older = app(CompatibilityEvaluator::class)->evaluate(['min_php' => '2.9.0']);

    expect($newer['compatible'])->toBeTrue();
    expect($older['compatible'])->toBeTrue();
});

it('detects a newer version semver-aware (string compare would miss 2.10 > 2.9)', function (): void {
    $evaluator = app(CompatibilityEvaluator::class);

    // "2.10.0" < "2.9.0" lexically — this must NOT regress to string comparison.
    expect($evaluator->isNewer('2.10.0', '2.9.0'))->toBeTrue();
    expect($evaluator->isNewer('2.2.0', '2.1.0'))->toBeTrue();
    expect($evaluator->isNewer('2.2.0', '2.2.0'))->toBeFalse();
    expect($evaluator->isNewer('2.1.0', '2.2.0'))->toBeFalse();
});

it('never reports a newer version when the installed version is unparseable', function (): void {
    $evaluator = app(CompatibilityEvaluator::class);

    // A non-semver installed version (dev branch / empty) must fail closed, or
    // pad() would coerce it to 0.0.0 and flag every catalog release as an update.
    expect($evaluator->isNewer('2.2.0', 'dev-main'))->toBeFalse();
    expect($evaluator->isNewer('2.2.0', ''))->toBeFalse();
});

it('ignores empty constraints', function (): void {
    $result = app(CompatibilityEvaluator::class)->evaluate([
        'min_php'    => '',
        'min_phpvms' => '',
    ]);

    expect($result['compatible'])->toBeTrue();
});
