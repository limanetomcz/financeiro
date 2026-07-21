<?php

namespace App\Enums;

enum StatusCobranca: string
{
    case Aberta = 'aberta';
    case Paga = 'paga';
    case Cancelada = 'cancelada';
    case Expirada = 'expirada';
}
