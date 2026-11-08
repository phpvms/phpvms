<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Database\Factories\ThemeSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Override;

/**
 * One piece of global or per-theme configuration, addressed by (theme, name).
 * Rows whose theme is GLOBAL_THEME apply to every theme.
 *
 * The real primary key is composite (theme, name). Eloquent has no composite
 * key support, so `$primaryKey` only names one column to satisfy the parent
 * class, and the save/select query key resolution is overridden to match on
 * both columns.
 *
 * @property string      $theme
 * @property string      $name
 * @property string      $type
 * @property string|null $content
 * @property bool        $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static ThemeSettingFactory factory($count = null, $state = [])
 */
#[WithoutIncrementing]
class ThemeSetting extends Model
{
    /** @use HasFactory<ThemeSettingFactory> */
    use HasFactory;

    /**
     * The sentinel theme name of rows that apply to every theme.
     */
    public const string GLOBAL_THEME = '*';

    public $table = 'theme_settings';

    protected $primaryKey = 'theme';

    protected $keyType = 'string';

    protected $fillable = [
        'theme',
        'name',
        'type',
        'content',
        'active',
    ];

    /**
     * The attributes that should be casted to native types.
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * The PHP type name stored in `type` for a value: string, bool, int,
     * float or array. Anything else is stored as its string form.
     */
    public static function typeOf(mixed $value): string
    {
        return match (true) {
            is_bool($value)  => 'bool',
            is_int($value)   => 'int',
            is_float($value) => 'float',
            is_array($value) => 'array',
            default          => 'string',
        };
    }

    /**
     * The text form of a value as stored in `content`; an array is JSON.
     */
    public static function encode(mixed $value): string
    {
        return match (self::typeOf($value)) {
            'bool'  => $value ? '1' : '0',
            'array' => (string) json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * The row's content cast back to the PHP type recorded in `type`.
     */
    public function typedContent(): mixed
    {
        $content = (string) $this->content;

        return match ($this->type) {
            'bool'  => $content === '1',
            'int'   => (int) $content,
            'float' => (float) $content,
            'array' => json_decode($content, true) ?? [],
            default => $content,
        };
    }

    /**
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    #[Override]
    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('theme', $this->getOriginal('theme') ?? $this->theme)
            ->where('name', $this->getOriginal('name') ?? $this->name);
    }

    /**
     * @param  Builder<static> $query
     * @return Builder<static>
     */
    #[Override]
    protected function setKeysForSelectQuery($query)
    {
        return $this->setKeysForSaveQuery($query);
    }

    /*
     * Query scopes
     */
    #[Scope]
    protected function forTheme(Builder $query, string $theme): Builder
    {
        return $query->whereIn('theme', [$theme, self::GLOBAL_THEME]);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
