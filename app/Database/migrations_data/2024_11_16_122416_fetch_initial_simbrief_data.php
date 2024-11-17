<?php

use App\Services\SimBriefService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('simbrief_aircraft') && Schema::hasTable('simbrief_airframes')) {

            $SimBriefSVC = app(SimBriefService::class);
            $SimBriefSVC->getAircraftAndAirframes();
            $SimBriefSVC->GetBriefingLayouts();
        }
    }
};
