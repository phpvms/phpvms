<?php

declare(strict_types=1);

namespace App\Services\Theme;

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use JsonException;
use JsonSchema\Validator;
use stdClass;

final class ThemeDocumentNormalizer
{
    public const int VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function normalizeJson(string $json): array
    {
        try {
            $input = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw ValidationException::withMessages([
                '$' => ['Invalid JSON: '.$jsonException->getMessage()],
            ]);
        }

        if (!is_array($input) || array_is_list($input)) {
            throw ValidationException::withMessages([
                '$' => ['The theme document must be a JSON object.'],
            ]);
        }

        return $this->normalize($input);
    }

    /**
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        if (array_key_exists('version', $input)) {
            if ($input['version'] !== self::VERSION) {
                throw ValidationException::withMessages([
                    'version' => ['Unsupported theme document version.'],
                ]);
            }

            $document = $input;
        } else {
            $document = $this->defaults();
            $document['nuxtUi']['theme'] = $this->normalizeRawTheme($input);
        }

        $this->validate($document);

        foreach (['button', 'input'] as $componentName) {
            $component = $document['nuxtUi']['components'][$componentName] ?? null;

            if (!is_array($component)) {
                continue;
            }

            foreach (['props', 'style'] as $sectionName) {
                if (($component[$sectionName] ?? null) === []) {
                    unset($component[$sectionName]);
                }
            }

            if ($component === []) {
                unset($document['nuxtUi']['components'][$componentName]);
            } else {
                $document['nuxtUi']['components'][$componentName] = $component;
            }
        }

        if (($document['nuxtUi']['components'] ?? null) === []) {
            unset($document['nuxtUi']['components']);
        }

        return $document;
    }

    /**
     * Upstream exports before color-shade and dark-theme controls existed omit
     * these fields. The current builder parser supplies these same values.
     *
     * @param  array<string, mixed> $theme
     * @return array<string, mixed>
     */
    private function normalizeRawTheme(array $theme): array
    {
        $theme['colorShades'] ??= array_fill_keys(
            ['primary', 'secondary', 'success', 'info', 'warning', 'error'],
            '500',
        );
        $theme['darkColors'] ??= $theme['colors'] ?? null;
        $theme['darkColorShades'] ??= $theme['colorShades'];
        $theme['darkNeutral'] ??= $theme['neutral'] ?? null;
        $theme['darkRadius'] ??= $theme['radius'] ?? null;
        $theme['darkFont'] ??= $theme['font'] ?? null;

        return $theme;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->readJson(resource_path('js/apps/fe-vue/schemas/skylight-theme-v1.defaults.json'));
    }

    /**
     * @param array<string, mixed> $document
     */
    private function validate(array $document): void
    {
        $data = json_decode(json_encode($document, JSON_THROW_ON_ERROR));
        $components = $data->nuxtUi->components ?? null;

        if ($components === []) {
            $data->nuxtUi->components = new stdClass();
        } elseif (is_object($components)) {
            foreach (['button', 'input'] as $componentName) {
                $component = $components->{$componentName} ?? null;

                if ($component === []) {
                    $components->{$componentName} = new stdClass();

                    continue;
                }

                if (!is_object($component)) {
                    continue;
                }

                foreach (['props', 'style'] as $sectionName) {
                    if (($component->{$sectionName} ?? null) === []) {
                        $component->{$sectionName} = new stdClass();
                    }
                }
            }
        }

        $schema = json_decode(File::get(resource_path('js/apps/fe-vue/schemas/skylight-theme-v1.schema.json')));
        $validator = new Validator();
        $validator->validate($data, $schema);

        if ($validator->isValid()) {
            return;
        }

        $messages = [];
        foreach ($validator->getErrors() as $error) {
            $field = $error['property'] === '' ? '$' : $error['property'];
            $messages[$field][] = $error['message'];
        }

        throw ValidationException::withMessages($messages);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $value = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($value)) {
            throw new JsonException("Expected a JSON object at {$path}");
        }

        return $value;
    }
}
