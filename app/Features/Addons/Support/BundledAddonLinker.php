<?php

declare(strict_types=1);

namespace App\Features\Addons\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Links each bundled addon into the addon root, as a relative symlink
 * `<root>/<name> -> ../../../modules/<name>`.
 *
 * The links are committed, so a git checkout or a release archive already has
 * them. This puts back the ones that are missing: a Docker volume over storage/
 * is filled from the image only once, so an addon bundled by a later release
 * has no link in it until this runs.
 *
 * An entry that is a real directory is a registry update of a bundled addon.
 * It stays while it is strictly newer than the bundled copy, and is replaced
 * by the link once a release ships that version or a later one
 * ({@see AddonVersions::installedSupersedesImage()}).
 *
 * Symlinks are required, as they are for `storage:link`: a failed symlink()
 * throws.
 */
final readonly class BundledAddonLinker
{
    public const string LINKED = 'linked';

    public const string PRESENT = 'present';

    public const string RESTORED = 'restored';

    public const string KEPT = 'kept';

    public function __construct(private ManifestParser $parser) {}

    /**
     * What was done for each bundled addon whose files are present.
     *
     * @return array<string, self::LINKED|self::PRESENT|self::RESTORED|self::KEPT>
     *
     * @throws RuntimeException when a link cannot be created
     */
    public function link(): array
    {
        $root = AddonRoots::root();
        $results = [];

        foreach (AddonRoots::bundled() as $name) {
            $source = AddonRoots::bundledSource().'/'.$name;

            if (!is_dir($source)) {
                continue;
            }

            $results[$name] = $this->linkOne($root, $name, $source);
        }

        return $results;
    }

    /**
     * @return self::LINKED|self::PRESENT|self::RESTORED|self::KEPT
     */
    private function linkOne(string $root, string $name, string $source): string
    {
        $entry = $root.'/'.$name;

        if (is_link($entry)) {
            return self::PRESENT;
        }

        if (is_dir($entry)) {
            $installed = $this->parser->parse($entry)?->version;
            $bundled = $this->parser->parse($source)?->version;

            if (AddonVersions::installedSupersedesImage($installed, $bundled)) {
                return self::KEPT;
            }

            // Moved aside under a dot name, which discovery skips, so a failed
            // link can be undone and the old copy is never scanned twice.
            $aside = $root.'/.'.$name.'.superseded-'.uniqid('', true);

            if (!rename($entry, $aside)) {
                throw new RuntimeException(sprintf('Could not move %s aside to link the bundled version.', $entry));
            }

            try {
                $this->symlink($source, $entry);
            } catch (RuntimeException $runtimeException) {
                rename($aside, $entry);

                throw $runtimeException;
            }

            File::deleteDirectory($aside);
            Log::info(sprintf('BundledAddonLinker: "%s" %s replaced by the bundled %s', $name, $installed ?? 'unversioned', $bundled ?? 'unversioned'));

            return self::RESTORED;
        }

        if (file_exists($entry)) {
            throw new RuntimeException(sprintf('%s is in the way of the bundled "%s" addon.', $entry, $name));
        }

        File::ensureDirectoryExists($root);
        $this->symlink($source, $entry);

        return self::LINKED;
    }

    private function symlink(string $source, string $entry): void
    {
        $target = self::relativePath(dirname($entry), $source);

        if (!@symlink($target, $entry)) {
            throw new RuntimeException(sprintf('Could not create the symlink %s -> %s. phpvms requires symlink support.', $entry, $target));
        }
    }

    /**
     * $to as a path relative to the directory $from, so the link survives the
     * install being moved.
     */
    public static function relativePath(string $from, string $to): string
    {
        $fromParts = array_values(array_filter(explode('/', realpath($from) ?: $from), static fn (string $part): bool => $part !== ''));
        $toParts = array_values(array_filter(explode('/', realpath($to) ?: $to), static fn (string $part): bool => $part !== ''));

        $common = 0;

        while (isset($fromParts[$common], $toParts[$common]) && $fromParts[$common] === $toParts[$common]) {
            $common++;
        }

        return implode('/', [
            ...array_fill(0, count($fromParts) - $common, '..'),
            ...array_slice($toParts, $common),
        ]);
    }
}
