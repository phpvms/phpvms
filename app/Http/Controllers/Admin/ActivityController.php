<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = Activity::with('causer')->orderBy('created_at', 'desc')->paginate();

        return view('admin.activities.index', [
            'activities' => $activities,
        ]);
    }

    public function show(Request $request, int $id): RedirectResponse|View
    {
        $activity = Activity::with('causer', 'subject')->find($id);

        if (!$activity) {
            flash()->error('Activity not found');
            return redirect()->route('admin.activities.index');
        }

        return view('admin.activities.show', [
            'activity' => $activity,
        ]);
    }
}
