<?php

namespace App\Bancario\Sicredi;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\DTO\PagadorRemessa;
use App\Bancario\Support\TextoCnab;
use App\Contracts\Bancario\RemessaLayoutGeneratorInterface;
use App\Models\Remessa;
use App\Models\RemessaItem;
use Illuminate\Support\Collection;

/**
 * CNAB 240 — valores de banco/espécie/códigos vêm de ContaCobranca (config do cliente).
 */
class SicrediCnab240Layout implements RemessaLayoutGeneratorInterface
{
    public function gerar(Remessa $remessa, ContaCobranca $conta, Collection $itens): string
    {
        $agora = now();
        $banco = TextoCnab::lpad(TextoCnab::apenasDigitos($conta->codigoBanco), 3);
        $linhas = [];

        $linhas[] = $this->headerArquivo($conta, $banco, $remessa->lote, $agora);
        $linhas[] = $this->headerLote($conta, $banco, $remessa->lote, $agora);

        $seqNoLote = 1;
        foreach ($itens->sortBy('nosso_numero') as $item) {
            /** @var RemessaItem $item */
            $linhas[] = $this->segmentoP($conta, $banco, $item, $seqNoLote++);
            $linhas[] = $this->segmentoQ($banco, $item, $seqNoLote++);
            $linhas[] = $this->segmentoR($banco, $item, $seqNoLote++);
        }

        $qtdTitulos = $itens->count();
        $linhas[] = $this->trailerLote($banco, ($qtdTitulos * 3) + 2);
        $linhas[] = $this->trailerArquivo($banco, ($qtdTitulos * 3) + 4);

        return implode("\r\n", $linhas)."\r\n";
    }

    private function headerArquivo(ContaCobranca $conta, string $banco, int $lote, \DateTimeInterface $agora): string
    {
        return $banco
            .'0000'
            .'0'
            .TextoCnab::rpad('', 9)
            .'2'
            .TextoCnab::lpad(TextoCnab::apenasDigitos($conta->beneficiarioCnpj), 14)
            .TextoCnab::rpad('', 20)
            .TextoCnab::lpad($conta->agencia, 5)
            .TextoCnab::rpad($conta->dvAgencia !== '' ? $conta->dvAgencia : ' ', 1)
            .TextoCnab::lpad($conta->conta, 12)
            .TextoCnab::rpad($conta->dvConta, 1)
            .TextoCnab::rpad('', 1)
            .TextoCnab::alfanumerico($conta->beneficiarioNome, 30)
            .TextoCnab::rpad($conta->nomeBancoArquivo, 30)
            .TextoCnab::rpad('', 10)
            .'1'
            .$agora->format('dmY')
            .$agora->format('His')
            .TextoCnab::lpad($lote, 6)
            .$conta->layoutArquivoVersao
            .$conta->layoutArquivoDensidade
            .TextoCnab::rpad('', 69);
    }

    private function headerLote(ContaCobranca $conta, string $banco, int $lote, \DateTimeInterface $agora): string
    {
        return $banco
            .'0001'
            .'1'
            .'R'
            .'01'
            .TextoCnab::rpad('', 2)
            .'040'
            .' '
            .'2'
            .TextoCnab::lpad(TextoCnab::apenasDigitos($conta->beneficiarioCnpj), 15)
            .TextoCnab::rpad('', 20)
            .TextoCnab::lpad($conta->agencia, 5)
            .TextoCnab::rpad($conta->dvAgencia !== '' ? $conta->dvAgencia : ' ', 1)
            .TextoCnab::lpad($conta->conta, 12)
            .TextoCnab::rpad($conta->dvConta, 1)
            .TextoCnab::rpad('', 1)
            .TextoCnab::alfanumerico($conta->beneficiarioNome, 30)
            .TextoCnab::rpad('', 80)
            .TextoCnab::lpad($lote, 8)
            .$agora->format('dmY')
            .'00000000'
            .TextoCnab::rpad('', 33);
    }

    private function segmentoP(ContaCobranca $conta, string $banco, RemessaItem $item, int $seq): string
    {
        $juros = (float) $item->valor_juros_dia;
        $codJuros = $juros > 0 ? $conta->codigoJurosMoraComJuros : $conta->codigoJurosMoraSemJuros;
        $dataJuros = $juros > 0 ? $item->vencimento->format('dmY') : '00000000';

        return $banco
            .'0001'
            .'3'
            .TextoCnab::lpad($seq, 5)
            .'P'
            .' '
            .$item->operacao->value
            .TextoCnab::lpad($conta->agencia, 5)
            .TextoCnab::rpad($conta->dvAgencia !== '' ? $conta->dvAgencia : ' ', 1)
            .TextoCnab::lpad($conta->conta, 12)
            .TextoCnab::rpad($conta->dvConta, 1)
            .TextoCnab::rpad('', 1)
            .TextoCnab::rpad((string) ($item->numero_registro ?? ''), 20)
            .$conta->modalidadeCarteira
            .$conta->carteira
            .$conta->tipoDocumentoCobranca
            .$conta->idEmissaoBoleto
            .$conta->idDistribuicaoBoleto
            .TextoCnab::rpad($item->nosso_numero, 15)
            .$item->vencimento->format('dmY')
            .TextoCnab::valorCentavos((float) $item->valor)
            .'00000'
            .' '
            .$conta->especieTitulo
            .'N'
            .$item->data_emissao->format('dmY')
            .$codJuros
            .$dataJuros
            .TextoCnab::valorCentavos($juros > 0 ? $juros : 0)
            .'1'
            .'00000000'
            .'000000000000000'
            .'000000000000000'
            .'000000000000000'
            .TextoCnab::rpad('', 25)
            .$conta->codigoProtesto
            .$conta->diasProtesto
            .$conta->codigoDevolucao
            .TextoCnab::lpad($item->dias_devolucao, 3)
            .$conta->codigoMoeda
            .'0000000000'
            .' ';
    }

    private function segmentoQ(string $banco, RemessaItem $item, int $seq): string
    {
        $pagador = PagadorRemessa::fromArray($item->pagador ?? []);

        return $banco
            .'0001'
            .'3'
            .TextoCnab::lpad($seq, 5)
            .'Q'
            .' '
            .$item->operacao->value
            .$pagador->tipoInscricao
            .TextoCnab::lpad(TextoCnab::apenasDigitos($pagador->inscricao), 15)
            .TextoCnab::alfanumerico($pagador->nome, 40)
            .TextoCnab::alfanumerico($pagador->endereco, 40)
            .TextoCnab::alfanumerico($pagador->bairro, 15)
            .TextoCnab::lpad(TextoCnab::apenasDigitos($pagador->cep), 8)
            .TextoCnab::alfanumerico($pagador->cidade, 15)
            .TextoCnab::rpad(strtoupper($pagador->uf), 2)
            .'0'
            .'000000000000000'
            .TextoCnab::rpad('', 40)
            .'000'
            .TextoCnab::rpad('', 20)
            .TextoCnab::rpad('', 8);
    }

    private function segmentoR(string $banco, RemessaItem $item, int $seq): string
    {
        $dataMulta = $item->vencimento->copy()->addDay()->format('dmY');

        return $banco
            .'0001'
            .'3'
            .TextoCnab::lpad($seq, 5)
            .'R'
            .' '
            .$item->operacao->value
            .'000000000000000000000000'
            .str_repeat('0', 24)
            .$item->codigo_multa
            .$dataMulta
            .TextoCnab::valorCentavos((float) $item->valor_multa)
            .TextoCnab::rpad('', 10)
            .TextoCnab::rpad('', 40)
            .TextoCnab::rpad('', 40)
            .TextoCnab::rpad('', 61);
    }

    private function trailerLote(string $banco, int $qtdRegistros): string
    {
        return $banco
            .'0001'
            .'5'
            .TextoCnab::rpad('', 9)
            .TextoCnab::lpad($qtdRegistros, 6)
            .'000000'
            .'00000000000000000'
            .'000000'
            .'00000000000000000'
            .'000000'
            .'00000000000000000'
            .'000000'
            .'00000000000000000'
            .TextoCnab::rpad('', 125);
    }

    private function trailerArquivo(string $banco, int $qtdRegistros): string
    {
        return $banco
            .'9999'
            .'9'
            .TextoCnab::rpad('', 9)
            .TextoCnab::lpad(1, 6)
            .TextoCnab::lpad($qtdRegistros, 6)
            .'000000'
            .TextoCnab::rpad('', 205);
    }
}
