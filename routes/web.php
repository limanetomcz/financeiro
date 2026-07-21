<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'financeiro',
        'docs' => '/docs via README.md',
        'api' => url('/api/v1/health'),
    ]);
});
