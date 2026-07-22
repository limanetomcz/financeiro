<?php

namespace App\Bancario\Sicredi;

use App\Bancario\DTO\OcorrenciaRetorno;
use App\Contracts\Bancario\RetornoParserInterface;
use App\Exceptions\DominioException;
use Illuminate\Support\Collection;

/**
 * Parser CNAB 240 Sicredi (segmentos T + U), portado de
 * BaixaArquivoRetornoService::arquivoSicredi200Colunas (sigo-laravel).
 *
 * Posições 0-based iguais ao legado — validar com .CRT real da Seridó.
 */
class SicrediCnab240RetornoParser implements RetornoParserInterface
{
    public function parse(string $conteudo): Collection
    {
        $linhas = preg_split("/\r\n|\n|\r/", $conteudo) ?: [];
        $linhas = array_values(array_filter($linhas, fn ($l) => trim($l) !== ''));

        if ($linhas === []) {
            throw new DominioException('Arquivo de retorno vazio.');
        }

        $primeira = $linhas[0];
        if (strlen($primeira) < 3 || substr($primeira, 0, 3) !== '748') {
            throw new DominioException('O arquivo não é da Sicredi (banco 748).');
        }

        /** @var Collection<int, OcorrenciaRetorno> $ocorrencias */
        $ocorrencias = collect();

        $pendenteT = null;
        $linhaT = null;
        $linhaU = null;
        $linhaNumT = 0;

        $flush = function () use (&$pendenteT, &$linhaT, &$linhaU, &$linhaNumT, $ocorrencias): void {
            if ($pendenteT === null) {
                return;
            }

            $ocorrencias->push(new OcorrenciaRetorno(
                linha: $linhaNumT,
                codigoMovimento: $pendenteT['codigo'],
                nossoNumero: $pendenteT['nosso_numero'],
                numeroRegistro: $pendenteT['numero_registro'],
                vencimento: $pendenteT['vencimento'],
                pagoEm: $pendenteT['pago_em'],
                valorPago: $pendenteT['valor_pago'],
                motivoRejeicao: $pendenteT['motivo'],
                linhaT: $linhaT ?? '',
                linhaU: $linhaU,
                valorJuros: $pendenteT['valor_juros'],
            ));

            $pendenteT = null;
            $linhaT = null;
            $linhaU = null;
            $linhaNumT = 0;
        };

        foreach ($linhas as $idx => $raw) {
            $linha = rtrim($raw, "\r\n");
            $numeroLinha = $idx + 1;

            if (strlen($linha) < 14) {
                continue;
            }

            $segmento = $linha[13];

            if ($segmento !== 'U' && $segmento !== 'Y' && $pendenteT !== null) {
                $flush();
            }

            if ($segmento === 'T') {
                if (strlen($linha) < 150) {
                    continue;
                }

                $codigo = substr($linha, 15, 2);
                $numeroRegistro = trim(substr($linha, 37, 9));
                $nossoNumero = trim(substr($linha, 58, 15));
                $vencimento = $this->dataCnabParaIso(substr($linha, 73, 8));
                $motivo = $codigo === '03' ? trim(substr($linha, 213, 2)) : null;

                $pendenteT = [
                    'codigo' => $codigo,
                    'nosso_numero' => $nossoNumero,
                    'numero_registro' => $numeroRegistro,
                    'vencimento' => $vencimento,
                    'pago_em' => null,
                    'valor_pago' => null,
                    'valor_juros' => null,
                    'motivo' => $motivo !== '' ? $motivo : null,
                ];
                $linhaT = $linha;
                $linhaU = null;
                $linhaNumT = $numeroLinha;

                continue;
            }

            if ($segmento === 'U' && $pendenteT !== null) {
                if (strlen($linha) < 150) {
                    continue;
                }

                $valorPago = ((int) substr($linha, 77, 15)) / 100;
                $valorJuros = ((int) substr($linha, 17, 15)) / 100;
                $pagoEm = $this->dataCnabParaIso(substr($linha, 137, 8));
                if ($pagoEm === null) {
                    $pagoEm = $this->dataCnabParaIso(substr($linha, 145, 8));
                }

                $pendenteT['valor_pago'] = round($valorPago, 2);
                $pendenteT['valor_juros'] = round($valorJuros, 2);
                $pendenteT['pago_em'] = $pagoEm;
                $linhaU = $linha;

                continue;
            }

            // Segmento Y (PIX URL/txid) — ignorado no MVP; flush já ocorre no próximo T/trailer.
        }

        $flush();

        return $ocorrencias->values();
    }

    private function dataCnabParaIso(string $dmY): ?string
    {
        $dmY = trim($dmY);
        if (strlen($dmY) !== 8 || ! ctype_digit($dmY) || $dmY === '00000000') {
            return null;
        }

        $dia = substr($dmY, 0, 2);
        $mes = substr($dmY, 2, 2);
        $ano = substr($dmY, 4, 4);

        if (! checkdate((int) $mes, (int) $dia, (int) $ano)) {
            return null;
        }

        return $ano.'-'.$mes.'-'.$dia;
    }
}
