<?php

namespace App\Enums;

enum ModalidadeTaxaLocal: string
{
    case Debito = 'debito';
    case CreditoAvista = 'credito_avista';
    case Credito1a6 = 'credito_1_6';
    case Credito2a6 = 'credito_2_6';
    case Credito7a12 = 'credito_7_12';
}
