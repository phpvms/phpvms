<?php

namespace App\Providers;

use App\Events\Expenses;
use App\Events\Fares;
use App\Events\PirepFiled;
use App\Events\ProfileUpdated;
use App\Events\UserStatsChanged;
use App\Listeners\AwardHandler;
use App\Listeners\BidEventHandler;
use App\Listeners\ExpenseListener;
use App\Listeners\FareListener;
use App\Listeners\FinanceEventHandler;
use App\Listeners\MessageLoggedListener;
use App\Listeners\PirepEventsHandler;
use App\Listeners\UserStateListener;
use App\Notifications\NotificationEventsHandler;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Log\Events\MessageLogged;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Expenses::class => [
            ExpenseListener::class,
        ],

        Fares::class => [
            FareListener::class,
        ],

        PirepFiled::class => [
            UserStateListener::class,
        ],

        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        UserStatsChanged::class => [

        ],

        // Log messages out to the console if running there
        MessageLogged::class => [
            MessageLoggedListener::class,
        ],

        ProfileUpdated::class => [],
    ];

    protected $subscribe = [
        BidEventHandler::class,
        FinanceEventHandler::class,
        NotificationEventsHandler::class,
        AwardHandler::class,
        PirepEventsHandler::class,
    ];
}
