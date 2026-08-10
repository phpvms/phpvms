<?php

declare(strict_types=1);

use App\Models\ActiveThemePublication;
use App\Models\PublishedThemeRevision;
use App\Services\Theme\ThemeDocumentNormalizer;
use App\Services\Theme\ThemePublicationService;
use Illuminate\Validation\ValidationException;

function rawThemeFixture(): array
{
    return json_decode(
        file_get_contents(resource_path('js/apps/fe-vue/tests/fixtures/nuxt-ui-themes-b3b334c4.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
}

it('normalizes the verified raw builder fixture without persisting a draft', function (): void {
    $normalizer = app(ThemeDocumentNormalizer::class);

    $document = $normalizer->normalizeJson(
        file_get_contents(resource_path('js/apps/fe-vue/tests/fixtures/nuxt-ui-themes-b3b334c4.json')),
    );

    expect($document)->toBe($normalizer->defaults())
        ->and(PublishedThemeRevision::query()->count())->toBe(0)
        ->and(ActiveThemePublication::query()->count())->toBe(0);
});

it('matches the frontend legacy raw defaults', function (): void {
    $raw = rawThemeFixture();
    unset(
        $raw['colorShades'],
        $raw['darkColors'],
        $raw['darkColorShades'],
        $raw['darkNeutral'],
        $raw['darkRadius'],
        $raw['darkFont'],
    );

    $theme = app(ThemeDocumentNormalizer::class)->normalize($raw)['nuxtUi']['theme'];

    expect(array_values(array_unique($theme['colorShades'])))->toBe(['500'])
        ->and($theme['darkColors'])->toBe($theme['colors'])
        ->and($theme['darkColorShades'])->toBe($theme['colorShades'])
        ->and($theme['darkNeutral'])->toBe($theme['neutral'])
        ->and($theme['darkRadius'])->toBe($theme['radius'])
        ->and($theme['darkFont'])->toBe($theme['font']);
});

it('rejects an unsupported document version because version one has no migrations', function (): void {
    $document = app(ThemeDocumentNormalizer::class)->defaults();
    $document['version'] = 0;

    try {
        app(ThemeDocumentNormalizer::class)->normalize($document);
        $this->fail('Expected theme validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('version');
    }
});

it('rejects malformed JSON at the root field', function (): void {
    try {
        app(ThemeDocumentNormalizer::class)->normalizeJson('{');
        $this->fail('Expected JSON validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('$');
    }
});

it('rejects unsafe classes with field-level errors', function (): void {
    $document = app(ThemeDocumentNormalizer::class)->defaults();
    $document['nuxtUi']['components']['button']['style']['shape'] = 'rounded-full';

    try {
        app(ThemeDocumentNormalizer::class)->normalize($document);
        $this->fail('Expected theme validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('nuxtUi.components.button.style.shape');
    }
});

it('resolves nested schema refs and rejects nested additional properties', function (): void {
    $document = app(ThemeDocumentNormalizer::class)->defaults();
    $document['nuxtUi']['components']['button']['ui'] = ['base' => 'p-4'];

    try {
        app(ThemeDocumentNormalizer::class)->normalize($document);
        $this->fail('Expected theme validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey('nuxtUi.components.button')
            ->and($exception->errors()['nuxtUi.components.button'][0])
            ->toContain('property ui is not defined');
    }
});

it('returns a transient preview without durable theme data', function (): void {
    $preview = app(ThemePublicationService::class)->preview(rawThemeFixture());

    expect(array_keys($preview))->toBe(['document', 'diagnostics', 'css', 'resolvedInput', 'targets'])
        ->and($preview['document']['version'])->toBe(1)
        ->and($preview['diagnostics'])->toBe([])
        ->and($preview['resolvedInput'])->toBe([
            'components' => $preview['document']['nuxtUi']['components'],
            'phpvms'     => $preview['document']['phpvms'],
        ])
        ->and($preview['targets'])->toBe([
            'routes'     => ['dashboard', 'profile', 'pirep-detail'],
            'components' => ['button', 'input', 'dashboard-toolbar', 'pirep-summary'],
        ])
        ->and(PublishedThemeRevision::query()->count())->toBe(0)
        ->and(ActiveThemePublication::query()->count())->toBe(0);
});
