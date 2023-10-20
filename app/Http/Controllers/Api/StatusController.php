<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Services\VersionService;
use Illuminate\Http\JsonResponse;

/**
 * Class StatusController
 */
class StatusController extends Controller
{
    /**
     * @param VersionService $versionSvc
     */
    public function __construct(
        private readonly VersionService $versionSvc
    ) {
    }

    /**
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'name'    => config('app.name'),
            'version' => $this->versionSvc->getCurrentVersion(true),
            'php'     => PHP_VERSION,
        ]);
    }
}
