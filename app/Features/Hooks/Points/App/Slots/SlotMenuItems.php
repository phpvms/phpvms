<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points\App\Slots;

use App\Features\Hooks\RenderHook;
use App\Models\User;

/**
 * Render hook after the items of the site menu (airside theme only).
 *
 * The menu is drawn as sidebar items or as top bar list items, depending on the
 * theme's layout, so a fill must match `$placement`:
 *
 * - `sidebar`: `<x-ts-side-bar.item>` entries.
 * - `topbar`: `<li>` entries in the top bar's menu row.
 * - `topbar.mobile`: `<li>` entries in the top bar's slide-over menu.
 *
 * `$user` is null for a guest.
 */
final class SlotMenuItems extends RenderHook
{
    public const string SIDEBAR = 'sidebar';

    public const string TOPBAR = 'topbar';

    public const string TOPBAR_MOBILE = 'topbar.mobile';

    public function __construct(
        public readonly string $placement,
        public readonly ?User $user,
    ) {
        parent::__construct();
    }

    public static function name(): string
    {
        return 'shell.menu.items';
    }
}
