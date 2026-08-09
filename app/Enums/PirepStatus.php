<?php

declare(strict_types=1);

/**
 * A class_alias, not a second enum, so PirepStatus::TAXI and PirepPhase::TAXI are
 * the same case. Composer resolves this path via PSR-4 and the alias defines the name.
 *
 * @deprecated Use \App\Enums\PirepPhase instead.
 */

namespace App\Enums;

class_alias(PirepPhase::class, 'App\\Enums\\PirepStatus');
