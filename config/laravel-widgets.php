<?php

declare(strict_types=1);

return [
    'default_namespace' => 'App\Widgets',

    'use_jquery_for_ajax_calls' => true,

    /*
    * Set Ajax widget middleware
    */
    'route_middleware' => ['web'],

    'widget_stub'       => 'resources/stubs/widgets/widget.stub',
    'widget_plain_stub' => 'resources/stubs/widgets/widget_plain.stub',
];
