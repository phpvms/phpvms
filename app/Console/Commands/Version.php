<?php

namespace App\Console\Commands;

use App\Services\VersionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Yaml\Yaml;
use Version\Extension\Build;
use Version\Extension\PreRelease;
use Version\Version as SemanticVersion;

#[AsCommand(name: 'phpvms:version', description: 'Get or update the current application version')]
class Version extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'phpvms:version 
                            {version? : The semantic version string to apply}
                            {--write : Write the updated version/build number to the version.yml file} 
                            {--base-only : Only output the base version without build metadata} 
                            {--write-full-version : Update the major, minor, and patch values in the file}';

    /**
     * Execute the console command.
     */
    public function handle(VersionService $versionSvc): int
    {
        if ($this->option('write') && $this->argument('version')) {
            $this->updateVersionConfig($versionSvc);
        }

        $includeBuild = !$this->option('base-only');
        $version = $versionSvc->getCurrentVersion($includeBuild);

        // We use line() instead of components->info() so this command safely pipes
        // raw string data to bash scripts during CI/CD workflows.
        $this->line($version);

        return self::SUCCESS;
    }

    /**
     * Parse and update the version.yml file.
     */
    protected function updateVersionConfig(VersionService $versionSvc): void
    {
        $versionFile = config_path('version.yml');

        if (!File::exists($versionFile)) {
            $this->components->error(sprintf('Version config file not found at [%s]', $versionFile));

            return;
        }

        $versionString = $this->argument('version');

        $this->components->task('Updating version.yml to '.$versionString, function () use ($versionString, $versionSvc, $versionFile): void {
            $cfg = Yaml::parse(File::get($versionFile));
            $version = SemanticVersion::fromString($versionString);

            // Update Base Version if requested
            if ($this->option('write-full-version')) {
                $cfg['current']['major'] = $version->getMajor();
                $cfg['current']['minor'] = $version->getMinor();
                $cfg['current']['patch'] = $version->getPatch();
            }

            // Update Pre-release
            $prerelease = $version->getPreRelease();
            $cfg['current']['prerelease'] = $prerelease instanceof PreRelease
                ? $prerelease->toString()
                : false;

            // Update Build Metadata
            $buildMeta = $version->getBuild();
            $cfg['current']['buildmetadata'] = $buildMeta instanceof Build
                ? $buildMeta->toString()
                : $versionSvc->generateBuildId($cfg);

            // Save back to disk
            File::put($versionFile, Yaml::dump($cfg, 4, 2));
        });
    }
}
