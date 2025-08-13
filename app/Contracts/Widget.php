<?php

namespace App\Contracts;

use Arrilot\Widgets\AbstractWidget;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

/**
 * Class Widget
 */
abstract class Widget extends AbstractWidget
{
    public $cacheTime = 0;

    /**
     * Render the template
     *
     *
     * @return Factory|View
     */
    public function view(string $template, array $vars = [])
    {
        return view($template, $vars);
    }
}
