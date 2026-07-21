<?php

return [
    'jwt_secret' => env('SIGOWEB_JWT_SECRET'),
    'jwt_algo' => env('SIGOWEB_JWT_ALGO', 'HS256'),
];
