<?php

declare(strict_types=1);

namespace App\Models;

use App\Addons\Models\AddonBootCache;
use App\Addons\Models\AddonManifest;
use App\Contracts\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Override;
use Str;

/**
 * @property int         $id
 * @property string|null $name
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
 * @method static Builder<static>|Addon newModelQuery()
 * @method static Builder<static>|Addon newQuery()
 * @method static Builder<static>|Addon query()
 * @method static Builder<static>|Addon whereCreatedAt($value)
 * @method static Builder<static>|Addon whereEnabled($value)
 * @method static Builder<static>|Addon whereId($value)
 * @method static Builder<static>|Addon whereInstalledAt($value)
 * @method static Builder<static>|Addon whereNamespace($value)
 * @method static Builder<static>|Addon wherePath($value)
 * @method static Builder<static>|Addon whereRegistryId($value)
 * @method static Builder<static>|Addon whereType($value)
 * @method static Builder<static>|Addon whereUpdatedAt($value)
 * @method static Builder<static>|Addon whereVersion($value)
 *
 * @mixin \Eloquent
 */
class Addon extends Model
{
    use HasFactory;

    public $table = 'addons';

    protected $fillable = [
        'name',
        'registry_id',
        'type',
        'version',
        'namespace',
        'path',
        'enabled',
        'installed_at',
    ];

    public static array $rules = [
        'name'         => 'required|string',
        'registry_id'  => 'nullable|string',
        'type'         => 'required|string',
        'version'      => 'nullable|string',
        'namespace'    => 'required|string',
        'path'         => 'required|string',
        'enabled'      => 'boolean',
        'installed_at' => 'nullable|date',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'enabled'      => 'boolean',
            'installed_at' => 'datetime',
        ];
    }

    /**
     * Create an addon from a manifest
     *
     * @param AddonManifest $m
     */
    public static function fromBootCache(AddonBootCache $runtime): Addon
    {
        $addon = new Addon();
        $addon->name = $runtime->name;
        $addon->registry_id = $runtime->registryId;
        $addon->type = $runtime->type;
        $addon->version = $runtime->version;
        $addon->namespace = $runtime->namespace;
        $addon->path = $runtime->path;
        $addon->enabled = $runtime->enabled;

        return $addon;
    }

    /**
     * Create an addon from a manifest
     */
    public static function fromManifest(AddonManifest $m): Addon
    {
        $addon = new Addon();
        $addon->name = $m->name;
        $addon->registry_id = $m->registryId;
        $addon->type = $m->type;
        $addon->version = $m->version;
        $addon->namespace = $m->namespace;
        $addon->path = $m->path;
        $addon->enabled = $m->enabled;

        return $addon;
    }

    /**
     * Return the lower-cased addon name
     */
    public function getLowerName(): string
    {
        return strtolower((string) $this->name);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Return the studly-cased addon name
     */
    public function getStudlyName(): string
    {
        return Str::studly($this->name);
    }

    /**
     * If no registry id -> assume it's a legacy addon
     * TODO: Add a "version" column
     */
    public function isLegacy(): bool
    {
        return blank($this->registry_id);
    }

    /**
     * Return the addon name, falling back to the directory basename for bundled addons with no name
     */
    public function getName(): string
    {
        return $this->name ?? basename($this->path);
    }

    /**
     * Return the absolute filesystem path to the addon root
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Return the absolute filesystem path to a file or directory within the addon root
     */
    public function getExtraPath(string $path): string
    {
        return $this->getPath().'/'.$path;
    }

    /**
     * Whether the addon is currently enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
