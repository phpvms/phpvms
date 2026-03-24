<?php

use App\Repositories\KvpRepository;
use App\Services\VersionService;

test('greater than version strings', function () {
    $test = [
        ['7.0.0' => '6.0.0'],
        ['7.0.0+1231s' => '6.0.0'],
        // ['7.0.0-beta' => '7.0.0-dev'],
        ['7.0.0-beta' => '7.0.0-alpha'],
        ['7.0.0-beta.1'        => '7.0.0-beta'],
        ['7.0.0-beta.2'        => '7.0.0-beta.1'],
        ['7.0.0-beta.2+a34sdf' => '7.0.0-beta.1'],
    ];

    $versionSvc = app(VersionService::class);
    foreach ($test as $set) {
        $newVersion = array_key_first($set);
        $currentVersion = $set[$newVersion];

        expect($versionSvc->isGreaterThan($newVersion, $currentVersion))->toBeTrue("$newVersion not greater than $currentVersion");
    }
});

test('get latest version', function () {
    setting('general.check_prerelease_version', false);

    mockGuzzleResponse('releases.json', 'application/json; charset=utf-8');
    $versionSvc = app(VersionService::class);

    $str = $versionSvc->getLatestVersion();

    expect($str)->toEqual('7.0.0-alpha2')
        ->and(app(KvpRepository::class)->get('latest_version_tag'))->toEqual('7.0.0-alpha2');
});

test('get latest prerelease version', function () {
    updateSetting('general.check_prerelease_version', true);

    mockGuzzleResponse('releases.json', 'application/json; charset=utf-8');
    $versionSvc = app(VersionService::class);

    $str = $versionSvc->getLatestVersion();

    expect($str)->toEqual('7.0.0-beta')
        ->and(app(KvpRepository::class)->get('latest_version_tag'))->toEqual('7.0.0-beta');
});

test('new version not available', function () {
    updateSetting('general.check_prerelease_version', false);

    $versions = [
        'v7.0.0',
        '7.0.0',
        '8.0.0',
        '7.0.0-beta',
        '7.0.0+buildid',
    ];

    foreach ($versions as $v) {
        mockGuzzleResponse('releases.json', 'application/json; charset=utf-8');
        $versionSvc = app(VersionService::class);
        expect($versionSvc->isNewVersionAvailable($v))->toBeFalse();
    }
});

test('new version is available', function () {
    updateSetting('general.check_prerelease_version', true);

    $versions = [
        'v6.0.1',
        '6.0.0',
        '7.0.0-alpha',
    ];

    foreach ($versions as $v) {
        mockGuzzleResponse('releases.json', 'application/json; charset=utf-8');
        $versionSvc = app(VersionService::class);
        expect($versionSvc->isNewVersionAvailable($v))->toBeTrue();
    }
});
