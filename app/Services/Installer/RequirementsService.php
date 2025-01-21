<?php

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
        if (version_compare(PHP_VERSION, config('installer.php.version')) >= 0) {
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
        foreach (config('installer.extensions') as $ext) {
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
        foreach (config('installer.permissions') as $path) {
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
}
