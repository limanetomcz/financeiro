<?php

namespace App\Services\Fatura;

use App\Enums\StatusFatura;
use App\Exceptions\DominioException;
use App\Models\Fatura;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Dompdf\Dompdf;
use Dompdf\Options;

class GerarPdfDemonstrativoFaturaService
{
    public function __construct(
        private GerarPdfFaturaPjService $pdfFatura,
    ) {
    }

    /**
     * @param  bool  $comDependentes  false = só titulares (tipodep 3 / depend 00)
     */
    public function executar(Fatura $fatura, bool $comDependentes = false): string
    {
        $fatura->loadMissing(['contratante', 'lancamentos']);

        if (! in_array($fatura->status, [
            StatusFatura::Aberta,
            StatusFatura::EmCobranca,
            StatusFatura::Paga,
        ], true)) {
            throw new DominioException('Só é possível emitir demonstrativo de fatura aberta, em cobrança ou paga.');
        }

        $cliente = ClienteContext::get();
        $conta = data_get($cliente->config, 'bancario.conta', ClienteConfig::padraoSerido()['bancario']['conta']);

        $linhas = [];
        $total = 0.0;

        foreach ($fatura->lancamentos->where('codigo', 'mensalidade') as $lanc) {
            $meta = $lanc->meta ?? [];
            $tipodep = (string) ($meta['tipodep'] ?? '');
            $depend = (string) ($meta['depend'] ?? '');
            $ehTitular = $tipodep === '3' || $depend === '00' || $depend === '0';

            if (! $comDependentes && ! $ehTitular) {
                continue;
            }

            $valor = (float) $lanc->valor;
            $linhas[] = [
                'familia' => $meta['familia'] ?? '',
                'depend' => $depend,
                'pessoa' => $meta['pessoa'] ?? '',
                'nome' => $lanc->descricao,
                'tipodep' => $ehTitular ? 'Titular' : 'Dependente',
                'tipopag' => $meta['tipopag'] ?? '',
                'valor' => $valor,
            ];
            $total += $valor;
        }

        usort($linhas, function ($a, $b) {
            return [$a['familia'], $a['depend']] <=> [$b['familia'], $b['depend']];
        });

        $html = view('faturas.demonstrativo', [
            'fatura' => $fatura,
            'empresa' => [
                'nome' => $conta['beneficiario_nome'] ?? $cliente->nome,
                'cnpj' => $conta['beneficiario_cnpj'] ?? '',
            ],
            'sacado' => $fatura->contratante,
            'numero_fatura' => $this->pdfFatura->numeroFatura($fatura),
            'titulo' => $comDependentes
                ? 'Demonstrativo de Fatura (Titulares e Dependentes)'
                : 'Demonstrativo de Fatura (Somente Titulares)',
            'linhas' => $linhas,
            'total' => round($total, 2),
            'com_dependentes' => $comDependentes,
            'impresso_em' => now()->format('d/m/Y H:i'),
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
}
