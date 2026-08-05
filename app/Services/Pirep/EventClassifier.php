<?php

namespace App\Services\Pirep;

use App\Enums\PirepPhase;

/**
 * Best-effort classifier that maps a free-text ACARS event log string to a
 * structured type/category/phase/details tuple. A straight port of the C#
 * client's `LegacyEventClassifier` (acars/src/Acars.Models/Client/), plus one
 * addition: phase-literal detection over PerformanceChartService::LOG_MARKERS
 * for legacy phase-transition strings the C# classifier does not cover
 * (design decision 5, pirep-events-table).
 *
 * Never throws; unmatched, null, or blank input degrades to
 * type = null, category = 'message', phase = null, details = null.
 */
class EventClassifier
{
    /**
     * Log-substring → PirepPhase marker table, mirroring
     * PerformanceChartService::LOG_MARKERS. Case-insensitive substring match.
     *
     * @var array<int, array{needle: string, phase: PirepPhase, is_phase_change: bool}>
     */
    private const array PHASE_MARKERS = [
        ['needle' => 'started boarding',   'phase' => PirepPhase::BOARDING,      'is_phase_change' => true],
        ['needle' => 'started pushback',   'phase' => PirepPhase::PUSHBACK_TOW,  'is_phase_change' => true],
        ['needle' => 'started taxi out',   'phase' => PirepPhase::TAXI,          'is_phase_change' => true],
        ['needle' => 'started takeoff',    'phase' => PirepPhase::TAKEOFF,       'is_phase_change' => true],
        ['needle' => 'on final approach',  'phase' => PirepPhase::ON_FINAL,      'is_phase_change' => true],
        ['needle' => 'on approach',        'phase' => PirepPhase::APPROACH_ICAO, 'is_phase_change' => true],
        ['needle' => 'landing rate',       'phase' => PirepPhase::LANDING,       'is_phase_change' => true],
        ['needle' => 'blocks on time',     'phase' => PirepPhase::ON_BLOCK,      'is_phase_change' => true],
        ['needle' => 'flaps set to up',    'phase' => PirepPhase::ENROUTE,       'is_phase_change' => false],
    ];

    /**
     * Classifies a free-text event log string. Never throws.
     *
     * @return array{type: ?string, category: string, phase: ?string, details: ?array}
     */
    public static function classify(?string $log): array
    {
        $trimmed = trim((string) $log);

        if ($trimmed === '') {
            return ['type' => null, 'category' => 'message', 'phase' => null, 'details' => null];
        }

        $result = self::classifyType($trimmed);

        // "flaps set to up" is a real flaps-change event; the phase is an
        // additional, independent hint the chart service also uses.
        if ($result['type'] === 'flaps-change') {
            $result['phase'] = self::phaseOf($trimmed);
        }

        return $result;
    }

    /**
     * @return array{type: ?string, category: string, phase: ?string, details: ?array}
     */
    private static function classifyType(string $log): array
    {
        // Phase-transition literals (checked before the generic feature-toggle
        // pattern, which would otherwise never match these anyway since they
        // don't fit the "{feature} set to {on|off}" shape).
        foreach (self::PHASE_MARKERS as $marker) {
            if ($marker['is_phase_change'] && str_contains(strtolower($log), $marker['needle'])) {
                return [
                    'type'     => 'phase-change',
                    'category' => 'phase',
                    'phase'    => $marker['phase']->value,
                    'details'  => null,
                ];
            }
        }

        // "Engine 2 is on" / "Engine 2 is off"
        if (preg_match('/^Engine\s+(?<num>\d+)\s+is\s+(?<state>on|off)$/i', $log, $m)) {
            $isOn = strtolower($m['state']) === 'on';

            return [
                'type'     => $isOn ? 'engine-start' : 'engine-stop',
                'category' => 'aircraft',
                'phase'    => null,
                'details'  => ['engine_number' => (int) $m['num'], 'state' => $isOn],
            ];
        }

        // "Parking brake set" / "Parking brake released"
        if (preg_match('/^Parking\s+brake\s+(?<state>set|released)$/i', $log, $m)) {
            $isSet = strtolower($m['state']) === 'set';

            return [
                'type'     => $isSet ? 'parking-brake-set' : 'parking-brake-released',
                'category' => 'aircraft',
                'phase'    => null,
                'details'  => ['feature' => 'ParkingBrakes', 'state' => $isSet],
            ];
        }

        // "Landing Gear set to up" / "Landing Gear set to down"
        if (preg_match('/^Landing\s+Gear\s+set\s+to\s+(?<state>up|down)$/i', $log, $m)) {
            $isUp = strtolower($m['state']) === 'up';

            return [
                'type'     => $isUp ? 'gear-up' : 'gear-down',
                'category' => 'aircraft',
                'phase'    => null,
                'details'  => ['feature' => 'LandingGear', 'state' => $isUp],
            ];
        }

        // "Flaps set to {x}" - not "Flaps set to on/off" (that's a feature toggle).
        if (preg_match('/^Flaps\s+set\s+to\s+(?!(?:on|off)$)(?<flaps>.+)$/i', $log, $m)) {
            return [
                'type'     => 'flaps-change',
                'category' => 'aircraft',
                'phase'    => null,
                'details'  => ['flaps' => trim($m['flaps'])],
            ];
        }

        // "Transponder changed to {code}"
        if (preg_match('/^Transponder\s+changed\s+to\s+(?<squawk>\d+)$/i', $log, $m)) {
            return [
                'type'     => 'transponder-change',
                'category' => 'systems',
                'phase'    => null,
                'details'  => ['squawk' => (int) $m['squawk']],
            ];
        }

        // "Sim rate increased to {n}x" / "Sim rate decreased to {n}x"
        if (preg_match('/^Sim\s+rate\s+(?:increased|decreased)\s+to\s+(?<rate>[\d.]+)x$/i', $log, $m)) {
            return [
                'type'     => 'sim-rate-change',
                'category' => 'systems',
                'phase'    => null,
                'details'  => ['sim_rate' => (float) $m['rate']],
            ];
        }

        // "TOC reached"
        if (preg_match('/^TOC\s+reached$/i', $log)) {
            return ['type' => 'top-of-climb', 'category' => 'milestone', 'phase' => null, 'details' => null];
        }

        // "Top of descent reached"
        if (preg_match('/^Top\s+of\s+descent\s+reached$/i', $log)) {
            return ['type' => 'top-of-descent', 'category' => 'milestone', 'phase' => null, 'details' => null];
        }

        // "Reached flight minimum altitude"
        if (preg_match('/^Reached\s+flight\s+minimum\s+altitude$/i', $log)) {
            return ['type' => 'min-altitude', 'category' => 'milestone', 'phase' => null, 'details' => null];
        }

        // "On or crossing runway {id}"
        if (preg_match('/^On\s+or\s+crossing\s+runway\s+(?<runway>.+)$/i', $log, $m)) {
            return [
                'type'     => 'runway-cross',
                'category' => 'milestone',
                'phase'    => null,
                'details'  => ['runway' => trim($m['runway'])],
            ];
        }

        // "Change to unlimited fuel setting: on" / "... off"
        if (preg_match('/^Change\s+to\s+unlimited\s+fuel\s+setting:\s*(?<state>on|off)$/i', $log, $m)) {
            return [
                'type'     => 'unlimited-fuel-toggle',
                'category' => 'systems',
                'phase'    => null,
                'details'  => ['state' => strtolower($m['state']) === 'on'],
            ];
        }

        // "Rule Triggered - {name}[ (Nx)], {p}pts"
        if (preg_match('/^Rule\s+Triggered\s*-\s*(?<name>.*?)(?:\s*\((?<count>\d+)x\))?,\s*(?<points>-?\d+)pts$/i', $log, $m)) {
            $name = trim($m['name']);
            $details = [
                'points' => (int) $m['points'],
                // The optional count group is followed by a group that always
                // matches, so PHP fills it in as '' rather than omitting it.
                'count' => $m['count'] !== '' ? (int) $m['count'] : 1,
            ];

            if ($name !== '') {
                $details['rule_name'] = $name;
            }

            return ['type' => 'rule-violation', 'category' => 'violation', 'phase' => null, 'details' => $details];
        }

        // Generic feature toggle: "{feature} set to on"/"off". Must be tried
        // after parking-brake/gear, which also match this shape.
        if (preg_match('/^(?<feature>.+?)\s+set\s+to\s+(?<state>on|off)$/i', $log, $m)) {
            return [
                'type'     => 'feature-toggle',
                'category' => 'aircraft',
                'phase'    => null,
                'details'  => ['feature' => trim($m['feature']), 'state' => strtolower($m['state']) === 'on'],
            ];
        }

        return ['type' => null, 'category' => 'message', 'phase' => null, 'details' => null];
    }

    private static function phaseOf(string $log): ?string
    {
        $lower = strtolower($log);

        foreach (self::PHASE_MARKERS as $marker) {
            if (str_contains($lower, $marker['needle'])) {
                return $marker['phase']->value;
            }
        }

        return null;
    }
}
