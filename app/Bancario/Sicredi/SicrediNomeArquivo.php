<?php

namespace App\Bancario\Sicredi;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\Support\TextoCnab;
use App\Contracts\Bancario\RemessaNomeArquivoInterface;
use App\Models\Remessa;
use Carbon\CarbonInterface;

/**
 * Convenção legado Sicredi: {cedente5}{mês especial}{dia}.CRM
 * Out/Nov/Dez → O/N/D
 */
class SicrediNomeArquivo implements RemessaNomeArquivoInterface
{
    public function nomear(Remessa $remessa, ContaCobranca $conta, CarbonInterface $quando): string
    {
        $mes = (int) $quando->format('n');
        $mesFmt = match (true) {
            $mes === 10 => 'O',
            $mes === 11 => 'N',
            $mes === 12 => 'D',
            default => (string) $mes,
        };

        $mdd = $mesFmt.TextoCnab::lpad((int) $quando->format('j'), 2);
        $cedente = TextoCnab::lpad(TextoCnab::apenasDigitos($conta->codigoCedente), 5);

        return "{$cedente}{$mdd}.CRM";
    }
}
