<?php

declare(strict_types=1);

namespace App\Services\Awards;

use App\Models\Award;
use App\Models\User;
use App\Models\UserAward;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Grants a rules-based award to a user through the same dupe-guarded
 * `user_awards` insert as `Contracts\Award::addAward()` (`UserAward`
 * creation dispatches `AwardAwarded` via a model event). Shared by the
 * PIREP-trigger listener and the nightly user-trigger sweep so the guard
 * and notification pipeline live in one place, never duplicated.
 */
class AwardGrantService
{
    /**
     * Insert the user_awards row if the user doesn't already hold this
     * award. Returns true when a new row was created.
     */
    public function grant(Award $award, User $user): bool
    {
        $w = [
            'user_id'  => $user->id,
            'award_id' => $award->id,
        ];

        if (UserAward::where($w)->count('id') > 0) {
            return false;
        }

        try {
            UserAward::create($w);
        } catch (Exception $exception) {
            Log::error('Error saving award: '.$exception->getMessage(), $exception->getTrace());

            return false;
        }

        return true;
    }
}
