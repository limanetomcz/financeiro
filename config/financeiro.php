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
];
