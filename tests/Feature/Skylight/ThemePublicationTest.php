<?php

declare(strict_types=1);

use App\Models\ActiveThemePublication;
use App\Models\PublishedThemeRevision;
use App\Models\User;
use App\Services\Theme\ThemeAssetService;
use App\Services\Theme\ThemeCssRenderer;
use App\Services\Theme\ThemeDocumentNormalizer;
use App\Services\Theme\ThemePublicationService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config([
        'filesystems.theme_assets' => 'theme-assets',
        'phpvms.installed'         => true,
        'themes.publish_lock.wait' => 0,
    ]);
    Storage::fake('theme-assets');
    User::factory()->create();
});

function publishedThemeDocument(): array
{
    return app(ThemeDocumentNormalizer::class)->defaults();
}

it('publishes immutable assets before activating a durable revision', function (): void {
    $service = app(ThemePublicationService::class);
    $document = publishedThemeDocument();

    $published = $service->publish('skylight', $document, '.pv-dashboard-toolbar { color: red; }');
    $themeCss = app(ThemeCssRenderer::class)->render($document);

    expect($published->revision)->toBe($service->contentRevision($document, $published->custom_css, $themeCss))
        ->and(ActiveThemePublication::query()->findOrFail('skylight')->published_theme_revision_id)->toBe($published->id);
    $this->assertDatabaseHas('published_theme_revisions', [
        'id'             => $published->id,
        'theme_name'     => 'skylight',
        'schema_version' => 1,
    ]);
    Storage::disk('theme-assets')->assertExists("skylight/{$published->revision}/theme.css");
    Storage::disk('theme-assets')->assertExists("skylight/{$published->revision}/custom.css");
});

it('distinguishes an absent custom stylesheet from an empty one', function (): void {
    $service = app(ThemePublicationService::class);
    $document = publishedThemeDocument();

    $withoutCustomCss = $service->publish('skylight', $document);
    $withEmptyCustomCss = $service->publish('skylight', $document, '');

    expect($withoutCustomCss->revision)->not->toBe($withEmptyCustomCss->revision)
        ->and($withoutCustomCss->custom_css)->toBeNull()
        ->and($withEmptyCustomCss->custom_css)->toBe('');
    Storage::disk('theme-assets')->assertMissing("skylight/{$withoutCustomCss->revision}/custom.css");
    Storage::disk('theme-assets')->assertExists("skylight/{$withEmptyCustomCss->revision}/custom.css");
});

it('changes the revision when generated theme CSS bytes change', function (): void {
    $service = app(ThemePublicationService::class);
    $document = publishedThemeDocument();

    $first = $service->contentRevision($document, null, ':root { --pv-accent: #067ec1; }');
    $second = $service->contentRevision($document, null, ':root { --pv-accent: #067ec2; }');

    expect($first)->not->toBe($second);
});

it('serves the default asset URL anonymously with immutable cache headers', function (): void {
    $published = app(ThemePublicationService::class)->publish('skylight', publishedThemeDocument(), 'body { color: red; }');
    $assets = app(ThemeAssetService::class);

    expect(config('themes.asset_delivery'))->toBe('route');
    $themeResponse = $this->get($assets->url('skylight', $published->revision, 'theme.css'));
    $customResponse = $this->get($assets->url('skylight', $published->revision, 'custom.css'));

    $themeResponse->assertOk();
    $customResponse->assertOk();
    expect($themeResponse->streamedContent())->toBe(Storage::disk('theme-assets')->get("skylight/{$published->revision}/theme.css"))
        ->and($customResponse->streamedContent())->toBe('body { color: red; }')
        ->and($themeResponse->headers->get('Cache-Control'))->toContain('public')
        ->toContain('max-age=31536000')
        ->toContain('immutable');
});

it('leaves the active revision unchanged when an immutable asset write conflicts', function (): void {
    $service = app(ThemePublicationService::class);
    $first = $service->publish('skylight', publishedThemeDocument());
    $changed = publishedThemeDocument();
    $changed['nuxtUi']['theme']['radius'] = 0.5;
    $failedRevision = $service->contentRevision($changed, null, app(ThemeCssRenderer::class)->render($changed));
    Storage::disk('theme-assets')->put("skylight/{$failedRevision}/theme.css", 'conflict');

    expect(fn () => $service->publish('skylight', $changed))->toThrow(LogicException::class)
        ->and(ActiveThemePublication::query()->findOrFail('skylight')->published_theme_revision_id)->toBe($first->id)
        ->and(PublishedThemeRevision::query()->count())->toBe(1);
    $this->get(route('theme-assets.show', [
        'themeName' => 'skylight',
        'revision'  => $failedRevision,
        'asset'     => 'theme.css',
    ]))->assertNotFound();
});

it('rolls back the revision row and pointer when activation fails', function (): void {
    $service = app(ThemePublicationService::class);
    $first = $service->publish('skylight', publishedThemeDocument());
    $changed = publishedThemeDocument();
    $changed['nuxtUi']['theme']['radius'] = 0.5;
    $failedRevision = $service->contentRevision($changed, null, app(ThemeCssRenderer::class)->render($changed));

    Event::listen('eloquent.saving: '.ActiveThemePublication::class, function (): never {
        throw new RuntimeException('activation failed');
    });

    expect(fn () => $service->publish('skylight', $changed))->toThrow(RuntimeException::class, 'activation failed')
        ->and(ActiveThemePublication::query()->findOrFail('skylight')->published_theme_revision_id)->toBe($first->id)
        ->and(PublishedThemeRevision::query()->count())->toBe(1);
    Storage::disk('theme-assets')->assertExists("skylight/{$failedRevision}/theme.css");
    $this->get(route('theme-assets.show', [
        'themeName' => 'skylight',
        'revision'  => $failedRevision,
        'asset'     => 'theme.css',
    ]))->assertNotFound();
});

it('uses the configured lock store to serialize competing publications', function (): void {
    $service = app(ThemePublicationService::class);
    $first = $service->publish('skylight', publishedThemeDocument());
    $lock = Cache::lock('theme-publication:'.hash('sha256', 'skylight'), 10);
    expect($lock->get())->toBeTrue();

    $changed = publishedThemeDocument();
    $changed['nuxtUi']['theme']['radius'] = 0.5;

    try {
        expect(fn () => $service->publish('skylight', $changed))->toThrow(LockTimeoutException::class);
    } finally {
        $lock->release();
    }

    expect(ActiveThemePublication::query()->findOrFail('skylight')->published_theme_revision_id)->toBe($first->id)
        ->and(PublishedThemeRevision::query()->count())->toBe(1);
});

it('rolls back by changing only the active pointer', function (): void {
    $service = app(ThemePublicationService::class);
    $first = $service->publish('skylight', publishedThemeDocument());
    $changed = publishedThemeDocument();
    $changed['nuxtUi']['theme']['radius'] = 0.5;
    $second = $service->publish('skylight', $changed, 'body { color: red; }');

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->andReturn(true);
    Storage::shouldReceive('disk')->once()->with('theme-assets')->andReturn($disk);

    $rolledBack = $service->rollback('skylight', $first);

    expect($rolledBack->is($first))->toBeTrue()
        ->and(ActiveThemePublication::query()->findOrFail('skylight')->published_theme_revision_id)->toBe($first->id)
        ->and(PublishedThemeRevision::query()->count())->toBe(2)
        ->and($second->custom_css)->not->toBeNull();
});

it('keeps revision history globally scoped by rendered theme name', function (): void {
    $service = app(ThemePublicationService::class);
    $document = publishedThemeDocument();
    $skylight = $service->publish('skylight', $document);
    $other = $service->publish('other-renderer', $document);

    expect($service->history('skylight')->modelKeys())->toBe([$skylight->id])
        ->and($service->history('other-renderer')->modelKeys())->toBe([$other->id])
        ->and($skylight->revision)->toBe($other->revision)
        ->and(PublishedThemeRevision::query()->count())->toBe(2)
        ->and(ActiveThemePublication::query()->count())->toBe(2);
});

it('rejects oversized custom CSS before writing or persisting', function (): void {
    config(['themes.custom_css_max' => 4]);

    try {
        app(ThemePublicationService::class)->publish('skylight', publishedThemeDocument(), '12345');
        $this->fail('Expected custom CSS validation to fail.');
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey('customCss');
    }

    expect(PublishedThemeRevision::query()->count())->toBe(0)
        ->and(ActiveThemePublication::query()->count())->toBe(0)
        ->and(Storage::disk('theme-assets')->allFiles())->toBe([]);
});
