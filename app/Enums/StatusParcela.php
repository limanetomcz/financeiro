<?php

namespace App\Enums;

enum StatusParcela: string
{
    case Aberta = 'aberta';
    case EmCobranca = 'em_cobranca';
    case Paga = 'paga';
    case Cancelada = 'cancelada';
    case Perdida = 'perdida';
}
