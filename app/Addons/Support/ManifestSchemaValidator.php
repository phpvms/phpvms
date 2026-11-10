<?php

declare(strict_types=1);

namespace App\Addons\Support;

use JsonSchema\Validator;

/**
 * Checks a decoded addon.json against the registry's published manifest schema.
 */
class ManifestSchemaValidator
{
    public function __construct(
        private readonly ManifestSchema $schema,
    ) {}

    /**
     * @param  array<string, mixed> $manifest The decoded addon.json.
     * @return list<string>         "<property>: <message>" lines; empty when valid or no schema is available.
     */
    public function errors(array $manifest): array
    {
        $schema = $this->schema->schema();

        if ($schema === null) {
            return [];
        }

        // Round-trip so objects decode as objects (not arrays), as the validator expects.
        $data = json_decode((string) json_encode($manifest));
        $validator = new Validator();
        $validator->validate($data, $schema);

        $errors = [];

        foreach ($validator->getErrors() as $error) {
            $errors[] = sprintf('%s: %s', $error['property'] !== '' ? $error['property'] : '(root)', $error['message']);
        }

        return $errors;
    }
}
