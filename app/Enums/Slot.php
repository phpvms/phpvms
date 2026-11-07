<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Core extension slots: named outlets on first-party theme pages that addons
 * fill through `Extensions::slots()->register(['slot' => Slot::X, ...])`.
 *
 * Addons may still define their own slots with a plain string.
 */
enum Slot: string implements HasLabel
{
    /**
     * My Bids drawer, overview tab, next to the OFP action.
     * Context: `bid` (the bid) and `selection` (the selected bid row).
     */
    case BidsDrawerOverviewActions = 'bids.drawer.overview.actions';

    /**
     * Per-row action cell of a bid card on the My Bids page.
     * Context: `bid` and `flight` for that row.
     */
    case BidsRowActions = 'bids.row.actions';

    /**
     * Action area of the flight detail panel and the flight manifest aside.
     * Context: `flight` and `policy` (the availability policy).
     */
    case FlightsDetailActions = 'flights.detail.actions';

    /**
     * Per-row action cell of the flight manifest list.
     * Context: `flight` and `policy` for that row.
     */
    case FlightsRowActions = 'flights.row.actions';

    /**
     * Actions area of the SimBrief briefing page, above the OFP lifecycle buttons.
     * Context: `flight`, `bid`, `briefing` and `aircraft`.
     */
    case FlightBriefingActions = 'flight.briefing.actions';

    /**
     * Pilot profile page, among the profile cards.
     * Context: `profile` (the profile DTO, including `isOwnProfile`).
     */
    case ProfileCards = 'profile.cards';

    /**
     * PIREP detail page, full-width below the two-column detail grid.
     * Context: `pirep`.
     */
    case PirepsDetailMain = 'pireps.detail.main';

    public function getLabel(): string
    {
        return match ($this) {
            self::BidsDrawerOverviewActions => 'Bid drawer overview actions',
            self::BidsRowActions            => 'Bid row actions',
            self::FlightsDetailActions      => 'Flight detail actions',
            self::FlightsRowActions         => 'Flight row actions',
            self::FlightBriefingActions     => 'Flight briefing actions',
            self::ProfileCards              => 'Profile cards',
            self::PirepsDetailMain          => 'PIREP detail main',
        };
    }
}
