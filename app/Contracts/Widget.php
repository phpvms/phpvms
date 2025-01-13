<?php

namespace App\Contracts;

use Arrilot\Widgets\AbstractWidget;

/**
 * Class Widget.
 */
abstract class Widget extends AbstractWidget
{
    public $cacheTime = 0;

    /**
     * Render the template.
     *
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function view(string $template, array $vars = [])
    {
        return view($template, $vars);
    }
}
