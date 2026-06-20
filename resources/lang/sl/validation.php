<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute mora biti sprejet.',
    'active_url' => ':attribute ni veljaven URL.',
    'after' => ':attribute mora biti datum po :date.',
    'after_or_equal' => ':attribute mora biti datum po ali enak :date.',
    'alpha' => ':attribute lahko vsebuje samo črke.',
    'alpha_dash' => ':attribute lahko vsebuje samo črke, številke in vezaje.',
    'alpha_num' => ':attribute lahko vsebuje samo črke in številke.',
    'array' => ':attribute mora biti polje.',
    'before' => ':attribute mora biti datum pred :date.',
    'before_or_equal' => ':attribute mora biti datum pred ali enak :date.',
    'between' => [
        'numeric' => ':attribute mora biti med :min in :max.',
        'file' => ':attribute mora biti med :min in :max kilobajti.',
        'string' => ':attribute mora biti med :min in :max znaki.',
        'array' => ':attribute mora imeti med :min in :max elementi.',
    ],
    'boolean' => 'Polje :attribute mora biti resnično ali neresnično.',
    'confirmed' => 'Potrditev :attribute se ne ujema.',
    'country' => 'Polje :attribute ni veljavna država.',
    'date' => ':attribute ni veljaven datum.',
    'date_equals' => ':attribute mora biti datum enak :date.',
    'date_format' => ':attribute se ne ujema z formatom :format.',
    'different' => ':attribute in :other morata biti različna.',
    'digits' => ':attribute mora biti :digits številk.',
    'digits_between' => ':attribute mora biti med :min in :max številkami.',
    'distinct' => 'Polje :attribute ima podvojeno vrednost.',
    'email' => ':attribute mora biti veljaven e-poštni naslov.',
    'exists' => 'Izbrani :attribute ni veljaven.',
    'filled' => 'Polje :attribute je obvezno.',
    'gt' => [
        'numeric' => ':attribute mora biti večji od :value.',
        'file' => ':attribute mora biti večji od :value kilobajtov.',
        'string' => ':attribute mora biti večji od :value znakov.',
        'array' => ':attribute mora imeti več kot :value elementov.',
    ],
    'gte' => [
        'numeric' => ':attribute mora biti večji ali enak :value.',
        'file' => ':attribute mora biti večji ali enak :value kilobajtov.',
        'string' => ':attribute mora biti večji ali enak :value znakov.',
        'array' => ':attribute mora imeti :value ali več elementov.',
    ],
    'image' => ':attribute mora biti slika.',
    'in' => 'Izbrani :attribute ni veljaven.',
    'in_array' => 'Polje :attribute ne obstaja v :other.',
    'integer' => ':attribute mora biti celo število.',
    'ip' => ':attribute mora biti veljaven IP naslov.',
    'ipv4' => ':attribute mora biti veljaven IPv4 naslov.',
    'ipv6' => ':attribute mora biti veljaven IPv6 naslov.',
    'json' => ':attribute mora biti veljaven JSON niz.',
    'lt' => [
        'numeric' => ':attribute mora biti manjši od :value.',
        'file' => ':attribute mora biti manjši od :value kilobajtov.',
        'string' => ':attribute mora biti manjši od :value znakov.',
        'array' => ':attribute mora imeti manj kot :value elementov.',
    ],
    'lte' => [
        'numeric' => ':attribute mora biti manjši ali enak :value.',
        'file' => ':attribute mora biti manjši ali enak :value kilobajtov.',
        'string' => ':attribute mora biti manjši ali enak :value znakov.',
        'array'   => ':attribute ne sme vsebovati več kot :value elementov.',
    ],
    'max' => [
        'numeric' => ':attribute ne sme biti večji od :max.',
        'file' => ':attribute ne sme biti večji od :max kilobajtov.',
        'string' => ':attribute ne sme biti daljši od :max znakov.',
        'array' => ':attribute ne sme imeti več kot :max elementov.',
    ],
    'mimes' => ':attribute mora biti datoteka tipa: :values.',
    'mimetypes' => ':attribute mora biti datoteka tipa: :values.',
    'min' => [
        'numeric' => ':attribute mora biti vsaj :min.',
        'file' => ':attribute mora biti velik vsaj :min kilobajtov.',
        'string' => ':attribute mora biti dolg vsaj :min znakov.',
        'array' => ':attribute mora imeti vsaj :min elementov.',
    ],
    'not_in' => 'Izbrani :attribute ni veljaven.',
    'not_regex' => 'Format :attribute ni veljaven.',
    'numeric' => ':attribute mora biti številka.',
    'present' => 'Polje :attribute mora biti prisotno.',
    'regex' => 'Format :attribute ni veljaven.',
    'required' => 'Polje :attribute je obvezno.',
    'required_if' => 'Polje :attribute je obvezno, ko je :other :value.',
    'required_unless' => 'Polje :attribute je obvezno, razen če je :other v :values.',
    'required_with' => 'Polje :attribute je obvezno, ko je :values prisotno.',
    'required_with_all' => 'Polje :attribute je obvezno, ko so :values prisotni.',
    'required_without' => 'Polje :attribute je obvezno, ko :values ni prisoten.',
    'required_without_all' => 'Polje :attribute je obvezno, ko nobeden od :values ni prisoten.',
    'same' => ':attribute in :other se morata ujemati.',
    'size' => [
        'numeric' => ':attribute mora biti :size.',
        'file' => ':attribute mora biti :size kilobajtov.',
        'string' => ':attribute mora biti :size znakov.',
        'array' => ':attribute mora vsebovati :size elementov.',
    ],
    'starts_with' => ':attribute se mora začeti z enim od naslednjih: :values',
    'state' => 'To stanje ni veljavno za navedeno državo.',
    'string' => ':attribute mora biti niz.',
    'timezone' => ':attribute mora biti veljavna cona.',
    'unique' => ':attribute je že zaseden.',
    'uploaded' => 'Nalaganje :attribute ni uspelo.',
    'url' => 'Format :attribute ni veljaven.',
    'vat_id' => 'Ta identifikacijska številka za DDV ni veljavna.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
