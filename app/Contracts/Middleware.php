<?php

declare(strict_types=1);

namespace App\Contracts;

use Closure;
use Illuminate\Http\Request;

interface Middleware
{
    public function handle(Request $request, Closure $next);
}
