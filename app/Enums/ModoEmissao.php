<?php

namespace App\Enums;

enum ModoEmissao: string
{
    /** Todas as parcelas entram no CR na data de adesão (emitida_em = hoje). */
    case Imediata = 'imediata';

    /** Cada parcela só entra no CR no seu mês (emitida_em escalonado). */
    case Escalonada = 'escalonada';
}
