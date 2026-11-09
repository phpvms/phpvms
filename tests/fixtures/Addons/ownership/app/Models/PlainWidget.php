<?php

declare(strict_types=1);

namespace PhpvmsOwnershipFixture\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inside the addon namespace but without #[AddonModel]: not an addon table.
 */
class PlainWidget extends Model
{
    protected $table = 'own_plain_widgets';
}
