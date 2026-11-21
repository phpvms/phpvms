<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

final class Requirements
{
    /**
     * @return array{version: string, passed: bool}
     */
    public function checkPHPVersion(): array
    {
        return [
            'version' => PHP_VERSION,
            'passed'  => version_compare(PHP_VERSION, (string) config('phpvms.installer.php_version')) >= 0,
        ];
    }

    /**
     * @return list<array{ext: string, passed: bool}>
     */
    public function checkExtensions(): array
    {
        $extensions = [];
        foreach (config('phpvms.installer.extensions') as $ext) {
            $extensions[] = ['ext' => $ext, 'passed' => \extension_loaded($ext)];
        }

        return $extensions;
    }

    /**
     * @return list<array{dir: string, passed: bool}>
     */
    public function checkPermissions(): array
    {
        clearstatcache();

        $directories = [];
        foreach (config('phpvms.installer.permissions') as $path) {
            $directories[] = ['dir' => $path, 'passed' => file_exists($path) && is_writable($path)];
        }

        return $directories;
    }

    /**
     * The addon root links bundled addons and `storage:link` serves the public
     * disk, so phpvms cannot run without symlinks.
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

    /**
     * @return list<string> one line per failed check
     */
    public function failures(): array
    {
        $failures = [];

        $php = $this->checkPHPVersion();
        if (!$php['passed']) {
            $failures[] = 'PHP '.config('phpvms.installer.php_version').' or newer is required, found '.$php['version'];
        }

        foreach ($this->checkExtensions() as $ext) {
            if (!$ext['passed']) {
                $failures[] = 'PHP extension missing: '.$ext['ext'];
            }
        }

        foreach ($this->checkPermissions() as $dir) {
            if (!$dir['passed']) {
                $failures[] = 'Directory missing or not writable: '.$dir['dir'];
            }
        }

        if (!$this->checkSymlinks()['passed']) {
            $failures[] = 'PHP cannot create symlinks';
        }

        return $failures;
    }
}
