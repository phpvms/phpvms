<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Enums\BundleType;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Http\Data\TourListItemData;
use App\Models\FlightBundle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class TourController extends Controller
{
    /**
     * Every enabled tour, each with the pilot's latest run through it. A
     * disabled bundle stays listed while the pilot has a run in progress —
     * the run remains completable (see TourRetentionTest), so it must not
     * vanish from under them.
     */
    public function index(Request $request): View|InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        $liveBundleIds = UserTour::query()
            ->where('user_id', $user->id)
            ->where('status', TourStatus::InProgress)
            ->pluck('bundle_id');

        $bundles = FlightBundle::query()
            ->where('type', BundleType::Tour)
            ->where(fn ($query) => $query->where('enabled', true)->orWhereIn('id', $liveBundleIds))
            ->orderBy('name')
            ->get();

        // Latest run per bundle: ordered newest-first, unique() keeps the
        // first row it sees for each bundle_id.
        $latestRuns = UserTour::query()
            ->where('user_id', $user->id)
            ->whereIn('bundle_id', $bundles->pluck('id'))
            ->orderByDesc('started_at')
            ->get()
            ->unique('bundle_id')
            ->keyBy('bundle_id');

        // The pilot's running tours surface first; everything else stays A-Z.
        [$running, $rest] = $bundles->partition(
            fn (FlightBundle $bundle): bool => $latestRuns->get($bundle->id)?->status === TourStatus::InProgress
        );
        $bundles = $running->concat($rest);

        $tours = $bundles
            ->map(fn (FlightBundle $bundle): TourListItemData => TourListItemData::fromModel(
                $bundle,
                $latestRuns->get($bundle->id),
            ))
            ->values()
            ->all();

        return response()->themed(
            'Tours',
            'tours.index',
            bladeData: ['tours' => $tours],
            spa: ['tours' => $tours],
        );
    }
}
