<?php

namespace App\Services;

use App\Contracts\Service;
use App\Repositories\KvpRepository;
use App\Support\HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;
use Version\Version;

class VersionService extends Service
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly KvpRepository $kvpRepo
    ) {
    }

    /**
     * Clean the version string (e.,g strip the v in front).
     *
     * @param string $version
     */
    private function cleanVersionString($version): string
    {
        if ($version[0] === 'v') {
            $version = substr($version, 1);
        }

        return $version;
    }

    /**
     * Set the latest release version/tag into the KVP repo and return the tag.
     *
     *
     * @return string The version string
     */
    private function setLatestRelease($version_tag, $download_url): string
    {
        $version_tag = $this->cleanVersionString($version_tag);

        $this->kvpRepo->save('latest_version_tag', $version_tag);
        $this->kvpRepo->save('latest_version_url', $download_url);

        return $version_tag;
    }

    /**
     * Find and return the Github asset line.
     */
    private function getGithubAsset($release): string
    {
        foreach ($release['assets'] as $asset) {
            if ($asset['content_type'] === 'application/gzip') {
                return $asset['browser_download_url'];
            }
        }

        return '';
    }

    /**
     * Download the latest version from github and return the version number.
     */
    private function getLatestVersionGithub()
    {
        $releases = [];

        try {
            $releases = $this->httpClient->get(config('phpvms.version_file'), [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $e) {
            Log::error('Error retrieving new version: '.$e->getMessage());
        }

        $include_prerelease = setting('general.check_prerelease_version', false);
        Log::info('Include prerelease='.$include_prerelease);
        foreach ($releases as $release) {
            if ($release['prerelease'] === true) {
                if ($include_prerelease) {
                    Log::info('Found latest pre-release of '.$release['tag_name']);

                    return $this->setLatestRelease(
                        $release['tag_name'],
                        $this->getGithubAsset($release)
                    );
                }

                continue;
            }

            Log::info('Found latest release of '.$release['tag_name']);

            return $this->setLatestRelease(
                $release['tag_name'],
                $this->getGithubAsset($release)
            );
        }

        return null;
    }

    /**
     * Downloads the latest version and saves it into the KVP store.
     */
    public function getLatestVersion()
    {
        $latest_version = $this->getLatestVersionGithub();

        return $latest_version;
    }

    /**
     * Get the build ID, which is the date and the git log version.
     *
     * @param array $cfg
     *
     * @return string
     */
    public function getBuildId($cfg)
    {
        return $cfg['build']['number'];
    }

    /**
     * Generate a build ID.
     *
     * @param array $cfg The version config
     *
     * @return false|string
     */
    public function generateBuildId($cfg)
    {
        $date = date('ymd');
        exec($cfg['git']['git-local'], $version);
        if (empty($version)) {
            return $date;
        }

        $version = substr($version[0], 0, $cfg['build']['length']);

        // prefix with the date in YYMMDD format
        $version = $date.'.'.$version;

        return $version;
    }

    /**
     * Get the current version.
     *
     * @param bool $include_build True will include the build ID
     *
     * @return string
     */
    public function getCurrentVersion($include_build = true)
    {
        $version_file = config_path('version.yml');
        $cfg = Yaml::parse(file_get_contents($version_file));

        $c = $cfg['current'];
        $version = "{$c['major']}.{$c['minor']}.{$c['patch']}";
        if (!empty($c['prerelease'])) {
            $version .= "-{$c['prerelease']}";
        }

        if ($include_build) {
            // Get the current build id
            $build_number = $c['buildmetadata'];
            if (!empty($build_number)) {
                $version = $version.'+'.$build_number;
            }
        }

        return $version;
    }

    /**
     * See if a new version is available. Saves a flag into the KVP store if there is.
     *
     * @param null [$current_version]
     *
     * @return bool
     */
    public function isNewVersionAvailable($current_version = null)
    {
        if (!$current_version) {
            $current_version = $this->getCurrentVersion(false);
        } else {
            $current_version = $this->cleanVersionString($current_version);
        }

        $latest_version = $this->getLatestVersion();
        Log::info('Current version='.$current_version.'; latest detected='.$latest_version);

        // No new/released version found
        if (empty($latest_version)) {
            $this->kvpRepo->save('new_version_available', false);

            return false;
        }

        // Convert to semver
        if ($this->isGreaterThan($latest_version, $current_version)) {
            Log::info('Latest version "'.$latest_version.'" is greater than "'.$current_version.'"');
            $this->kvpRepo->save('new_version_available', true);

            return true;
        }

        $this->kvpRepo->save('new_version_available', false);

        return false;
    }

    /**
     * @param string $version1
     * @param string $version2
     *
     * @return bool If $version1 is greater than $version2
     */
    public function isGreaterThan($version1, $version2): bool
    {
        $parsedVersion1 = Version::fromString($version1);
        $parsedVersion2 = Version::fromString($version2);

        return $parsedVersion1->isGreaterThan($parsedVersion2);
    }
}
