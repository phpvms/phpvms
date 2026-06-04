<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property string|null $registry_id
 * @property string      $type
 * @property string|null $version
 * @property string      $namespace
 * @property string      $path
 * @property bool        $enabled
 * @property Carbon|null $installed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon whereInstalledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon whereNamespace($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon whereRegistryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Addon whereVersion($value)
 *
 * @mixin \Eloquent
 */
class Addon extends Model
{
    use HasFactory;

    public $table = 'addons';

    protected $fillable = [
        'registry_id',
        'type',
        'version',
        'namespace',
        'path',
        'enabled',
        'installed_at',
    ];

    public static array $rules = [
        'registry_id'  => 'nullable|string',
        'type'         => 'required|string',
        'version'      => 'nullable|string',
        'namespace'    => 'required|string',
        'path'         => 'required|string',
        'enabled'      => 'boolean',
        'installed_at' => 'nullable|date',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'enabled'      => 'boolean',
            'installed_at' => 'datetime',
        ];
    }
}
