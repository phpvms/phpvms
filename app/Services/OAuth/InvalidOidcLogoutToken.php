<?php

declare(strict_types=1);

namespace App\Services\OAuth;

use RuntimeException;

final class InvalidOidcLogoutToken extends RuntimeException {}
