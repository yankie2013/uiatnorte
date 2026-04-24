<?php

return [
    'seeker' => [
        'base_url' => getenv('SEEKER_BASE_URL') ?: 'https://seeker.red',
        'token' => getenv('SEEKER_TOKEN') ?: '',
        'token_dni' => getenv('SEEKER_TOKEN_DNI') ?: (getenv('SEEKER_TOKEN') ?: ''),
        'token_vehiculo' => getenv('SEEKER_TOKEN_VEHICULO') ?: (getenv('SEEKER_TOKEN') ?: ''),
        'dni_url' => getenv('SEEKER_DNI_URL') ?: 'https://seeker.red/personas/apiPremium/dni',
        'placa_url' => getenv('SEEKER_PLACA_URL') ?: 'https://seeker.red/vehiculos/api_newPlacas',
    ],
    'google_maps' => [
        'js_api_key' => getenv('GOOGLE_MAPS_JS_API_KEY')
            ?: (getenv('GOOGLE_MAPS_API_KEY')
                ?: 'AIzaSyBBnJS7WFYPLcroPFi-l0felTh2UW_QR4Q'),
    ],
];
