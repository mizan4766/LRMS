<?php

return [
    'weekend_days' => [5, 6],                 // Friday, Saturday
    'bigha_in_decimal' => 33,
    'authority_threshold_acre' => 16.50,      // 50 bigha (sec 5(3)): above -> Ministry, below -> Commissioner
    'fiscal_year_start_month' => 7,           // fiscal year July-June (LA-SYL-09/2025-26)
    'objection_days' => 15,                   // sec 5(1): working days after the 4(1) notice
    'payment_gateway' => env('LARMS_PAYMENT_GATEWAY', 'manual'),   // manual | (add your own class in app/Services/Payments)
    'default_password' => env('LARMS_DEFAULT_PASSWORD', 'Land1234'),
    'token_days' => 3,
    'roles' => [
        'rb' => 'প্রত্যাশী সংস্থা', 'dc' => 'জেলা প্রশাসক', 'adc' => 'অতিরিক্ত জেলা প্রশাসক', 'lao' => 'ভূমি অধিগ্রহণ কর্মকর্তা (এলএও)',
        'ac_land' => 'সহকারী কমিশনার (ভূমি)', 'ulao' => 'ইউনিয়ন ভূমি সহকারী কর্মকর্তা', 'kanungo' => 'কানুনগো', 'surveyor' => 'সার্ভেয়ার',
        'commissioner' => 'বিভাগীয় কমিশনার', 'accounts' => 'হিসাব কর্মকর্তা (জেলা)', 'ministry' => 'ভূমি মন্ত্রণালয়', 'citizen' => 'নাগরিক/স্বার্থসংশ্লিষ্ট ব্যক্তি', 'admin' => 'সিস্টেম অ্যাডমিন',
    ],
    'survey_names' => ['CS', 'SA', 'RS', 'BS', 'MUTATION', 'DIARA', 'অন্যান্য'],
    'body_types' => ['govt' => 'সরকারি', 'semi_govt' => 'আধা-সরকারি', 'autonomous' => 'স্বায়ত্তশাসিত', 'private' => 'বেসরকারি'],
    // Rule 31(2): incidental costs added to the estimate
    'incidental_govt_up_to_1cr' => 3.0, 'incidental_govt_above' => 2.0, 'incidental_private' => 10.0,
    'tax_at_source_percent' => 0.0,           // set by Finance rules; applied in the roydad book (schedule 32)
];
