<?php

declare(strict_types=1);

namespace Modules\Sample\Models;

use App\Contracts\Model;

/**
 * Class SampleTable
 *
 * @property int    $id
 * @property string $name
 */
class SampleTable extends Model
{
    public $table = 'sample_items';

    protected $fillable = [
        'name',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'id'   => 'integer',
            'name' => 'string',
        ];
    }
}
