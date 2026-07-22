<?php

namespace App\Enums;

enum StatusRemessa: string
{
    case Pendente = 'pendente';
    case Processando = 'processando';
    case Concluida = 'concluida';
    case Vazia = 'vazia';
    case Falha = 'falha';
}
