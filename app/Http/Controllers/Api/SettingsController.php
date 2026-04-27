<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\Setting as SettingResource;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Class SettingsController
 */
class SettingsController extends Controller
{
    /**
     * Return all settings, ordered by `order`
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $settings = Setting::orderBy('order', 'asc')->get();

        return SettingResource::collection($settings);
    }
}
