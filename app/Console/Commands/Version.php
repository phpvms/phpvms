<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Services\VersionService;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Version\Extension\Build;
use Version\Extension\PreRelease;

class Version extends Command
{
    protected $signature = 'phpvms:version {--write} {--base-only} {--write-full-version} {version?}';

    private VersionService $versionSvc;

    public function __construct(VersionService $versionSvc)
    {
        parent::__construct();

        $this->versionSvc = $versionSvc;
    }

    /**
     * Run dev related commands
     *
     * @throws ParseException
     */
    public function handle()
    {
        if ($this->option('write')) {
            // Write the updated build number out to the file
            $version_file = config_path('version.yml');
            $cfg = Yaml::parse(file_get_contents($version_file));

            // If a version is being passed in, the update the build, etc data against this
            if ($this->argument('version')) {
                $version = \Version\Version::fromString($this->argument('version'));
                if ($this->option('write-full-version')) {
                    $cfg['current']['major'] = $version->getMajor();
                    $cfg['current']['minor'] = $version->getMinor();
                    $cfg['current']['patch'] = $version->getPatch();
                }

                $prerelease = $version->getPreRelease();
                $cfg['current']['prerelease'] = $prerelease instanceof PreRelease ? $prerelease->toString() : false;

                $build_meta = $version->getBuild();
                if ($build_meta instanceof Build) {
                    $cfg['current']['buildmetadata'] = $build_meta->toString();
                } else {
                    $build_number = $this->versionSvc->generateBuildId($cfg);
                    $cfg['current']['buildmetadata'] = $build_number;
                }

                file_put_contents($version_file, Yaml::dump($cfg, 4, 2));
            }
        }

        $incl_build = empty($this->option('base-only'));
        $version = $this->versionSvc->getCurrentVersion($incl_build);
        echo $version."\n";
    }
}
