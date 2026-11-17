<?php

declare(strict_types=1);

namespace App\Contracts\Addons\Attributes;

use Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Names the model a policy in an addon's `app/Policies` authorizes. The model
 * may belong to core. Without it, core matches `{Name}Policy` to the addon's
 * own `Models\{Name}`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PolicyFor
{
    /**
     * @param class-string<Model> $model
     */
    public function __construct(public string $model) {}
}
