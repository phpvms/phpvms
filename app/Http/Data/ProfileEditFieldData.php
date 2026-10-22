<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\UserField;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One editable custom user field. Posts back as `field_{slug}`, matching the
 * name the Blade form uses and the key ProfileController::update() reads when
 * it writes UserFieldValue rows.
 *
 * Always a text input: `user_fields` has no `type` or `options` column (see the
 * create in 2025_01_13_003704_create_phpvms_table.php), so the Blade form's
 * `@if ($field->type === 'select')` branch can never be taken. There is no
 * select variant to model here.
 */
#[TypeScript]
final class ProfileEditFieldData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $value,
        public bool $required,
    ) {}

    /**
     * `$field->value` is grafted on by UserService::getUserFields(); it is not
     * a column, so it stays null when the pilot has never saved that field.
     */
    public static function fromModel(UserField $field): self
    {
        return new self(
            slug: $field->slug,
            name: $field->name,
            value: $field->value === null ? null : (string) $field->value,
            required: (bool) $field->required,
        );
    }
}
