<?php

namespace App\Enums;

enum OperacaoRemessa: string
{
    /** Entrada de título */
    case Entrada = '01';

    /** Alteração de vencimento / dados */
    case Alteracao = '06';
}
