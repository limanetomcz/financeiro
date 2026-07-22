<?php

namespace App\Bancario\Selecao;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\DTO\PagadorRemessa;
use App\Bancario\DTO\TituloRemessa;
use App\Bancario\Sicredi\SicrediNossoNumeroGenerator;
use App\Enums\OperacaoRemessa;
use App\Enums\TipoCobranca;
use App\Enums\TipoContratante;
use App\Models\Cobranca;
use App\Models\Fatura;
use Carbon\Carbon;

class MapeadorCobrancaParaTitulo
{
    public function __construct(
        private readonly SicrediNossoNumeroGenerator $nossoNumeroGenerator,
    ) {}

    public function mapear(
        Cobranca $cobranca,
        ContaCobranca $conta,
        OperacaoRemessa $operacao = OperacaoRemessa::Entrada,
    ): TituloRemessa {
        $numeros = $this->nossoNumeroGenerator->garantir($cobranca, $conta);
        $cobranca->refresh();
        $cobranca->loadMissing('contratante');

        $valor = (float) $cobranca->valor;
        $jurosDia = $this->jurosDia($conta, $valor);
        $percentualMulta = $conta->percentualMultaPadrao;

        $contratante = $cobranca->contratante;
        $doc = preg_replace('/\D/', '', (string) ($contratante?->documento ?? '')) ?: '';
        $ehPj = ($contratante?->tipo === TipoContratante::Pj) || strlen($doc) > 11;
        $padrao = $conta->pagadorPadrao;

        // codigo_multa=2 (percentual): CNAB leva o % (ex.: 2.00), não o R$.
        $valorMultaCnab = $conta->codigoMulta === '2'
            ? $percentualMulta
            : round($valor * ($percentualMulta / 100), 2);

        return new TituloRemessa(
            cobrancaId: $cobranca->id,
            nossoNumero: $numeros['nosso_numero'],
            numeroRegistro: $numeros['numero_registro'],
            operacao: $operacao,
            valor: $valor,
            valorJurosDia: $jurosDia,
            valorMulta: $valorMultaCnab,
            vencimento: $cobranca->vencimento,
            dataEmissao: $cobranca->data_emissao_boleto
                ? Carbon::parse($cobranca->data_emissao_boleto)
                : ($cobranca->created_at ? Carbon::parse($cobranca->created_at) : now()),
            tipoBoleto: $this->tipoBoleto($cobranca),
            diasDevolucao: $conta->diasDevolucao,
            codigoMulta: $conta->codigoMulta,
            pagador: new PagadorRemessa(
                tipoInscricao: $ehPj ? $conta->tipoInscricaoPj : $conta->tipoInscricaoPf,
                inscricao: $doc !== '' ? $doc : ($ehPj ? '00000000000000' : '00000000000'),
                nome: (string) ($contratante?->nome ?? 'PAGADOR'),
                endereco: (string) ($contratante?->endereco ?: $padrao['endereco']),
                bairro: (string) ($contratante?->bairro ?: $padrao['bairro']),
                cidade: (string) ($contratante?->cidade ?: $padrao['cidade']),
                cep: preg_replace('/\D/', '', (string) ($contratante?->cep ?: $padrao['cep'])) ?: '00000000',
                uf: (string) ($contratante?->uf ?: $padrao['uf']),
            ),
        );
    }

    private function tipoBoleto(Cobranca $cobranca): string
    {
        if (Fatura::query()->where('cobranca_id', $cobranca->id)->exists()) {
            return 'F';
        }

        return $cobranca->tipo === TipoCobranca::Consolidada ? 'MA' : 'M';
    }

    private function jurosDia(ContaCobranca $conta, float $valor): float
    {
        $percMes = $conta->percentualJurosMes;

        if ($percMes <= 0) {
            return round($conta->jurosDiaPadrao, 2);
        }

        return round((($percMes / 30) / 100) * $valor, 2);
    }
}
