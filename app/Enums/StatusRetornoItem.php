<?php

namespace App\Enums;

enum StatusRetornoItem: string
{
    case Pendente = 'pendente';
    case Processado = 'processado';
    case Ignorado = 'ignorado';
    case Erro = 'erro';
}
