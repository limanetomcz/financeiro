<?php

namespace App\Enums;

enum StatusFatura: string
{
    case Rascunho = 'rascunho';
    case Aberta = 'aberta';
    case EmCobranca = 'em_cobranca';
    case Paga = 'paga';
    case Cancelada = 'cancelada';
}
