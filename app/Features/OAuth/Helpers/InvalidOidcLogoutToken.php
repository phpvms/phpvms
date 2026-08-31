<?php

declare(strict_types=1);

namespace App\Features\OAuth\Helpers;

use RuntimeException;

final class InvalidOidcLogoutToken extends RuntimeException {}
