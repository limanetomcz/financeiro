<?php

namespace App\Enums;

enum StatusRetornoBancario: string
{
    case Pendente = 'pendente';
    case Processando = 'processando';
    case Concluido = 'concluido';
    case Parcial = 'parcial';
    case Falha = 'falha';
}
