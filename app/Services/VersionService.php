<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Support\HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Version\Version;

class VersionService extends Service
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly KvpService $kvpRepo
    ) {}

    /**
     * Clean the version string (e.,g strip the v in front)
     *
     * @param string $version
     */
    private function cleanVersionString($version): string
    {
        if ($version[0] === 'v') {
            return substr($version, 1);
        }

        return $version;
    }

    /**
     * Set the latest release version/tag into the KVP repo and return the tag
     *
     *
     * @return string The version string
     */
    private function setLatestRelease($version_tag, string $download_url): string
    {
        $version_tag = $this->cleanVersionString($version_tag);

        $this->kvpRepo->save('latest_version_tag', $version_tag);
        $this->kvpRepo->save('latest_version_url', $download_url);

        return $version_tag;
    }

    /**
     * Find and return the Github asset line
     */
    private function getGithubAsset(array $release): string
    {
        foreach ($release['assets'] as $asset) {
            if ($asset['content_type'] === 'application/gzip') {
                return $asset['browser_download_url'];
            }
        }

        return '';
    }

    /**
     * Download the latest version from github and return the version number
     */
    private function getLatestVersionGithub(): ?string
    {
        $releases = [];

        try {
            /** @var array $releases */
            $releases = $this->httpClient->get(config('phpvms.version_file'), [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $guzzleException) {
            Log::error('Error retrieving new version: '.$guzzleException->getMessage());
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
     * Downloads the latest version and saves it into the KVP store
     */
    public function getLatestVersion(): ?string
    {
        return $this->getLatestVersionGithub();
    }

    /**
     * The application version, read from the root composer.json. The release
     * workflow stamps that field with the release tag before packaging.
     */
    public function getCurrentVersion(): string
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($composer) || !is_string($composer['version'] ?? null) || $composer['version'] === '') {
            throw new RuntimeException('composer.json has no "version" field');
        }

        return $this->cleanVersionString($composer['version']);
    }

    /**
     * See if a new version is available. Saves a flag into the KVP store if there is
     */
    public function isNewVersionAvailable(?string $current_version = null): bool
    {
        $current_version = $current_version ? $this->cleanVersionString($current_version) : $this->getCurrentVersion();

        $latest_version = $this->getLatestVersion();
        Log::info('Current version='.$current_version.'; latest detected='.$latest_version);

        // No new/released version found
        if (in_array($latest_version, [null, '', '0'], true)) {
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
     * @return bool If $version1 is greater than $version2
     */
    public function isGreaterThan(string $version1, string $version2): bool
    {
        $parsedVersion1 = Version::fromString($version1);
        $parsedVersion2 = Version::fromString($version2);

        return $parsedVersion1->isGreaterThan($parsedVersion2);
    }
}
