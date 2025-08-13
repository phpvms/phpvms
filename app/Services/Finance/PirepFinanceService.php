<?php

namespace App\Services\Finance;

use App\Contracts\Service;
use App\Events\Expenses as ExpensesEvent;
use App\Events\Fares as FaresEvent;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Enums\ExpenseType;
use App\Models\Enums\FareType;
use App\Models\Enums\FuelType;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Expense;
use App\Models\Fare;
use App\Models\Pirep;
use App\Models\PirepFare;
use App\Models\Subfleet;
use App\Repositories\ExpenseRepository;
use App\Repositories\JournalRepository;
use App\Services\FareService;
use App\Services\FinanceService;
use App\Support\Math;
use App\Support\Money;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Prettus\Validator\Exceptions\ValidatorException;
use UnexpectedValueException;

class PirepFinanceService extends Service
{
    /**
     * FinanceService constructor.
     */
    public function __construct(
        private readonly ExpenseRepository $expenseRepo,
        private readonly FareService $fareSvc,
        private readonly FinanceService $financeSvc,
        private readonly JournalRepository $journalRepo
    ) {}

    /**
     * Process all of the finances for a pilot report. This is called
     * from a listener (FinanceEvents)
     *
     *
     * @return mixed
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws ValidatorException
     * @throws Exception
     */
    public function processFinancesForPirep(Pirep $pirep)
    {
        if (!$pirep->airline->journal) {
            $pirep->airline->journal = $pirep->airline->initJournal(setting('units.currency', 'USD'));
        }

        if (!$pirep->user->journal) {
            $pirep->user->journal = $pirep->user->initJournal(setting('units.currency', 'USD'));
        }

        // Clean out the expenses first
        $this->deleteFinancesForPirep($pirep);

        Log::info('Finance: Starting PIREP pay for '.$pirep->id);

        // Now start and pay from scratch
        $this->payFuelCosts($pirep);
        $this->payFaresForPirep($pirep);
        $this->payFaresEventsForPirep($pirep);
        $this->payExpensesForSubfleet($pirep);
        $this->payExpensesForPirep($pirep);
        $this->payAirportExpensesForPirep($pirep);
        $this->payExpensesEventsForPirep($pirep);
        $this->payGroundHandlingForPirep($pirep);
        $this->payPilotForPirep($pirep);

        $pirep->airline->journal->refresh();
        $pirep->user->journal->refresh();

        // Recalculate balances...
        $this->journalRepo->recalculateBalance($pirep->airline->journal);
        $this->journalRepo->recalculateBalance($pirep->user->journal);

        return $pirep;
    }

    public function deleteFinancesForPirep(Pirep $pirep): void
    {
        $this->journalRepo->deleteAllForObject($pirep);
    }

    /**
     * Collect all of the fares and then post each fare class's profit and
     * the costs for each seat and post it to the journal
     *
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws ValidatorException
     */
    public function payFaresForPirep($pirep): void
    {
        $fares = $this->getFaresForPirep($pirep);

        /** @var PirepFare $fare */
        foreach ($fares as $fare) {
            Log::info('Finance: PIREP: '.$pirep->id.', Fare:', $fare->toArray());

            $credit = Money::createFromAmount($fare->count * $fare->price);
            $debit = Money::createFromAmount($fare->count * $fare->cost);

            Log::info('Finance: Calculate: C='.$credit->toAmount().', D='.$debit->toAmount());

            $memo = FareType::label($fare->type).' fare: '.$fare->code.': '.$fare->count
                .'; price: '.$fare->price.', cost: '.$fare->cost;

            $this->journalRepo->post(
                $pirep->airline->journal,
                $credit,
                $debit,
                $pirep,
                $memo,
                null,
                'Fares',
                'fare'
            );
        }
    }

    /**
     * Collect all of the fares from listeners and apply those to the journal
     *
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws ValidatorException
     */
    public function payFaresEventsForPirep(Pirep $pirep): void
    {
        // Throw an event and collect any fares returned from it
        $gathered_fares = event(new FaresEvent($pirep));
        if (!\is_array($gathered_fares)) {
            return;
        }

        foreach ($gathered_fares as $event_fare) {
            if (!\is_array($event_fare)) {
                continue;
            }

            foreach ($event_fare as $fare) {
                // Make sure it's of type Fare Model
                if (!($fare instanceof Fare)) {
                    Log::info('Finance: Event Fare is not an instance of Fare Model, aborting process!');

                    continue;
                }

                $credit = Money::createFromAmount($fare->price);
                $debit = Money::createFromAmount($fare->cost);
                Log::info('Finance: Income From Listener N='.$fare->name.', C='.$credit.', D='.$debit);

                $this->journalRepo->post(
                    $pirep->airline->journal,
                    $credit,
                    $debit,
                    $pirep,
                    $fare->name,
                    null,
                    $fare->notes,
                    'additional-sales'
                );
            }
        }
    }

    /**
     * Calculate the fuel used by the PIREP and add those costs in
     *
     *
     * @throws ValidatorException
     */
    public function payFuelCosts(Pirep $pirep): void
    {
        // Get Airport Fuel Prices or Use Defaults
        $ap = $pirep->dpt_airport;

        // Get Aircraft Fuel Type from Subfleet
        // And set $fuel_cost according to type (Failsafe is Jet A)
        $sf = $pirep->aircraft->subfleet;
        $fuel_type = $sf ? $sf->fuel_type : FuelType::JET_A;

        if ($fuel_type === FuelType::LOW_LEAD) {
            $fuel_cost = empty($ap->fuel_100ll_cost) ? setting('airports.default_100ll_fuel_cost') : $ap->fuel_100ll_cost;
        } elseif ($fuel_type === FuelType::MOGAS) {
            $fuel_cost = empty($ap->fuel_mogas_cost) ? setting('airports.default_mogas_fuel_cost') : $ap->fuel_mogas_cost;
        } else { // Default to JetA
            $fuel_cost = empty($ap->fuel_jeta_cost) ? setting('airports.default_jet_a_fuel_cost') : $ap->fuel_jeta_cost;
        }

        if (setting('pireps.advanced_fuel', false)) {
            // Reading second row by skip(1) to reach the previous accepted pirep. Current pirep is at the first row
            // To get proper fuel values, we need to fetch current pirep and older ones only. Scenario: ReCalculating finances
            $prev_flight = Pirep::where([
                'aircraft_id' => $pirep->aircraft->id,
                'state'       => PirepState::ACCEPTED,
                'status'      => PirepStatus::ARRIVED,
            ])
                ->where('submitted_at', '<=', $pirep->submitted_at)
                ->orderby('submitted_at', 'desc')
                ->skip(1)
                ->first();

            if ($prev_flight) {
                // If there is a pirep use its values to calculate the remaining fuel
                // and calculate the uplifted fuel amount for this pirep
                $fuel_amount = $pirep->block_fuel->internal() - ($prev_flight->block_fuel->internal() - $prev_flight->fuel_used->internal());
                // Aircraft has more than enough fuel in its tanks, no uplift necessary
                if ($fuel_amount < 0) {
                    $fuel_amount = 0;
                }
            } else {
                // No pirep found for aircraft, debit full block fuel
                $fuel_amount = $pirep->block_fuel->internal();
            }
        } else {
            // Setting is false, switch back to basic calculation
            $fuel_amount = $pirep->fuel_used->internal();
        }

        $debit = Money::createFromAmount($fuel_amount * $fuel_cost);
        Log::info('Finance: Fuel cost, (fuel='.$fuel_amount.', cost='.$fuel_cost.') D='.$debit->getAmount());

        $this->financeSvc->debitFromJournal(
            $pirep->airline->journal,
            $debit,
            $pirep,
            'Fuel Cost ('.$fuel_cost.'/'.config('phpvms.internal_units.fuel').')',
            'Fuel',
            'fuel'
        );
    }

    /**
     * Calculate what the cost is for the operating an aircraft
     * in this subfleet, as-per the block time
     *
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws ValidatorException
     */
    public function payExpensesForSubfleet(Pirep $pirep): void
    {
        $sf = $pirep->aircraft->subfleet;

        // Haven't entered a cost
        if (!filled($sf->cost_block_hour)) {
            return;
        }

        // Convert to cost per-minute
        $cost_per_min = round($sf->cost_block_hour / 60, 2);

        // Time to use - use the block time if it's there, actual
        // flight time if that hasn't been used
        $block_time = $pirep->block_time;
        if (!filled($block_time)) {
            Log::info('Finance: No block time, using PIREP flight time');
            $block_time = $pirep->flight_time;
        }

        $debit = Money::createFromAmount($cost_per_min * $block_time);
        Log::info('Finance: Subfleet Block Hourly, D='.$debit->getAmount());

        $this->financeSvc->debitFromJournal(
            $pirep->airline->journal,
            $debit,
            $pirep,
            'Subfleet '.$sf->type.': Block Time Cost',
            'Subfleet '.$sf->type,
            'subfleet'
        );
    }

    /**
     * Collect all of the expenses and apply those to the journal
     *
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function payExpensesForPirep(Pirep $pirep): void
    {
        $expenses = $this->expenseRepo->getAllForType(
            ExpenseType::FLIGHT,
            $pirep->airline_id
        );

        /*
         * Go through the expenses and apply a mulitplier if present
         */
        $expenses->map(function (Expense $expense, $i) use ($pirep) {
            // Airport expenses are paid out separately
            if ($expense->ref_model === Airport::class) {
                return;
            }

            Log::info('Finance: PIREP: '.$pirep->id.', expense:', $expense->toArray());

            // Check to see if there is a certain fleet or flight type set on this expense
            // if there is and it doesn't match up the flight type for the PIREP, skip it
            if ($expense->ref_model === Expense::class && (is_array($expense->flight_type) && $expense->flight_type !== []) && !in_array($pirep->flight_type, $expense->flight_type, true)) {
                return;
            }

            // Get the transaction group name from the ref_model name
            // This way it can be more dynamic and don't have to add special
            // tables or specific expense calls to accomodate all of these
            $klass = 'Expense';
            if ($expense->ref_model) {
                $ref = explode('\\', $expense->ref_model);
                $klass = end($ref);
            }

            // Form the memo, with some specific ones depending on the group
            if ($expense->ref_model === Subfleet::class) {
                if ((int) $expense->ref_model_id === $pirep->aircraft->subfleet->id) {
                    $memo = "Subfleet Expense: $expense->name ({$pirep->aircraft->subfleet->name}) dd";
                    $transaction_group = "Subfleet: $expense->name ({$pirep->aircraft->subfleet->name})";
                } else { // Skip any subfleets that weren't used for this flight
                    return;
                }
            } elseif ($expense->ref_model === Aircraft::class) {
                if ((int) $expense->ref_model_id === $pirep->aircraft->id) {
                    $memo = "Aircraft Expense: $expense->name ({$pirep->aircraft->name})";
                    $transaction_group = "Aircraft: $expense->name "
                        ."({$pirep->aircraft->name}-{$pirep->aircraft->registration})";
                } else { // Skip any aircraft expenses that weren't used for this flight
                    return;
                }
            } else {
                // Skip any expenses that aren't for the airline this flight was for
                if ($expense->airline_id && $expense->airline_id !== $pirep->airline_id) {
                    return;
                }

                $memo = "Expense: $expense->name";
                $transaction_group = "Expense: $expense->name";
            }

            if (filled($pirep->aircraft->subfleet->ground_handling_multiplier) && $expense->multiplier == 1) {
                // force into percent mode
                $multiplier = $pirep->aircraft->subfleet->ground_handling_multiplier.'%';
                // do the math and calculate the amount
                $expense->amount = Math::applyAmountOrPercent($expense->amount, $multiplier);
            }

            $debit = Money::createFromAmount($expense->amount);

            // If the expense is marked to charge it to a user (only applicable to Flight)
            // then change the journal to the user's to debit there
            $journal = $pirep->airline->journal;
            if ($expense->charge_to_user) {
                $journal = $pirep->user->journal;
            }

            $this->financeSvc->debitFromJournal(
                $journal,
                $debit,
                $pirep,
                $memo,
                $transaction_group,
                strtolower($klass)
            );
        });
    }

    /**
     * Pay the airport-specific expenses for a PIREP
     */
    public function payAirportExpensesForPirep(Pirep $pirep): void
    {
        $expenses = $this->expenseRepo->getAllForType(
            ExpenseType::FLIGHT,
            $pirep->airline_id,
            Airport::class,
            $pirep->arr_airport_id
        );

        /*
         * Go through the expenses and apply a mulitplier if present
         */
        $expenses->map(function (Expense $expense, $i) use ($pirep) {
            Log::info('Finance: PIREP: '.$pirep->id.', airport expense:', $expense->toArray());

            $memo = "Airport Expense: $expense->name ($expense->ref_model_id)";
            $transaction_group = "Airport: $expense->ref_model_id";

            $debit = Money::createFromAmount($expense->amount);

            // Charge to the airlines journal
            $journal = $pirep->airline->journal;
            $this->financeSvc->debitFromJournal(
                $journal,
                $debit,
                $pirep,
                $memo,
                $transaction_group,
                'airport'
            );
        });
    }

    /**
     * Collect all of the expenses from the listeners and apply those to the journal
     *
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws ValidatorException
     */
    public function payExpensesEventsForPirep(Pirep $pirep): void
    {
        /**
         * Throw an event and collect any expenses returned from it
         */
        $gathered_expenses = event(new ExpensesEvent($pirep));
        if (!\is_array($gathered_expenses)) {
            return;
        }

        foreach ($gathered_expenses as $event_expense) {
            if (!\is_array($event_expense)) {
                continue;
            }

            foreach ($event_expense as $expense) {
                // Make sure it's of type expense Model
                if (!($expense instanceof Expense)) {
                    continue;
                }

                Log::info('Finance: Expense from listener, N="'
                    .$expense->name.'", A='.$expense->amount);

                // If an airline_id is filled, then see if it matches
                /* @noinspection NotOptimalIfConditionsInspection */
                if (filled($expense->airline_id) && $expense->airline_id !== $pirep->airline_id) {
                    Log::info('Finance: Expense has an airline ID and it doesn\'t match, skipping');

                    continue;
                }

                $debit = Money::createFromAmount($expense->amount);

                $this->financeSvc->debitFromJournal(
                    $pirep->airline->journal,
                    $debit,
                    $pirep,
                    'Expense: '.$expense->name,
                    $expense->transaction_group ?? 'Expenses',
                    'expense'
                );
            }
        }
    }

    /**
     * Collect and apply the ground handling cost
     *
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws ValidatorException
     */
    public function payGroundHandlingForPirep(Pirep $pirep): void
    {
        $ground_handling_cost = $this->getGroundHandlingCost($pirep, $pirep->dpt_airport);
        Log::info('Finance: PIREP: '.$pirep->id.'; dpt ground handling: '.$ground_handling_cost);

        $this->financeSvc->debitFromJournal(
            $pirep->airline->journal,
            Money::createFromAmount($ground_handling_cost),
            $pirep,
            'Ground Handling (Departure)',
            'Ground Handling',
            'ground_handling'
        );

        $ground_handling_cost = $this->getGroundHandlingCost($pirep, $pirep->arr_airport);
        Log::info('Finance: PIREP: '.$pirep->id.'; arr ground handling: '.$ground_handling_cost);

        $this->financeSvc->debitFromJournal(
            $pirep->airline->journal,
            Money::createFromAmount($ground_handling_cost),
            $pirep,
            'Ground Handling (Arrival)',
            'Ground Handling',
            'ground_handling'
        );
    }

    /**
     * Figure out what the pilot pay is. Debit it from the airline journal
     * But also reference the PIREP
     *
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws ValidatorException
     */
    public function payPilotForPirep(Pirep $pirep): void
    {
        $pilot_pay = $this->getPilotPay($pirep);

        if ($pirep->flight && !empty($pirep->flight->pilot_pay)) {
            $memo = 'Pilot fixed payment for flight: '.$pirep->flight->pilot_pay;
            Log::info('Finance: PIREP: '.$pirep->id
                .'; pilot pay: fixed for flight='.$pirep->flight->pilot_pay.', total: '.$pilot_pay);
        } else {
            $pilot_pay_rate = $this->getPilotPayRateForPirep($pirep);
            $memo = 'Pilot Payment @ '.$pilot_pay_rate;

            Log::info('Finance: PIREP: '.$pirep->id
                .'; pilot pay: '.$pilot_pay_rate.', total: '.$pilot_pay);
        }

        $this->financeSvc->debitFromJournal(
            $pirep->airline->journal,
            $pilot_pay,
            $pirep,
            $memo,
            'Pilot Pay',
            'pilot_pay'
        );

        $this->financeSvc->creditToJournal(
            $pirep->user->journal,
            $pilot_pay,
            $pirep,
            $memo,
            'Pilot Pay',
            'pilot_pay'
        );
    }

    /**
     * Return all of the fares for the PIREP
     *
     *
     * @return Collection
     */
    public function getFaresForPirep($pirep)
    {
        // Collect all of the fares and prices
        return $this->fareSvc->getForPirep($pirep);
    }

    /**
     * Return the costs for the ground handling, with the multiplier
     * being applied from the subfleet
     */
    public function getGroundHandlingCost(Pirep $pirep, Airport $airport): ?float
    {
        if (empty($airport->ground_handling_cost)) {
            $gh_cost = setting('airports.default_ground_handling_cost');
        } else {
            $gh_cost = $airport->ground_handling_cost;
        }

        if (!filled($pirep->aircraft->subfleet->ground_handling_multiplier)) {
            return $gh_cost;
        }

        // force into percent mode
        $multiplier = $pirep->aircraft->subfleet->ground_handling_multiplier.'%';

        return Math::applyAmountOrPercent($gh_cost, $multiplier);
    }

    /**
     * Return the pilot's hourly pay for the given PIREP
     *
     *
     * @return float
     *
     * @throws InvalidArgumentException
     */
    public function getPilotPayRateForPirep(Pirep $pirep)
    {
        // Get the base rate for the rank
        $rank = $pirep->user->rank;
        $subfleet_id = $pirep->aircraft->subfleet_id;

        // find the right subfleet
        $override_rate = $rank->subfleets()
            ->where('subfleet_id', $subfleet_id)
            ->first();

        if ($override_rate) {
            $override_rate = $override_rate->pivot;
        }

        if ($pirep->source === PirepSource::ACARS) {
            Log::debug('Source is ACARS');
            $base_rate = $rank->acars_base_pay_rate;

            if ($override_rate) {
                $override_rate = $override_rate->acars_pay;
            }
        } else {
            Log::debug('Source is Manual');
            $base_rate = $rank->manual_base_pay_rate;

            if ($override_rate) {
                $override_rate = $override_rate->manual_pay;
            }
        }

        Log::debug('pilot pay: base rate='.$base_rate.', override='.$override_rate);

        return Math::applyAmountOrPercent(
            $base_rate,
            $override_rate
        );
    }

    /**
     * Get the user's payment amount for a PIREP
     *
     *
     * @return Money
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getPilotPay(Pirep $pirep)
    {
        // If there is a fixed price for this flight, return that amount
        $flight = $pirep->flight;
        if ($flight && !empty($flight->pilot_pay)) {
            return new Money(Money::convertToSubunit($flight->pilot_pay));
        }

        // Divided by 60 to get the rate per minute
        $pilot_rate = $this->getPilotPayRateForPirep($pirep) / 60;
        $payment = round($pirep->flight_time * $pilot_rate, 2);

        Log::info('Pilot Payment: rate='.$pilot_rate);
        $payment = Money::convertToSubunit($payment);

        return new Money($payment);
    }
}
