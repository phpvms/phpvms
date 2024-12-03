<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

/**
 * Notice for 3rd party developers.
 * 
 * This file defines the master integer value for sourcing of telemetry, logs, etc.
 * 
 * If you are a developer and want to add your software to this file, please make a pull request with your change to the phpVMS repository,
 * even if you don't intend to release your software publicly and will only be used by your community/VA. This way, integer values will not
 * conflict across systems.
 * 
 * When adding your client, please follow the comment formatting so that developers know, at a glance, who maintains the specific software.
 * 
 * Company Name or Real Name (github)
 */

class AcarsSource extends Enum
{
    /* Desktop Based Clients */
    public const VMSACARS = 0; // Nabeel S. (nabeelio)
    public const SMARTCARS = 1; // Invernyx d.b.a. TFDi Design (invernyx)

    /* Online Networks */
    public const VATSIM = 50; // N/A
    public const IVAO = 51; // N/A

    public static array $labels = [
        self::VMSACARS  => 'vmsACARS',
        self::SMARTCARS => 'smartCARS',
        self::VATSIM    => 'VATSIM',
        self::IVAO      => 'IVAO',
    ];
}