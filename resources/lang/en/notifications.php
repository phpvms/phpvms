<?php

declare(strict_types=1);

return [
    'discord' => [
        /*
         * Broadcast announcements address a Discord channel rather than a
         * request, so these resolve against the application locale.
         */
        'fields' => [
            'dep_airport'  => 'Dep.Airport',
            'arr_airport'  => 'Arr.Airport',
            'equipment'    => 'Equipment',
            'flight_time'  => 'Flight Time',
            'distance'     => 'Distance',
            'landing_rate' => 'Landing Rate',
        ],

        'pirep_filed'        => 'Flight :ident Filed',
        'pirep_filed_status' => 'Flight :ident :verb',
        'pirep_diverted'     => 'Flight :ident Diverted',

        'diverted' => [
            'pilot_in_command'   => 'Pilot In Command: :pilot',
            'flight_no'          => 'Flight #',
            'orig'               => 'Orig',
            'dest'               => 'Dest',
            'equipment'          => 'Equipment',
            'diverted'           => 'Diverted',
            'reason'             => 'Reason',
            'not_reported'       => 'Not Reported',
            'reason_crashed'     => 'Crashed Near :airport',
            'reason_operational' => 'Operational',
        ],
        'flight_by'        => 'Flight by :mention',
        'award_received'   => 'Received award :award',
        'awarded_to'       => 'Awarded to :mention',
        'rank_changed'     => 'Rank changed :rank',
        'rank_changed_for' => 'Rank changed for :mention',
        'news'             => 'News: :subject',
        'read_more'        => 'Read more',
        'user_registered'  => 'New User Registered: :ident',

        /*
         * Verbs completing "Flight <ident> ...", keyed by PirepStatus value.
         */
        'status' => [
            'INI' => 'is initialized',
            'SCH' => 'is scheduled',
            'BST' => 'is boarding',
            'RDT' => 'is ready for start',
            'PBT' => 'is pushing back',
            'OFB' => 'has departed',
            'DIR' => 'is ready for de-icing',
            'DIC' => 'is de-icing',
            'GRT' => 'on ground return',
            'TXI' => 'is taxiing',
            'TOF' => 'has taken off',
            'ICL' => 'in initial climb',
            'TKO' => 'is enroute',
            'ENR' => 'is enroute',
            'DV'  => 'has diverted',
            'TEN' => 'on approach',
            'APR' => 'on approach',
            'FIN' => 'on final approach',
            'LDG' => 'is landing',
            'LAN' => 'has landed',
            'ONB' => 'has arrived',
            'ARR' => 'has arrived',
            'DX'  => 'is cancelled',
            'PSD' => 'is paused',
            'EMG' => 'in emergency descent',
        ],
    ],
];
