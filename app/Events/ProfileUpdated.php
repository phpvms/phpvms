<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileUpdated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @var User
     */
    public $user;

    /**
     * @var bool
     */
    public $avatarUpdated;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, bool $avatarUpdated)
    {
        $this->user = $user;
        $this->avatarUpdated = $avatarUpdated;
    }
}
