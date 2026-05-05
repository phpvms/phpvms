<?php

declare(strict_types=1);

return [
    /*
     * Validation Language Lines
     */

    'accepted'   => ':attribute deve essere accettato.',
    'active_url' => ':attribute non è un URL valido.',
    'after'      => ':attribute deve essere una data successiva al :date.',
    'alpha'      => ':attribute può contenere solo lettere.',
    'alpha_dash' => ':attribute può contenere solo lettere, numeri e trattini.',
    'alpha_num'  => ':attribute può contenere solo lettere e numeri.',
    'array'      => ':attribute deve essere un array.',
    'before'     => ':attribute deve essere una data precedente al :date.',
    'between'    => [
        'numeric' => ':attribute deve essere compreso tra :min e :max.',
        'file'    => ':attribute deve essere compreso tra :min e :max kilobytes.',
        'string'  => ':attribute deve essere compreso tra :min e :max caratteri.',
        'array'   => ':attribute deve essere compreso tra :min e :max elementi.',
    ],
    'boolean'        => ':attribute deve essere true o false.',
    'confirmed'      => ':attribute la conferma non corrisponde.',
    'date'           => ':attribute non è una data valida.',
    'date_format'    => ':attribute non corrisponde al formato :format.',
    'different'      => ':attribute e :other devono essere differenti.',
    'digits'         => ':attribute deve essere di almeno :digits cifre.',
    'digits_between' => ':attribute deve essere compreso tra :min e :max cifre.',
    'dimensions'     => ':attribute ha dimensioni di immagine non valide.',
    'distinct'       => ':attribute il campo è duplicato.',
    'email'          => ':attribute deve essere un indirizzo email valido.',
    'exists'         => 'Il/la :attribute selezionato non è valido.',
    'file'           => ':attribute deve essere un file.',
    'filled'         => '":attribute" è obbligatorio.',
    'image'          => ":attribute deve essere un'immagine.",
    'in'             => 'Il/la :attribute selezionato non è valido.',
    'in_array'       => 'Il campo :attribute non esiste in :other.',
    'integer'        => ':attribute deve essere un intero.',
    'ip'             => ':attribute deve essere un indirizzo IP valido.',
    'json'           => ':attribute deve essere una stringa JSON valida.',
    'max'            => [
        'numeric' => ':attribute non può essere maggiore di :max.',
        'file'    => ':attribute non può essere maggiore di :max kilobytes.',
        'string'  => ':attribute non può essere maggiore di :max caratteri.',
        'array'   => ':attribute non può essere maggiore di :max elementi.',
    ],
    'mimes' => ':attribute deve essere un file di tipo: :values.',
    'min'   => [
        'numeric' => ':attribute deve essere di almeno :min.',
        'file'    => ':attribute deve essere di almeno :min kilobytes.',
        'string'  => ':attribute deve essere di almeno :min caratteri.',
        'array'   => ':attribute deve essere di almeno :min elementi.',
    ],
    'not_in'               => 'Il/la :attribute non è valido/a.',
    'numeric'              => ':attribute deve essere un numero.',
    'present'              => 'Il campo :attribute deve essere presente.',
    'regex'                => 'Il formato del/della :attribute non è valido.',
    'required'             => 'Il campo ":attribute" è obbligatorio.',
    'required_if'          => 'Il campo :attribute è obbligatorio quando :other è :value.',
    'required_unless'      => 'Il campo :attribute è obbligatorio a meno che :other sia compreso tra :values.',
    'required_with'        => 'Il campo :attribute è obbligatorio quando :values è presente.',
    'required_with_all'    => 'Il campo :attribute è obbligatorio quando :values sono presenti.',
    'required_without'     => 'Il campo :attribute è obbligatorio quando :values non è presente.',
    'required_without_all' => 'Il campo :attribute è obbligatorio quando :values non sono presenti.',
    'same'                 => ':attribute e :other devono corrispondere.',
    'size'                 => [
        'numeric' => ':attribute deve essere di :size.',
        'file'    => ':attribute deve essere di :size kilobytes.',
        'string'  => ':attribute deve essere di :size caratteri.',
        'array'   => ':attribute deve contenere :size elementi.',
    ],
    'string'   => ':attribute deve essere una stringa.',
    'timezone' => ':attribute deve essere una zona valida.',
    'unique'   => ':attribute è già stato utilizzato.',
    'url'      => 'Il formato del/della :attribute non è valido.',

    /*
     * Custom Validation Language Lines
     */

    'custom' => [
        'airline_id' => [
            'required' => 'Una compagnia aerea è obbligatoria',
            'exists'   => 'La compagnia aerea non esiste',
        ],
        'aircraft_id' => [
            'required' => 'Un aereo è obbligatorio',
            'exists'   => "L'aereo non esiste",
        ],
        'arr_airport_id' => [
            'required' => 'Un aeroporto di arrivo è obbligatorio',
        ],
        'dpt_airport_id' => [
            'required' => 'Un aeroporto di partenza è obbligatorio',
        ],
        'flight_time' => [
            'required' => 'Il tempo di volo in minuti è obbligatorio',
            'integer'  => 'Il tempo di volo in minuti deve essere un intero',
        ],
        'planned_flight_time' => [
            'required' => 'Il tempo di volo in minuti è obbligatorio',
            'integer'  => 'Il tempo di volo in minuti deve essere un intero',
        ],
        'source_name' => [
            'required' => 'La fonte del PIREP è obbligatoria',
        ],
        'g-recaptcha-response' => [
            'required' => 'Conferma di non essere un robot per favore.',
            'captcha'  => 'Errore captcha! Riprova più tardi o contatta un amministratiore.',
        ],
    ],

    /*
     * Custom Validation Attributes
     */

    'attributes' => [],

];
