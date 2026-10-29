<?php

declare(strict_types=1);

namespace App\Addons\Support;

use App\Addons\Models\AddonManifest;
use App\Exceptions\AddonInstallException;
use Symfony\Component\Finder\Finder;

/**
 * Validates an extracted addon directory before placement: manifest parses,
 * the resolved PSR-4 autoload path exists on disk, and public/ holds no PHP.
 */
class AddonValidator
{
    /**
     * File names a PHP-enabled web server may execute. Matched anywhere in the
     * name, so "shell.php.png" is caught too: some Apache setups run any file
     * with ".php" in it.
     */
    private const string EXECUTABLE_EXTENSIONS = '/\.(php\d*|phtml|phar|pht|phps)(\.|$)/i';

    /**
     * Per-directory server config files. On Apache and PHP-CGI hosts these
     * can turn any file into a PHP script (AddHandler, auto_prepend_file).
     */
    private const array SERVER_CONFIG_FILES = ['.htaccess', '.user.ini'];

    public function __construct(
        private readonly ManifestParser $parser,
    ) {}

    /**
     * @throws AddonInstallException
     */
    public function validate(string $dir): AddonManifest
    {
        $manifest = $this->parser->parse($dir);

        if (!$manifest instanceof AddonManifest) {
            throw new AddonInstallException('Invalid or missing module.json.');
        }

        if (!is_dir($manifest->autoloadPath)) {
            throw new AddonInstallException(sprintf('PSR-4 path does not exist: %s', $manifest->autoloadPath));
        }

        $this->assertNoExecutablePublicFiles($dir);

        return $manifest;
    }

    /**
     * Refuse an addon whose public/ holds a PHP script or a server config file.
     *
     * public/ is linked into the web root (public/ext, and public/storage for
     * themes), and the web server runs any PHP file it finds there. An addon's
     * PHP code belongs outside public/, where only the framework loads it.
     *
     * @throws AddonInstallException
     */
    private function assertNoExecutablePublicFiles(string $dir): void
    {
        $public = $dir.'/public';

        if (!is_dir($public)) {
            return;
        }

        // Finder, not File::allFiles(): that skips VCS directories (.git, CVS,
        // .svn, ...) by default, and a PHP file inside one is still served.
        $files = Finder::create()->files()->in($public)->ignoreDotFiles(false)->ignoreVCS(false);

        foreach ($files as $file) {
            $name = $file->getFilename();

            if (preg_match(self::EXECUTABLE_EXTENSIONS, $name) === 1 || in_array(strtolower($name), self::SERVER_CONFIG_FILES, true)) {
                throw new AddonInstallException(sprintf(
                    'Addon public/ must not contain PHP or server config files: %s',
                    $file->getRelativePathname(),
                ));
            }
        }
    }
}
