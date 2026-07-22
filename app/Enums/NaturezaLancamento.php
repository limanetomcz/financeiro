<?php

namespace App\Enums;

enum NaturezaLancamento: string
{
    /** Compõe o bruto (ex.: soma das parcelas dos beneficiários). */
    case Base = 'base';

    /** Abate do líquido (IR, ISS, PIS, COFINS…). */
    case Retencao = 'retencao';

    /** Soma ao líquido além do bruto. */
    case Acrescimo = 'acrescimo';

    /** Não altera totais (só informativo). */
    case Informativo = 'informativo';
}
