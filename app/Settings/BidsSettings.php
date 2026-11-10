<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * How pilots bid on flights.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `bids`.
 */
final class BidsSettings extends Settings
{
    public bool $disable_flight_on_bid = true;

    public bool $allow_multiple_bids = true;

    public int $expire_time = 48;

    #[Override]
    public static function group(): string
    {
        return 'bids';
    }
}
