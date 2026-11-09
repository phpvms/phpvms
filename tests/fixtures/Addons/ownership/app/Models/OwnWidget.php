<?php

declare(strict_types=1);

namespace PhpvmsOwnershipFixture\Models;

use App\Contracts\Addons\Attributes\AddonModel;
use Illuminate\Database\Eloquent\Model;

#[AddonModel]
class OwnWidget extends Model
{
    protected $table = 'own_widgets';
}
