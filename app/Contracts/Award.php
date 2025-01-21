<?php

namespace App\Contracts;

use App\Models\Award as AwardModel;
use App\Models\User;
use App\Models\UserAward;
use App\Support\Utils;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Base class for the Awards, you need to extend this, and implement:
 *  $name
 *  $param_description (optional)
 *  public function check($parameter=null)
 *
 * See: http://docs.phpvms.net/customizing/awards
 */
abstract class Award
{
    public $name = '';

    public $param_description = '';

    /**
     * Each award class just needs to return true or false if it should actually
     * be awarded to a user. This is the only method that needs to be implemented
     *
     * @param null $parameter Optional parameters that are passed in from the UI
     */
    abstract public function check($parameter = null): bool;

    /*
     * You don't really need to mess with anything below here
     */

    protected ?AwardModel $award;

    protected ?User $user;

    public function __construct(?AwardModel $award = null, ?User $user = null)
    {
        $this->award = $award;
        $this->user = $user;
    }

    /**
     * Run the main handler for this award class to determine if
     * it should be awarded or not. Declared as final to prevent a child
     * from accidentally overriding and breaking something
     */
    final public function handle(): void
    {
        // Check if the params are a JSON object or array
        $param = $this->award->ref_model_params;
        if (Utils::isObject($this->award->ref_model_params)) {
            $param = json_decode($this->award->ref_model_params);
        }

        if ($this->check($param)) {
            $this->addAward();
        }
    }

    /**
     * Add the award to this user, if they don't already have it
     */
    protected function addAward(): bool|UserAward|null
    {
        $w = [
            'user_id'  => $this->user->id,
            'award_id' => $this->award->id,
        ];

        $found = UserAward::where($w)->count('id');
        if ($found > 0) {
            return true;
        }

        // Associate this award to the user now
        $award = new UserAward($w);

        try {
            $award->save();
        } catch (Exception $e) {
            Log::error(
                'Error saving award: '.$e->getMessage(),
                $e->getTrace()
            );

            return false;
        }

        return $award;
    }
}
