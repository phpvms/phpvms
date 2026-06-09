<?php

declare(strict_types=1);

namespace App\Addons\Support;

use App\Addons\Models\AddonManifest;
use App\Exceptions\AddonInstallException;

/**
 * Validates an extracted addon directory before placement: manifest parses and
 * the resolved PSR-4 autoload path exists on disk.
 */
class AddonValidator
{
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

        return $manifest;
    }
}
