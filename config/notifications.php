<?php

declare(strict_types=1);

use App\Notifications\Messages\AdminUserRegistered;
use App\Notifications\Messages\NewsAdded;
use App\Notifications\Messages\PirepAccepted;
use App\Notifications\Messages\PirepFiled;
use App\Notifications\Messages\PirepRejected;
use App\Notifications\Messages\UserPending;
use App\Notifications\Messages\UserRegistered;
use App\Notifications\Messages\UserRejected;

return [
    /*
     * The channels that notifications are sent on
     */
    'channels' => [
        AdminUserRegistered::class => ['mail'],
        NewsAdded::class           => ['mail'],
        PirepAccepted::class       => ['mail'],
        PirepRejected::class       => ['mail'],
        PirepFiled::class          => ['mail'],
        UserPending::class         => ['mail'],
        UserRegistered::class      => ['mail'],
        UserRejected::class        => ['mail'],
    ],
];
