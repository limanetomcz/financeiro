<?php

namespace App\Enums;

enum StatusContrato: string
{
    case Rascunho = 'rascunho';
    case Ativo = 'ativo';
    case Suspenso = 'suspenso';
    case Encerrado = 'encerrado';
    case Cancelado = 'cancelado';
}
