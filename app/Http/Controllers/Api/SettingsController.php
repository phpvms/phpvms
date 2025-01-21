<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\Setting as SettingResource;
use App\Repositories\SettingRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Class SettingsController.
 */
class SettingsController extends Controller
{
    /**
     * SettingsController constructor.
     */
    public function __construct(
        private readonly SettingRepository $settingRepo
    ) {
    }

    /**
     * Return all the airlines, paginated.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $settings = $this->settingRepo
            ->orderBy('order', 'asc')->get();

        return SettingResource::collection($settings);
    }
}
