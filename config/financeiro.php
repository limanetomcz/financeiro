<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Filas Redis (multi-tenant)
    |--------------------------------------------------------------------------
    |
    | Um Redis compartilhado para até N cooperativas. Jobs sempre carregam
    | cliente_id e restauram ClienteContext no worker.
    |
    */
    'queues' => [
        'default' => env('FINANCEIRO_QUEUE_DEFAULT', 'default'),
        'cobranca' => env('FINANCEIRO_QUEUE_COBRANCA', 'cobranca'),
        'bancario' => env('FINANCEIRO_QUEUE_BANCARIO', 'bancario'),
    ],

    /*
    | Filas nomeadas por cooperativa (opcional).
    | false = todos os tenants na mesma fila (mais simples).
    | true  = queue "cliente-{codigo}" para isolamento/prioridade.
    */
    'queue_por_cliente' => (bool) env('FINANCEIRO_QUEUE_POR_CLIENTE', false),

    /*
    | Limpeza de contratante no lab (DELETE /api/v1/lab/financeiro).
    | Manter false fora de desenvolvimento.
    */
    'lab_limpeza_habilitada' => (bool) env('FINANCEIRO_LAB_LIMPEZA', env('APP_ENV') === 'local'),

    /*
    | Enviar eventos de baixa/estorno ao Fusca.
    | Manter false enquanto testamos (não sujar o Fusca).
    */
    'auditoria_fusca_habilitada' => (bool) env('FINANCEIRO_AUDITORIA_FUSCA', false),
];
