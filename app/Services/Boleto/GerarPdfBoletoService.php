<?php

namespace App\Services\Boleto;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\FabricaAdaptadorBanco;
use App\Bancario\FabricaAdaptadorBoleto;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Services\Cobranca\CobrancaService;
use App\Support\Tenant\ClienteContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Picqer\Barcode\BarcodeGeneratorHTML;

/**
 * Orquestra PDF de boleto sem conhecer regras de banco.
 * Banco = FabricaAdaptadorBoleto; nosso número = adapter de remessa do mesmo banco.
 */
class GerarPdfBoletoService
{
    private FabricaAdaptadorBoleto $fabricaBoleto;
    private FabricaAdaptadorBanco $fabricaRemessa;

    public function __construct(
        FabricaAdaptadorBoleto $fabricaBoleto,
        FabricaAdaptadorBanco $fabricaRemessa
    ) {
        $this->fabricaBoleto = $fabricaBoleto;
        $this->fabricaRemessa = $fabricaRemessa;
    }

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

        // HTML não depende de GD/Imagick (imagem Docker mínima).
        $generator = new BarcodeGeneratorHTML;
        $barcodeHtml = $generator->getBarcode(
            $barras->codigoBarras,
            $generator::TYPE_INTERLEAVED_2_5,
            1,
            50
        );

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
        $historico10ultimosPagamewntos = CobrancaService::buscarHistoricoPagamentos($cliente->id);

        $html = view($adapterBoleto->viewTemplate(), [
            'conta' => $conta,
            'cliente' => $cliente,
            'historico10ultimosPagamewntos' => $historico10ultimosPagamewntos,
            'cobranca' => $cobranca,
            'pagador' => [
                'nome' => isset($pagador) ? $pagador->nome : 'PAGADOR',
                'documento' => preg_replace('/\D/', '', (string) (isset($pagador) ? $pagador->documento : '')) ?: '',
                'chave' => isset($pagador) ? $pagador->chave_sigoweb : null,
                'endereco' => isset($pagador) ? ($pagador->endereco ?: $padrao['endereco']) : $padrao['endereco'],
                'bairro' => isset($pagador) ? ($pagador->bairro ?: $padrao['bairro']) : $padrao['bairro'],
                'cidade' => isset($pagador) ? ($pagador->cidade ?: $padrao['cidade']) : $padrao['cidade'],
                'uf' => isset($pagador) ? ($pagador->uf ?: $padrao['uf']) : $padrao['uf'],
                'cep' => preg_replace('/\D/', '', (string) (isset($pagador) ? ($pagador->cep ?: $padrao['cep']) : $padrao['cep'])) ?: '',
            ],
            'barras' => $barras,
            'barcode_html' => $barcodeHtml,
            'instrucao' => $instrucao,
            'composicao' => $composicao,
            'cnpj_formatado' => $this->formatarCnpj($conta->beneficiarioCnpj),
            'documento_formatado' => $this->formatarDocumento(
                preg_replace('/\D/', '', (string) (isset($pagador) ? $pagador->documento : '')) ?: ''
            ),
            'logo_base64' => $this->carregarLogoBase64('imgs/logoUniodonto.png'),
            'logo_sicredi_base64' => $this->carregarLogoBase64('imgs/sicredi-logo.png'),
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

    private function carregarLogoBase64(string $caminho): string
    {
        $logoPath = public_path($caminho);
        if (! file_exists($logoPath)) {
            return '';
        }

        $logoContents = file_get_contents($logoPath);
        if ($logoContents === false) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode($logoContents);
    }
}
