<?php

namespace App\Bancario\Sicredi;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\Support\CalculoDvModulo11;
use App\Bancario\Support\TextoCnab;
use App\Exceptions\DominioException;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Código de barras / linha digitável Sicredi (banco 748), conforme manual de cobrança.
 *
 * Código de barras (44):
 * 1-3 banco | 4 moeda | 5 DV geral | 6-9 fator | 10-19 valor | 20-44 campo livre
 *
 * Campo livre (25):
 * tipo cobrança (1) + carteira (1) + nosso nº (9) + agência (4) + posto (2)
 * + cedente (5) + indicador valor (1) + filler 0 + DV campo livre
 */
final class SicrediCodigoBarrasBoleto
{
    public function __construct(
        private readonly ContaCobranca $conta,
        private readonly string $nossoNumero,
        private readonly CarbonInterface $vencimento,
        private readonly float $valor,
        private readonly bool $comRegistro = true,
    ) {}

    /**
     * @return array{
     *   codigo_barras: string,
     *   linha_digitavel: string,
     *   linha_digitavel_formatada: string,
     *   fator_vencimento: string,
     *   campo_livre: string,
     *   nosso_numero_exibicao: string,
     *   agencia_codigo_beneficiario: string
     * }
     */
    public function montar(): array
    {
        $this->conta->validar();

        if ($this->conta->codigoBanco !== '748') {
            throw new DominioException('Geração de boleto PDF implementada apenas para Sicredi (748).');
        }

        $campoLivre = $this->campoLivre();
        $fator = $this->fatorVencimento($this->vencimento);
        $valor = TextoCnab::lpad((int) round($this->valor * 100), 10);

        // Sem DV geral: banco + moeda + fator + valor + campo livre (43 posições com buraco no DV)
        $semDv = '748'
            .'9'
            .$fator
            .$valor
            .$campoLivre;

        // DV geral (posição 5): módulo 11 pesos 2..9; resto 0 ou 1 → DV 1 (padrão Febraban boletos)
        $dvGeral = $this->dvGeralCodigoBarras($semDv);
        $codigoBarras = substr($semDv, 0, 4).$dvGeral.substr($semDv, 4);

        $linha = $this->linhaDigitavel($codigoBarras);

        return [
            'codigo_barras' => $codigoBarras,
            'linha_digitavel' => $linha['raw'],
            'linha_digitavel_formatada' => $linha['formatada'],
            'fator_vencimento' => $fator,
            'campo_livre' => $campoLivre,
            'nosso_numero_exibicao' => $this->nossoNumeroExibicao(),
            'agencia_codigo_beneficiario' => $this->agenciaCodigoBeneficiario(),
        ];
    }

    private function campoLivre(): string
    {
        $tipoCobranca = $this->comRegistro ? '1' : '3';
        $carteira = TextoCnab::lpad(TextoCnab::apenasDigitos($this->conta->carteira) ?: '1', 1);
        $nosso = $this->nossoNumeroCodigoBarras();
        $agencia = TextoCnab::lpad(TextoCnab::apenasDigitos($this->conta->agencia), 4);
        $posto = TextoCnab::lpad(TextoCnab::apenasDigitos($this->conta->posto), 2);
        $cedente = TextoCnab::lpad(TextoCnab::apenasDigitos($this->conta->codigoCedente), 5);
        $temValor = $this->valor > 0 ? '1' : '0';

        $base = $tipoCobranca.$carteira.$nosso.$agencia.$posto.$cedente.$temValor.'0';
        $dv = CalculoDvModulo11::digito($base);

        return $base.$dv;
    }

    /**
     * Nosso número com 9 dígitos no campo livre (YY + sequência + DV, ou pad à esquerda).
     */
    private function nossoNumeroCodigoBarras(): string
    {
        $n = TextoCnab::apenasDigitos($this->nossoNumero);

        if (strlen($n) > 9) {
            $n = substr($n, -9);
        }

        return TextoCnab::lpad($n, 9);
    }

    private function nossoNumeroExibicao(): string
    {
        $n = TextoCnab::apenasDigitos($this->nossoNumero);

        // Exibição legada Seridó costuma omitir o ano (7 dígitos) quando registro tem 9.
        if (strlen($n) === 9) {
            return substr($n, 2);
        }

        return $n;
    }

    private function agenciaCodigoBeneficiario(): string
    {
        $agencia = TextoCnab::lpad(TextoCnab::apenasDigitos($this->conta->agencia), 4);
        $posto = TextoCnab::lpad(TextoCnab::apenasDigitos($this->conta->posto), 2);
        $cedente = TextoCnab::lpad(TextoCnab::apenasDigitos($this->conta->codigoCedente), 5);

        return $agencia.'.'.$posto.'.'.$cedente;
    }

    private function fatorVencimento(CarbonInterface $vencimento): string
    {
        // Febraban: base 07/10/1997; a partir de 22/02/2025 reinicia em 1000.
        $base = Carbon::parse('1997-10-07')->startOfDay();
        $dias = $base->diffInDays($vencimento->copy()->startOfDay());

        if ($dias > 9999) {
            $dias = (($dias - 1000) % 9000) + 1000;
        }

        return TextoCnab::lpad((int) $dias, 4);
    }

    private function dvGeralCodigoBarras(string $codigoSemDv43): string
    {
        // Entrada: 43 dígitos (posições 1-4 + 6-44). Calcula DV da posição 5.
        $soma = 0;
        $peso = 2;

        for ($i = strlen($codigoSemDv43) - 1; $i >= 0; $i--) {
            $soma += ((int) $codigoSemDv43[$i]) * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }

        $resto = $soma % 11;
        $dv = 11 - $resto;

        if ($dv === 0 || $dv === 10 || $dv === 11) {
            return '1';
        }

        return (string) $dv;
    }

    /**
     * @return array{raw: string, formatada: string}
     */
    private function linhaDigitavel(string $codigoBarras): array
    {
        $campoLivre = substr($codigoBarras, 19, 25);

        $c1 = substr($codigoBarras, 0, 4).substr($campoLivre, 0, 5);
        $c1 .= $this->dvModulo10($c1);

        $c2 = substr($campoLivre, 5, 10);
        $c2 .= $this->dvModulo10($c2);

        $c3 = substr($campoLivre, 15, 10);
        $c3 .= $this->dvModulo10($c3);

        $c4 = substr($codigoBarras, 4, 1);
        $c5 = substr($codigoBarras, 5, 14);

        $raw = $c1.$c2.$c3.$c4.$c5;
        $formatada = substr($c1, 0, 5).'.'.substr($c1, 5, 5)
            .' '.substr($c2, 0, 5).'.'.substr($c2, 5, 6)
            .' '.substr($c3, 0, 5).'.'.substr($c3, 5, 6)
            .' '.$c4
            .' '.$c5;

        return ['raw' => $raw, 'formatada' => $formatada];
    }

    private function dvModulo10(string $numero): string
    {
        $soma = 0;
        $peso = 2;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $prod = ((int) $numero[$i]) * $peso;
            if ($prod > 9) {
                $prod = intdiv($prod, 10) + ($prod % 10);
            }
            $soma += $prod;
            $peso = $peso === 2 ? 1 : 2;
        }

        $resto = $soma % 10;

        return $resto === 0 ? '0' : (string) (10 - $resto);
    }
}
