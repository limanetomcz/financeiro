<?php

namespace App\Enums;

enum PerfilPagamento: string
{
    /** 12x (ou N) no boleto — inadimplência mensal. */
    case BoletoParcelado = 'boleto_parcelado';

    /** N vezes no cartão — emissão imediata ou escalonada. */
    case CartaoParcelado = 'cartao_parcelado';

    /** Anual à vista — se pago, só volta a dever na renovação. */
    case AVista = 'a_vista';
}
