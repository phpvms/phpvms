<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Events\Fares;
use App\Models\Enums\FareType;
use App\Models\Fare;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class FareListener extends Listener implements ShouldQueue
{
    use Queueable;

    /**
     * Return a list of additional fares/income items
     *
     * @param Fares $event
     *
     * @return mixed
     */
    public function handle(Fares $event)
    {
        $fares = [];

        // This is an example of a fare to return
        // You have the pirep on $event->pirep and any associated data
        // Cost may be skipped at all if not needed
        // Notes will be used as transaction group and it is how it will show as a line item
        /*
          $fares[] = new Fare([
            'name'  => 'Duty Free Sales',
            'type'  => FareType::PASSENGER,
            'price' => 985,
            'cost'  => 126,
            'notes' => 'InFlight Sales',
          ]);
        */

        return $fares;
    }
}
