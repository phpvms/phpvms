<?php

declare(strict_types=1);

namespace PhpvmsOwnershipFixture\Models;

use App\Contracts\Addons\Attributes\AddonModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Carries #[AddonModel] but points at a core table, which core must never drop.
 */
#[AddonModel]
class CoreBoundWidget extends Model
{
    protected $table = 'users';
}
