<?php

declare(strict_types=1);

namespace Modules\SampleVueWidget\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\SampleVueWidget\Http\Data\SamplePingData;

/**
 * Data endpoint OWNED by this addon.
 *
 * The addon ships both its UI (the Vue widget) and the API it talks to. This
 * proves the third-party model: remove/disable the addon and this route is never
 * registered, so nothing in the host references it. No DB access here — this is a
 * self-contained demo returning a laravel-data object.
 */
final class SamplePingController extends Controller
{
    /**
     * Return a populated SamplePingData. Because Data is Responsable, returning
     * it renders JSON when the request Accepts JSON (the widget sends
     * `Accept: application/json`).
     */
    public function show(): SamplePingData
    {
        return new SamplePingData(
            addon: 'sample-vue-widget',
            message: 'Pong from the addon endpoint.',
            time: now()->toIso8601String(),
        );
    }
}
