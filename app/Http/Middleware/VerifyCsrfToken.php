<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class VerifyCsrfToken extends PreventRequestForgery implements Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [];
}
