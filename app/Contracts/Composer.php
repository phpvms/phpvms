<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\View\View;

abstract class Composer
{
    abstract public function compose(View $view);
}
