<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * The SimBrief integration.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `simbrief`.
 */
final class SimbriefSettings extends Settings
{
    public string $api_key = '';

    public bool $only_bids = true;

    public int $expire_hours = 6;

    public int $noncharter_pax_weight = 185;

    public int $noncharter_baggage_weight = 35;

    public int $charter_pax_weight = 168;

    public int $charter_baggage_weight = 28;

    public bool $callsign = false;

    public bool $name_private = false;

    public bool $use_standard_weights = false;

    public bool $use_custom_airframes = false;

    #[Override]
    public static function group(): string
    {
        return 'simbrief';
    }

    /**
     * @return list<string>
     */
    #[Override]
    public static function encrypted(): array
    {
        return ['api_key'];
    }
}
