<?php

use App\Http\Controllers\Api\CobrancaController;
use App\Http\Controllers\Api\ContratoController;
use App\Http\Controllers\Api\ElegibilidadeController;
use App\Http\Controllers\Api\FaturaController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ParcelaController;
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

    Route::get('/contratos', [ContratoController::class, 'index']);
    Route::post('/contratos', [ContratoController::class, 'store']);
    Route::get('/contratos/{id}', [ContratoController::class, 'show']);

    Route::post('/cobrancas/consolidadas', [CobrancaController::class, 'consolidar']);
    Route::get('/cobrancas/{id}', [CobrancaController::class, 'show']);
    Route::post('/cobrancas/{id}/liquidar', [CobrancaController::class, 'liquidar']);

    Route::post('/parcelas/abrir-exigiveis', [ParcelaController::class, 'abrirExigiveis']);

    Route::get('/faturas', [FaturaController::class, 'index']);
    Route::post('/faturas', [FaturaController::class, 'store']);
    Route::get('/faturas/{id}', [FaturaController::class, 'show']);
    Route::post('/faturas/{id}/cobranca', [FaturaController::class, 'emitirCobranca']);

    Route::get('/elegibilidade', ElegibilidadeController::class);
});
