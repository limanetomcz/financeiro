<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Middleware\AuthenticateSigoweb;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'service' => 'financeiro',
    ]);
});

Route::middleware([AuthenticateSigoweb::class])->group(function () {
    Route::get('/me', MeController::class);
});
