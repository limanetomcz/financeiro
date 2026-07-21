<?php

namespace App\Enums;

enum StatusParcela: string
{
    /** Ainda não exigível no CR (mês futuro). */
    case Prevista = 'prevista';
    case Aberta = 'aberta';
    case EmCobranca = 'em_cobranca';
    case Paga = 'paga';
    case Cancelada = 'cancelada';
    case Perdida = 'perdida';
}
