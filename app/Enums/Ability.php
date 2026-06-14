<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

/**
 * The three abilities phpVMS exposes for every subject (resource model or page).
 *
 * `edit` covers create/update/duplicate; `delete` covers restore/force-delete;
 * `view` covers list/reorder. Permission names are `{ability}:{subject}`.
 */
enum Ability: string implements HasLabel
{
    case View = 'view';
    case Edit = 'edit';
    case Delete = 'delete';

    public function getLabel(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Map of Laravel/Filament policy methods to the ability that grants them.
     *
     * @return array<string, self>
     */
    public static function policyMethodMap(): array
    {
        return [
            'viewAny'        => self::View,
            'view'           => self::View,
            'reorder'        => self::View,
            'create'         => self::Edit,
            'update'         => self::Edit,
            'replicate'      => self::Edit,
            'delete'         => self::Delete,
            'deleteAny'      => self::Delete,
            'restore'        => self::Delete,
            'restoreAny'     => self::Delete,
            'forceDelete'    => self::Delete,
            'forceDeleteAny' => self::Delete,
        ];
    }

    /**
     * The permission name for this ability against the given subject slug.
     */
    public function permission(string $subject): string
    {
        return $this->value.':'.$subject;
    }
}
