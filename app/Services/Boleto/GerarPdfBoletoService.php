<?php

namespace App\Services\Boleto;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\FabricaAdaptadorBanco;
use App\Bancario\FabricaAdaptadorBoleto;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Support\Tenant\ClienteContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Orquestra PDF de boleto sem conhecer regras de banco.
 * Banco = FabricaAdaptadorBoleto; nosso número = adapter de remessa do mesmo banco.
 */
class GerarPdfBoletoService
{
    public function __construct(
        private readonly FabricaAdaptadorBoleto $fabricaBoleto,
        private readonly FabricaAdaptadorBanco $fabricaRemessa,
    ) {}

    public function executar(Cobranca $cobranca): string
    {
        $cliente = ClienteContext::get();
        $conta = ContaCobranca::fromClienteConfig($cliente->config ?? []);
        $conta->validar();

        $adapterBoleto = $this->fabricaBoleto->paraCliente($cliente);
        $adapterRemessa = $this->fabricaRemessa->paraCliente($cliente);

        $cobranca->loadMissing(['contratante', 'parcelas.beneficiarios']);

        if (! in_array($cobranca->meio, [null, '', 'boleto'], true)) {
            throw new DominioException('Somente cobranças de boleto podem gerar PDF.');
        }

        if (! $cobranca->nosso_numero || ! $cobranca->numero_registro) {
            $adapterRemessa->geradorNossoNumero()->garantir($cobranca, $conta);
            $cobranca->refresh();
        }

        if (! $cobranca->nosso_numero) {
            throw new DominioException('Cobrança sem nosso número para gerar boleto.');
        }

        $barras = $adapterBoleto->montarCodigoBarras(
            $conta,
            (string) $cobranca->nosso_numero,
            $cobranca->vencimento,
            (float) $cobranca->valor,
        );

        $generator = new BarcodeGeneratorPNG;
        $barcodePng = base64_encode($generator->getBarcode(
            $barras->codigoBarras,
            $generator::TYPE_INTERLEAVED_2_5,
            2,
            60
        ));

        $pagador = $cobranca->contratante;
        $padrao = $conta->pagadorPadrao;
        $instrucao = sprintf(
            'NO CASO DE ATRASO, COBRAR %s%% DE MULTA + JUROS DE MORA DE %s%% DO MÊS SOBRE O VALOR PRINCIPAL.',
            number_format($conta->percentualMultaPadrao, 2, ',', '.'),
            number_format($conta->percentualJurosMes, 2, ',', '.')
        );

        $composicao = [];
        foreach ($cobranca->parcelas as $parcela) {
            foreach ($parcela->beneficiarios as $b) {
                $composicao[] = [
                    'nome' => $b->nome,
                    'valor' => (float) $b->valor,
                ];
            }
            if ($composicao !== []) {
                break;
            }
        }

        $html = view($adapterBoleto->viewTemplate(), [
            'conta' => $conta,
            'cobranca' => $cobranca,
            'pagador' => [
                'nome' => $pagador?->nome ?? 'PAGADOR',
                'documento' => preg_replace('/\D/', '', (string) ($pagador?->documento ?? '')) ?: '',
                'chave' => $pagador?->chave_sigoweb,
                'endereco' => $pagador?->endereco ?: $padrao['endereco'],
                'bairro' => $pagador?->bairro ?: $padrao['bairro'],
                'cidade' => $pagador?->cidade ?: $padrao['cidade'],
                'uf' => $pagador?->uf ?: $padrao['uf'],
                'cep' => preg_replace('/\D/', '', (string) ($pagador?->cep ?: $padrao['cep'])) ?: '',
            ],
            'barras' => $barras,
            'barcode_base64' => $barcodePng,
            'instrucao' => $instrucao,
            'composicao' => $composicao,
            'cnpj_formatado' => $this->formatarCnpj($conta->beneficiarioCnpj),
            'documento_formatado' => $this->formatarDocumento(
                preg_replace('/\D/', '', (string) ($pagador?->documento ?? '')) ?: ''
            ),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function formatarCnpj(string $cnpj): string
    {
        $n = preg_replace('/\D/', '', $cnpj) ?: '';
        if (strlen($n) !== 14) {
            return $n;
        }

        return substr($n, 0, 2).'.'.substr($n, 2, 3).'.'.substr($n, 5, 3).'/'
            .substr($n, 8, 4).'-'.substr($n, 12, 2);
    }

    private function formatarDocumento(string $doc): string
    {
        if (strlen($doc) === 11) {
            return substr($doc, 0, 3).'.'.substr($doc, 3, 3).'.'.substr($doc, 6, 3).'-'.substr($doc, 9, 2);
        }
        if (strlen($doc) === 14) {
            return $this->formatarCnpj($doc);
        }

        return $doc;
    }
}
