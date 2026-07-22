<?php

namespace App\Enums;

enum TipoLocalPagamento: string
{
    case Caixa = 'caixa';
    case Banco = 'banco';
    case Pix = 'pix';
    case Cartao = 'cartao';
}
