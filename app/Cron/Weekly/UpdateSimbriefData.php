<?php

namespace App\Cron\Weekly;

use App\Contracts\Listener;
use App\Events\CronWeekly;
use App\Services\SimBriefService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Prettus\Validator\Exceptions\ValidatorException;
use UnexpectedValueException;

class UpdateSimbriefData extends Listener
{
    /**
     * Update SimBrief Support Data
     *
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws ValidatorException
     */
    public function handle(CronWeekly $event): void
    {
        Log::info('Weekly: Updating SimBrief Support Data');
        $SimBriefSVC = app(SimBriefService::class);
        $SimBriefSVC->getAircraftAndAirframes();
        $SimBriefSVC->GetBriefingLayouts();
    }
}
