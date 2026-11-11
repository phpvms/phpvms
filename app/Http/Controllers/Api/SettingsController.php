<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\SettingResource;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Class SettingsController
 */
class SettingsController extends Controller
{
    /**
     * Every visible core setting, by group, in declaration order.
     */
    public function index(Request $request, SettingsRegistry $registry): AnonymousResourceCollection
    {
        $settings = $registry->all()
            ->reject(fn (SettingDefinition $definition): bool => $definition->hidden)
            ->values();

        return SettingResource::collection($settings);
    }
}
