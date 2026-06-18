<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Filesystem path helpers shared across the app.
 *
 * These are lexical (string-only) operations: they never touch the filesystem,
 * so they work on paths whose target does not yet exist. That property makes
 * them safe for boundary checks where realpath() would fail open on a missing
 * file (e.g. addon manifest path-traversal guards, zip-slip protection).
 */
class Filesystem
{
    /**
     * Lexically normalise a path, resolving "." and ".." segments without
     * touching the filesystem.
     *
     * Unlike realpath(), this works on paths whose target does not yet exist,
     * so a "../" traversal can never slip through a boundary check just because
     * the file it points at is currently missing. Both "/" and "\" separators
     * are accepted; the result always uses "/".
     */
    public static function normalizePath(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/') || str_starts_with($path, '\\');
        $segments = preg_split('#[\\\\/]+#', $path) ?: [];
        $out = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if ($segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($out);

                continue;
            }

            $out[] = $segment;
        }

        return ($isAbsolute ? '/' : '').implode('/', $out);
    }

    /**
     * Whether $candidate resolves to a location inside $base. Both paths are
     * normalised lexically first (see normalizePath), so the check is robust to
     * "../" traversal and "\" separators and does not require either path to
     * exist on disk.
     */
    public static function isWithin(string $base, string $candidate): bool
    {
        $base = self::normalizePath($base);
        $normalized = self::normalizePath($candidate);

        return $normalized === $base || str_starts_with($normalized.'/', $base.'/');
    }
}
