<?php

namespace App\Models;

use App\Contracts\Model;
use App\Traits\HasNanoIds;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Override;

/**
 * @property string      $id
 * @property string      $name
 * @property string|null $description
 * @property string|null $disk
 * @property string|null $path
 * @property bool        $public
 * @property int         $download_count
 * @property string|null $ref_model_type
 * @property string|null $ref_model_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $extension
 * @property-read string $filename
 * @property-read bool $is_external_file
 * @property-read mixed $url
 *
 * @method static FileFactory          factory($count = null, $state = [])
 * @method static Builder<static>|File newModelQuery()
 * @method static Builder<static>|File newQuery()
 * @method static Builder<static>|File query()
 * @method static Builder<static>|File whereCreatedAt($value)
 * @method static Builder<static>|File whereDescription($value)
 * @method static Builder<static>|File whereDisk($value)
 * @method static Builder<static>|File whereDownloadCount($value)
 * @method static Builder<static>|File whereId($value)
 * @method static Builder<static>|File whereName($value)
 * @method static Builder<static>|File wherePath($value)
 * @method static Builder<static>|File wherePublic($value)
 * @method static Builder<static>|File whereRefModelId($value)
 * @method static Builder<static>|File whereRefModelType($value)
 * @method static Builder<static>|File whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class File extends Model
{
    use HasFactory;
    use HasNanoIds;

    public $table = 'files';

    protected $fillable = [
        'id',
        'name',
        'description',
        'disk',
        'path',
        'public',
        'ref_model_type',
        'ref_model_id',
    ];

    public static array $rules = [
        'name' => 'required',
    ];

    private ?array $pathinfo = null;

    /**
     * Return the file extension
     */
    public function extension(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                if (!$this->pathinfo) {
                    $this->pathinfo = pathinfo((string) $this->path);
                }

                return $this->pathinfo['extension'];
            }
        );
    }

    /**
     * Get just the filename
     */
    public function filename(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs): string {
                if (!$this->pathinfo) {
                    $this->pathinfo = pathinfo((string) $this->path);
                }

                return $this->pathinfo['filename'].'.'.$this->pathinfo['extension'];
            }
        );
    }

    /**
     * Get the full URL to this attribute
     */
    public function url(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                if (Str::startsWith($this->path, 'http')) {
                    return $this->path;
                }

                $disk = $this->disk ?? config('filesystems.public_files');

                // If the disk isn't stored in public (S3 or something),
                // just pass through the URL call
                if ($disk !== 'public') {
                    return Storage::disk(config('filesystems.public_files'))
                        ->url($this->path);
                }

                // Otherwise, figure out the public URL and save there
                return public_asset(Storage::disk('public')->url($this->path));
            }
        );
    }

    public function isExternalFile(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attrs): bool => is_null($attrs['disk']) && !str_contains((string) $this->url, (string) config('app.url')),
        );
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'public' => 'boolean',
        ];
    }
}
