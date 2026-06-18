<?php

declare(strict_types=1);

use App\Support\Filesystem;

test('normalizePath resolves "." and ".." segments lexically', function (): void {
    expect(Filesystem::normalizePath('/a/b/../c/./d'))->toBe('/a/c/d');
});

test('normalizePath collapses redundant and mixed separators to "/"', function (): void {
    expect(Filesystem::normalizePath('a\\b//c'))->toBe('a/b/c');
});

test('normalizePath preserves the leading slash on absolute paths', function (): void {
    expect(Filesystem::normalizePath('/a/b'))->toBe('/a/b')
        ->and(Filesystem::normalizePath('a/b'))->toBe('a/b');
});

test('isWithin accepts the base itself and nested paths', function (): void {
    expect(Filesystem::isWithin('/srv/addon', '/srv/addon'))->toBeTrue()
        ->and(Filesystem::isWithin('/srv/addon', '/srv/addon/helpers.php'))->toBeTrue();
});

test('isWithin rejects traversal that escapes the base, even for a missing target', function (): void {
    expect(Filesystem::isWithin('/srv/addon', '/srv/addon/../../etc/passwd'))->toBeFalse()
        ->and(Filesystem::isWithin('/srv/addon', '/srv/addon-other/file.php'))->toBeFalse();
});

test('isWithin normalises both arguments before comparing', function (): void {
    expect(Filesystem::isWithin('/srv/addon/', '/srv/addon/sub/../helpers.php'))->toBeTrue();
});
