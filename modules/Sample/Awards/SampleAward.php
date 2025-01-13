<?php

namespace Modules\Sample\Awards;

use App\Contracts\Award;

/**
 * Class SampleAward
 */
class SampleAward extends Award
{
    public $name = 'Sample Award';

    /**
     * This is the method that needs to be implemented.
     * You have access to $this->user, which holds the current
     * user the award is being checked against
     *
     * @param null $params Parameters passed in from the UI
     */
    public function check($params = null): bool
    {
        return false;
    }
}
