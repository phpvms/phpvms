<?php

declare(strict_types=1);

namespace App\Support;

use Igaster\LaravelTheme\Themes as BaseThemes;
use Override;
use RuntimeException;

/**
 * igaster's theme manager with an atomic cache rebuild.
 *
 * The vendor writes bootstrap/cache/themes.php with a plain file_put_contents,
 * which truncates the file before the new content lands. Every other process
 * `include`s that same file while booting, and an include of an empty file
 * returns int 1, so a concurrent boot (an Octane worker, a parallel test
 * worker) dies in scanThemes() with "foreach() argument must be of type
 * array|object". Writing to a sibling temp file and renaming it over the cache
 * gives readers either the old file or the new one, never a partial.
 */
class Themes extends BaseThemes
{
    #[Override]
    public function rebuildCache(): void
    {
        $content = '<?php'.PHP_EOL.PHP_EOL.'return '.var_export($this->scanJsonFiles(), true).';'.PHP_EOL;
        $tmp = $this->cachePath.'.tmp.'.getmypid().'.'.uniqid();

        file_put_contents($tmp, $content, LOCK_EX);

        if (!rename($tmp, $this->cachePath)) {
            @unlink($tmp);

            throw new RuntimeException('Themes: failed to atomically rename the theme cache file.');
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cachePath, true);
        }
    }
}
