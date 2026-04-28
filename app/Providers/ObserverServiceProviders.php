<?php

namespace App\Providers;

use App\Models\FlightField;
use App\Models\FlightFieldValue;
use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Models\Observers\JournalObserver;
use App\Models\Observers\JournalTransactionObserver;
use App\Models\Observers\Sluggable;
use App\Models\Page;
use App\Models\PirepField;
use App\Models\PirepFieldValue;
use Illuminate\Support\ServiceProvider;

class ObserverServiceProviders extends ServiceProvider
{
    public function boot(): void
    {
        Journal::observe(JournalObserver::class);
        JournalTransaction::observe(JournalTransactionObserver::class);

        FlightField::observe(Sluggable::class);
        FlightFieldValue::observe(Sluggable::class);

        Page::observe(Sluggable::class);

        PirepField::observe(Sluggable::class);
        PirepFieldValue::observe(Sluggable::class);
    }
}
