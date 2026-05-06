<?php

declare(strict_types=1);

return [
    /*
     * Validation Language Lines
     */

    'accepted'   => 'El :attribute debe ser aceptado.',
    'active_url' => 'El :attribute No es una URL valida.',
    'after'      => 'El :attribute debe ser una fecha después de :date.',
    'alpha'      => 'El :attribute solo puede contener letras.',
    'alpha_dash' => 'El :attribute solo puede contener letras, números, y guiones.',
    'alpha_num'  => 'El :attribute solo puede contener letras y números.',
    'array'      => 'El :attribute debe ser un array.',
    'before'     => 'El :attribute debe ser una fecha antes de :date.',
    'between'    => [
        'numeric' => 'El :attribute debe estar entre :min and :max.',
        'file'    => 'El :attribute debe estar entre :min and :max kilobytes.',
        'string'  => 'El :attribute debe estar entre :min and :max caracteres.',
        'array'   => 'El :attribute debe estar entre :min and :max objetos.',
    ],
    'boolean'        => 'El :attribute campo debe ser verdadero o falso.',
    'confirmed'      => 'El :attribute confirmación no coincide.',
    'date'           => 'El :attribute no es una fecha valida.',
    'date_format'    => 'El :attribute no coincide el formato :format.',
    'different'      => 'El :attribute y :other deben ser diferentes.',
    'digits'         => 'El :attribute debe ser :digits digitos.',
    'digits_between' => 'El :attribute debe estar entre :min and :max digitos.',
    'dimensions'     => 'El :attribute tiene dimensiones de imagen no valida.',
    'distinct'       => 'El :attribute campo tiene un valor duplicado.',
    'email'          => 'El :attribute debe ser un email valido.',
    'exists'         => 'El :attribute seleccionado es invalido.',
    'file'           => 'El :attribute debe ser un archivo.',
    'filled'         => 'El ":attribute" es requerido.',
    'image'          => 'El :attribute debe ser una imagen.',
    'in'             => 'El :attribute seleccionado es invalido.',
    'in_array'       => 'El :attribute campo no existe en :other.',
    'integer'        => 'El :attribute debe ser un integer.',
    'ip'             => 'El :attribute debe ser una dirección IP valida.',
    'json'           => 'El :attribute debe ser un string JSON valido.',
    'max'            => [
        'numeric' => 'El :attribute no puede ser mayor que :max.',
        'file'    => 'El :attribute no puede ser mayor que :max kilobytes.',
        'string'  => 'El :attribute no puede ser mayor que :max caracteres.',
        'array'   => 'El :attribute no puede tener más de :max objetos.',
    ],
    'mimes' => 'El :attribute must be a file of type: :values.',
    'min'   => [
        'numeric' => 'El :attribute debe tener al menos :min.',
        'file'    => 'El :attribute debe tener al menos :min kilobytes.',
        'string'  => 'El :attribute debe tener al menos :min caracteres.',
        'array'   => 'El :attribute debe tener al menos :min objetos.',
    ],
    'not_in'               => 'El :attribute seleccionado es invalido.',
    'numeric'              => 'El :attribute debe ser un número.',
    'present'              => 'El :attribute campo debe estar presente.',
    'regex'                => 'El :attribute formato es invalido.',
    'required'             => 'El ":attribute" campo es requerido.',
    'required_if'          => 'El :attribute campo es requerido cuando :other es :value.',
    'required_unless'      => 'El :attribute campo es requerido a no ser que :other esté en :values.',
    'required_with'        => 'El :attribute campo es requerido cuando :values es presente.',
    'required_with_all'    => 'El :attribute campo es requerido cuando :values es presente.',
    'required_without'     => 'El :attribute campo es requerido cuando :values no esté presente.',
    'required_without_all' => 'El :attribute campo es requerido cuando none of :values are presente.',
    'same'                 => 'El :attribute y :other debe coincidir.',
    'size'                 => [
        'numeric' => 'El :attribute debe ser :size.',
        'file'    => 'El :attribute debe ser :size kilobytes.',
        'string'  => 'El :attribute debe ser :size caracteres.',
        'array'   => 'El :attribute debe contener :size objetos.',
    ],
    'string'   => 'El :attribute debe ser un string.',
    'timezone' => 'El :attribute debe ser una zona valida.',
    'unique'   => 'El :attribute ha sido actualmente usado.',
    'url'      => 'El :attribute es un formato invalido.',

    /*
     * Custom Validation Language Lines
     */

    'custom' => [
        'airline_id' => [
            'required' => 'Una aerolínea es requerida',
            'exists'   => 'La aerolínea no existe',
        ],
        'aircraft_id' => [
            'required' => 'Una aeronave es requerido',
            'exists'   => 'La aeronave no existe',
        ],
        'arr_airport_id' => [
            'required' => 'Un aeropuerto de llegada es requerido',
        ],
        'dpt_airport_id' => [
            'required' => 'Un aeropuerto de salida es requerido',
        ],
        'flight_time' => [
            'required' => 'Tiempo de vuelo, en minutos, es requerido',
            'integer'  => 'Tiempo de vuelo, en minutos, es requerido',
        ],
        'planned_flight_time' => [
            'required' => 'Tiempo de vuelo, en minutos, es requerido',
            'integer'  => 'Tiempo de vuelo, en minutos, es requerido',
        ],
        'source_name' => [
            'required' => 'Origen del PIREP es requerido',
        ],
        'g-recaptcha-response' => [
            'required' => 'Por favor verifica que no eres un robot.',
            'captcha'  => '¡Error de CAPTCHA! intente de nuevo más tarde o póngase en contacto con el administrador del sitio.',
        ],
    ],

    /*
     * Custom Validation Attributes
     */

    'attributes' => [],

];
