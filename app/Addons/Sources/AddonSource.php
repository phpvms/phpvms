<?php

declare(strict_types=1);

namespace App\Addons\Sources;

use App\Exceptions\AddonInstallException;

/**
 * Resolves an addon payload into an extracted directory ready for validation.
 */
interface AddonSource
{
    /**
     * Extract/copy the addon into a fresh subdir of $stagingDir.
     *
     * @return string Absolute path to the extracted addon root (the dir
     *                containing module.json).
     *
     * @throws AddonInstallException
     */
    public function fetch(string $stagingDir): string;
}
