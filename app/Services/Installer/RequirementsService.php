<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Contracts\Service;

class RequirementsService extends Service
{
    /**
     * Check the PHP version that it meets the minimum requirement
     */
    public function checkPHPVersion(): array
    {
        $passed = false;
        if (version_compare(PHP_VERSION, config('phpvms.installer.php_version')) >= 0) {
            $passed = true;
        }

        return ['version' => PHP_VERSION, 'passed' => $passed];
    }

    /**
     * Make sure the minimal extensions required are loaded
     */
    public function checkExtensions(): array
    {
        $extensions = [];
        foreach (config('phpvms.installer.extensions') as $ext) {
            $pass = true;
            if (!\extension_loaded($ext)) {
                $pass = false;
            }

            $extensions[] = [
                'ext'    => $ext,
                'passed' => $pass,
            ];
        }

        return $extensions;
    }

    /**
     * Check the permissions for the directories specified
     * Make sure they exist and are writable
     */
    public function checkPermissions(): array
    {
        clearstatcache();

        $directories = [];
        foreach (config('phpvms.installer.permissions') as $path) {
            $pass = true;

            if (!file_exists($path)) {
                $pass = false;
            }

            if (!is_writable($path)) {
                $pass = false;
            }

            $directories[] = [
                'dir'    => $path,
                'passed' => $pass,
            ];
        }

        return $directories;
    }

    /**
     * Check that PHP can create a symlink. The addon root links each bundled
     * addon from modules/, and `storage:link` serves the public disk, so
     * phpvms cannot run without them.
     *
     * @return array{passed: bool}
     */
    public function checkSymlinks(): array
    {
        $link = storage_path('app/.symlink-check-'.uniqid());
        $passed = @symlink('.', $link) && is_link($link);

        if (is_link($link)) {
            @unlink($link);
        }

        return ['passed' => $passed];
    }
}
