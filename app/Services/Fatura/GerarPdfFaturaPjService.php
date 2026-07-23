<?php

namespace App\Services\Fatura;

use App\Enums\StatusFatura;
use App\Exceptions\DominioException;
use App\Models\Fatura;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Dompdf\Dompdf;
use Dompdf\Options;

class GerarPdfFaturaPjService
{
    public function executar(Fatura $fatura): string
    {
        $fatura->loadMissing(['contratante', 'lancamentos', 'cobranca']);

        if (! in_array($fatura->status, [
            StatusFatura::Aberta,
            StatusFatura::EmCobranca,
            StatusFatura::Paga,
        ], true)) {
            throw new DominioException('Só é possível emitir PDF de fatura aberta, em cobrança ou paga.');
        }

        $cliente = ClienteContext::get();
        $conta = data_get($cliente->config, 'bancario.conta', ClienteConfig::padraoSerido()['bancario']['conta']);
        $pagador = $fatura->contratante;

        $porCodigo = [];
        foreach ($fatura->lancamentos as $l) {
            $porCodigo[$l->codigo] = (float) $l->valor;
        }

        $html = view('faturas.fatura', [
            'fatura' => $fatura,
            'empresa' => [
                'nome' => $conta['beneficiario_nome'] ?? $cliente->nome,
                'cnpj' => $this->formatarCnpj((string) ($conta['beneficiario_cnpj'] ?? '')),
                'endereco' => 'R. SENADOR JOSE BERNARDO, 663 - CENTRO - CAICO - RN - CEP: 59300000',
            ],
            'sacado' => [
                'chave' => $pagador?->chave_sigoweb,
                'nome' => $pagador?->nome,
                'documento' => $this->formatarCnpj((string) ($pagador?->documento ?? '')),
                'endereco' => $pagador?->endereco,
                'bairro' => $pagador?->bairro,
                'cidade' => $pagador?->cidade,
                'uf' => $pagador?->uf,
                'cep' => $pagador?->cep,
            ],
            'numero_fatura' => $this->numeroFatura($fatura),
            'impostos' => [
                'ir' => $porCodigo['ir'] ?? 0,
                'iss' => $porCodigo['iss'] ?? 0,
                'pis' => $porCodigo['pis'] ?? ($porCodigo['piscofins'] ?? 0),
                'cofins' => $porCodigo['cofins'] ?? 0,
                'csll' => $porCodigo['csll'] ?? 0,
                'inss' => $porCodigo['inss'] ?? 0,
                'outros' => ($porCodigo['outro_desconto'] ?? 0) + ($porCodigo['desconto_concedido'] ?? 0),
            ],
            'impresso_em' => now()->format('d/m/Y'),
        ])->render();

        return $this->renderPdf($html);
    }

    public function numeroFatura(Fatura $fatura): string
    {
        if ($fatura->numero) {
            return $fatura->numero;
        }

        // Fallback só se fatura antiga sem número alocado.
        $ref = str_replace('-', '', $fatura->competencia);

        return $ref.'/0000';
    }

    private function renderPdf(string $html): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function formatarCnpj(string $digits): string
    {
        $d = preg_replace('/\D/', '', $digits) ?: '';
        if (strlen($d) !== 14) {
            return $d;
        }

        return substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12, 2);
    }
}
