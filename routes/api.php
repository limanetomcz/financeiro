<?php

use App\Http\Controllers\Api\CobrancaController;
use App\Http\Controllers\Api\ContratoController;
use App\Http\Controllers\Api\ElegibilidadeController;
use App\Http\Controllers\Api\FaturaController;
use App\Http\Controllers\Api\LabController;
use App\Http\Controllers\Api\LocalPagamentoController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ParcelaController;
use App\Http\Controllers\Api\RemessaController;
use App\Http\Controllers\Api\RetornoBancarioController;
use App\Http\Controllers\Api\BoletoController;
use App\Http\Controllers\Api\SituacaoFinanceiraController;
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
    Route::get('/cobrancas/{id}/boleto.pdf', [BoletoController::class, 'pdf']);

    Route::get('/locais-pagamento', [LocalPagamentoController::class, 'index']);
    Route::get('/locais-pagamento/resolver', [LocalPagamentoController::class, 'resolver']);
    Route::post('/locais-pagamento', [LocalPagamentoController::class, 'store']);

    /** Lab / testes — limpar / remessa / registrar boletos em lote. */
    Route::delete('/lab/financeiro', [LabController::class, 'limparFinanceiro']);
    Route::delete('/lab/remessas/{id}', [LabController::class, 'apagarRemessa']);
    Route::post('/lab/parcelas/abrir-todas', [LabController::class, 'abrirTodasParcelas']);
    Route::post('/lab/registrar-boletos', [LabController::class, 'registrarBoletos']);

    Route::post('/parcelas/abrir-exigiveis', [ParcelaController::class, 'abrirExigiveis']);
    Route::get('/parcelas', [ParcelaController::class, 'index']);
    Route::post('/parcelas/{id}/calcular-juros', [ParcelaController::class, 'calcularJuros']);
    Route::post('/parcelas/{id}/baixar', [ParcelaController::class, 'baixar']);
    Route::post('/parcelas/{id}/retirar-baixa', [ParcelaController::class, 'retirarBaixa']);

    Route::get('/faturas', [FaturaController::class, 'index']);
    Route::post('/faturas', [FaturaController::class, 'store']);
    Route::get('/faturas/{id}', [FaturaController::class, 'show']);
    Route::post('/faturas/{id}/cobranca', [FaturaController::class, 'emitirCobranca']);

    Route::get('/elegibilidade', ElegibilidadeController::class);

    /** Resumo financeiro do contratante (Sigoweb). */
    Route::get('/financeiro', SituacaoFinanceiraController::class);

    /** Remessa CNAB (fila bancario; use sincrono=1 só em testes/piloto). */
    Route::get('/remessas', [RemessaController::class, 'index']);
    Route::post('/remessas', [RemessaController::class, 'store']);
    Route::get('/remessas/{id}', [RemessaController::class, 'show']);
    Route::get('/remessas/{id}/download', [RemessaController::class, 'download']);

    /** Retorno CNAB Sicredi (.CRT). */
    Route::get('/retornos', [RetornoBancarioController::class, 'index']);
    Route::post('/retornos', [RetornoBancarioController::class, 'store']);
    Route::get('/retornos/{id}', [RetornoBancarioController::class, 'show']);
});
