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
