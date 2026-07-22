<?php

namespace App\Bancario\Sicredi;

use App\Bancario\DTO\BoletoCodigoBarrasDTO;
use App\Bancario\DTO\ContaCobranca;
use App\Contracts\Bancario\BancoBoletoAdapterInterface;
use Carbon\CarbonInterface;

class SicrediBoletoAdapter implements BancoBoletoAdapterInterface
{
    public function codigoBanco(): string
    {
        return '748';
    }

    public function viewTemplate(): string
    {
        return 'boletos.sicredi';
    }

    public function montarCodigoBarras(
        ContaCobranca $conta,
        string $nossoNumero,
        CarbonInterface $vencimento,
        float $valor,
    ): BoletoCodigoBarrasDTO {
        $dados = (new SicrediCodigoBarrasBoleto(
            conta: $conta,
            nossoNumero: $nossoNumero,
            vencimento: $vencimento,
            valor: $valor,
        ))->montar();

        return new BoletoCodigoBarrasDTO(
            codigoBarras: $dados['codigo_barras'],
            linhaDigitavel: $dados['linha_digitavel'],
            linhaDigitavelFormatada: $dados['linha_digitavel_formatada'],
            fatorVencimento: $dados['fator_vencimento'],
            campoLivre: $dados['campo_livre'],
            nossoNumeroExibicao: $dados['nosso_numero_exibicao'],
            agenciaCodigoBeneficiario: $dados['agencia_codigo_beneficiario'],
            codigoBancoFormatado: '748-X',
        );
    }
}
