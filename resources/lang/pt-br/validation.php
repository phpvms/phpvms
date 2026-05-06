<?php

declare(strict_types=1);

return [
    /*
     * Validation Language Lines
     */

    'accepted'   => 'O :attribute deve ser aceito.',
    'active_url' => 'O :attribute não é uma URL válida.',
    'after'      => 'O :attribute deve ser uma data depois de :date.',
    'alpha'      => 'O :attribute pode conter apenas letras.',
    'alpha_dash' => 'O :attribute pode conter apenas letras, números e traços.',
    'alpha_num'  => 'O :attribute pode conter apenas letras e números',
    'array'      => 'O :attribute deve ser uma array.',
    'before'     => 'O :attribute deve ser uma data antes de :date.',
    'between'    => [
        'numeric' => 'O :attribute deve estar entre :min e :max.',
        'file'    => 'O :attribute deve estar entre :min e :max kilobytes.',
        'string'  => 'O :attribute deve estar entre :min e :max characters.',
        'array'   => 'O :attribute deve ter entre :min e :max items.',
    ],
    'boolean'        => 'O :attribute deve ser verdadeiro ou falso.',
    'confirmed'      => 'A confirmação :attribute não corresponde.',
    'date'           => 'O :attribute não é uma data válida.',
    'date_format'    => 'O :attribute não corresponde ao formato :format.',
    'different'      => 'O :attribute e :other devem ser diferentes.',
    'digits'         => 'O :attribute deve ser :digits dígitos.',
    'digits_between' => 'O :attribute deve estar entre :min e :max dígitos.',
    'dimensions'     => 'O :attribute tem dimensões de imagem inválidas.',
    'distinct'       => 'O campo :attribute tem um valor duplicado.',
    'email'          => 'O :attribute deve ser um endereço de e-mail válido.',
    'exists'         => 'O :attribute selecionado é inválido.',
    'file'           => 'O :attribute deve ser um arquivo.',
    'filled'         => 'O ":attribute" é necessário.',
    'image'          => 'O :attribute deve ser uma imagem.',
    'in'             => 'O :attribute selecionado é inválido.',
    'in_array'       => 'O campo :attribute não existe em :other.',
    'integer'        => 'O :attribute deve ser um número inteiro.',
    'ip'             => 'O :attribute deve ser um endereço IP válido.',
    'json'           => 'O :attribute deve ser uma sequência JSON válida.',
    'max'            => [
        'numeric' => 'O :attribute não pode ser maior que :max.',
        'file'    => 'O :attribute não pode ser maior que :max kilobytes.',
        'string'  => 'O :attribute não pode ser maior que :max characters.',
        'array'   => 'O :attribute pode não ter mais do que :max itens.',
    ],
    'mimes' => 'O :attribute deve ser um arquivo do tipo: :values.',
    'min'   => [
        'numeric' => 'O :attribute deve ser pelo menos :min.',
        'file'    => 'O :attribute deve ser pelo menos :min kilobytes.',
        'string'  => 'O :attribute deve ser pelo menos :min characters.',
        'array'   => 'O :attribute deve ter pelo menos :min itens.',
    ],
    'not_in'               => 'O :attribute selecionado é inválido.',
    'numeric'              => 'O :attribute deve ser um número.',
    'present'              => 'O campo :attribute deve estar presente.',
    'regex'                => 'O format :attribute é inválido.',
    'required'             => 'O campo ":attribute" é necessário.',
    'required_if'          => 'O campo :attribute é necessário quando :other é :value.',
    'required_unless'      => 'O campo :attribute é necessário a menos que :other esteja em :values.',
    'required_with'        => 'O campo :attribute é necessário quando :values está presente.',
    'required_with_all'    => 'O campo :attribute é necessário quando :values está presente.',
    'required_without'     => 'O campo :attribute é necessário quando :values não está presente.',
    'required_without_all' => 'O campo :attribute é necessário quando nenhum dos :values estão presentes.',
    'same'                 => 'O :attribute e :other devem combinar.',
    'size'                 => [
        'numeric' => 'O :attribute deve ser :size.',
        'file'    => 'O :attribute deve ser :size kilobytes.',
        'string'  => 'O :attribute deve ser :size characters.',
        'array'   => 'O :attribute deve ter :size itens.',
    ],
    'string'   => 'O :attribute deve ser uma string.',
    'timezone' => 'O :attribute deve ser uma zona válida.',
    'unique'   => 'O :attribute já foi tomado.',
    'url'      => 'O formato :attribute é inválido.',

    /*
     * Custom Validation Language Lines
     */

    'custom' => [
        'airline_id' => [
            'required' => 'É necessária uma companhia aérea',
            'exists'   => 'A companhia aérea não existe.',
        ],
        'aircraft_id' => [
            'required' => 'É necessária uma aeronave',
            'exists'   => 'A aeronave aérea não existe.',
        ],
        'arr_airport_id' => [
            'required' => 'É necessário um aeroporto de chegada',
        ],
        'dpt_airport_id' => [
            'required' => 'É necessário um aeroporto de saída',
        ],
        'flight_time' => [
            'required' => 'O tempo de vôo, em minutos, é necessário',
            'integer'  => 'O tempo de vôo, em minutos, é necessário',
        ],
        'planned_flight_time' => [
            'required' => 'O tempo de vôo, em minutos, é necessário',
            'integer'  => 'O tempo de vôo, em minutos, é necessário',
        ],
        'source_name' => [
            'required' => 'Fonte PIREP é necessária',
        ],
        'g-recaptcha-response' => [
            'required' => 'Por favor, verifique se você não é um robô.',
            'captcha'  => 'Erro CAPTCHA! Tente novamente mais tarde ou entre em contato com o administrador do site.',
        ],
    ],

    /*
     * Custom Validation Attributes
     */

    'attributes' => [],

];
