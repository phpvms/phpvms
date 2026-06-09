<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an addon cannot be fetched, extracted, validated, or placed.
 */
class AddonInstallException extends RuntimeException {}
