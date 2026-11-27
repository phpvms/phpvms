<?php

declare(strict_types=1);

namespace App\Features\Setup\Enums;

enum StepOutcome
{
    /** One step ran and more remain. */
    case StepRan;
    /** The last step ran and the run is Done. */
    case Finished;
    /** Only octane_reload is left and a web request cannot run it; cron finishes the run. */
    case WaitingForCron;
    case Failed;
    case Busy;
    case NothingRequested;
}
