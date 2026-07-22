<?php

namespace Tests\Support;

/**
 * Monta linhas CNAB 240 Sicredi com as mesmas posições do parser legado.
 */
final class SicrediRetornoCnab240Fixture
{
    public static function arquivoComOcorrencias(array $ocorrencias): string
    {
        $linhas = [];
        $linhas[] = self::headerArquivo();
        $linhas[] = self::headerLote();

        $seq = 1;
        foreach ($ocorrencias as $oc) {
            $linhas[] = self::segmentoT(
                seq: $seq++,
                codigoMovimento: $oc['codigo'],
                nossoNumero: $oc['nosso_numero'],
                numeroRegistro: $oc['numero_registro'] ?? substr(preg_replace('/\D/', '', $oc['nosso_numero']), 0, 9),
                vencimento: $oc['vencimento'] ?? '10042026',
            );
            if (($oc['com_u'] ?? true) === true) {
                $linhas[] = self::segmentoU(
                    seq: $seq++,
                    valorPago: $oc['valor_pago'] ?? 10.0,
                    pagoEm: $oc['pago_em'] ?? '15032026',
                );
            }
        }

        $linhas[] = self::trailerLote(count($linhas));
        $linhas[] = self::trailerArquivo(count($linhas) + 1);

        return implode("\r\n", $linhas)."\r\n";
    }

    public static function headerArquivo(): string
    {
        return self::blank(240, [
            0 => '748',
            3 => '0000',
            7 => '0',
        ]);
    }

    public static function headerLote(): string
    {
        return self::blank(240, [
            0 => '748',
            3 => '0001',
            7 => '1',
        ]);
    }

    public static function trailerLote(int $qtd): string
    {
        return self::blank(240, [
            0 => '748',
            3 => '0001',
            7 => '5',
        ]);
    }

    public static function trailerArquivo(int $qtd): string
    {
        return self::blank(240, [
            0 => '748',
            3 => '9999',
            7 => '9',
        ]);
    }

    public static function segmentoT(
        int $seq,
        string $codigoMovimento,
        string $nossoNumero,
        string $numeroRegistro,
        string $vencimento,
    ): string {
        return self::blank(240, [
            0 => '748',
            3 => '0001',
            7 => '3',
            8 => str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
            13 => 'T',
            15 => str_pad($codigoMovimento, 2, '0', STR_PAD_LEFT),
            37 => str_pad(substr($numeroRegistro, 0, 9), 9, '0', STR_PAD_LEFT),
            58 => str_pad(substr($nossoNumero, 0, 15), 15, ' ', STR_PAD_RIGHT),
            73 => $vencimento,
            213 => '00',
        ]);
    }

    public static function segmentoU(int $seq, float $valorPago, string $pagoEm): string
    {
        $centavos = str_pad((string) (int) round($valorPago * 100), 15, '0', STR_PAD_LEFT);

        return self::blank(240, [
            0 => '748',
            3 => '0001',
            7 => '3',
            8 => str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
            13 => 'U',
            77 => $centavos,
            137 => $pagoEm,
        ]);
    }

    /**
     * @param  array<int, string>  $campos  offset => valor
     */
    private static function blank(int $tamanho, array $campos): string
    {
        $chars = array_fill(0, $tamanho, ' ');
        foreach ($campos as $offset => $valor) {
            $valor = (string) $valor;
            for ($i = 0, $len = strlen($valor); $i < $len; $i++) {
                if ($offset + $i >= $tamanho) {
                    break;
                }
                $chars[$offset + $i] = $valor[$i];
            }
        }

        return implode('', $chars);
    }
}
