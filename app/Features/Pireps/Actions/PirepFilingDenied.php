<?php

declare(strict_types=1);

namespace App\Features\Pireps\Actions;

use RuntimeException;

/**
 * A pilot may not file this PIREP (wrong airport, aircraft not allowed, a
 * duplicate). The message is the one shown to the pilot.
 */
final class PirepFilingDenied extends RuntimeException {}
