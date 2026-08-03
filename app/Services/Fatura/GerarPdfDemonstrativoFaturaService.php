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

        $ehTitularLancamento = function ($lanc): bool {
            $meta = $lanc->meta ?? [];
            $tipodep = (string) ($meta['tipodep'] ?? '');
            $depend = (string) ($meta['depend'] ?? '');

            return $tipodep === '3' || in_array($depend, ['00', '0'], true);
        };

        $mensalidades = $fatura->lancamentos
            ->where('codigo', 'mensalidade')
            ->filter(function ($lanc) use ($comDependentes, $ehTitularLancamento) {
                if ($comDependentes) {
                    return true;
                }

                return $ehTitularLancamento($lanc);
            })
            ->values();

        $mensalidadesTitulares = $fatura->lancamentos
            ->where('codigo', 'mensalidade')
            ->filter($ehTitularLancamento);
        $mensalidadesDependentes = $fatura->lancamentos
            ->where('codigo', 'mensalidade')
            ->reject($ehTitularLancamento);
        $totalGeral = (float) $fatura->lancamentos
            ->where('codigo', 'mensalidade')
            ->sum(fn ($lanc) => (float) $lanc->valor);

        foreach ($mensalidades as $lanc) {
            $meta = $lanc->meta ?? [];
            $tipodep = (string) ($meta['tipodep'] ?? '');
            $depend = (string) ($meta['depend'] ?? '');
            $ehTitular = $tipodep === '3' || $depend === '00' || $depend === '0';

            $valor = (float) $lanc->valor;
            $linhas[] = [
                'familia' => $meta['familia'] ?? '',
                'depend' => $depend,
                'pessoa' => $meta['pessoa'] ?? '',
                'codigo' => $meta['pessoa'] ?? ($meta['chave_vida'] ?? ''),
                'nome' => $lanc->descricao,
                'tipodep' => $ehTitular ? 'Titular' : 'Dependente',
                'tipopag' => $meta['tipopag'] ?? '',
                'inclusao' => $this->formatarData($meta['data_inclusao'] ?? ($meta['inclusao'] ?? null)),
                'nascimento' => $this->formatarData($meta['data_nascimento'] ?? ($meta['nascimento'] ?? null)),
                'valor' => $valor,
            ];
            $total += $valor;
        }

        /*
         * A tabela de titulares continua contendo somente titulares. A
         * mensalidade dos dependentes aparece apenas como informação separada
         * nos lançamentos, sem alterar o total contabilizado dos titulares.
         */
        /*
                $meta = $lanc->meta ?? [];
                $tipodep = (string) ($meta['tipodep'] ?? '');
                $depend = (string) ($meta['depend'] ?? '');

                return $tipodep === '3' || in_array($depend, ['00', '0'], true);
            })
            ->values();

        foreach ($mensalidades as $lanc) {
            $meta = $lanc->meta ?? [];
            $tipodep = (string) ($meta['tipodep'] ?? '');
            $depend = (string) ($meta['depend'] ?? '');
            $ehTitular = $tipodep === '3' || $depend === '00' || $depend === '0';

            $valor = (float) $lanc->valor;
            $linhas[] = [
                'familia' => $meta['familia'] ?? '',
                'depend' => $depend,
                'pessoa' => $meta['pessoa'] ?? '',
                'codigo' => $meta['pessoa'] ?? ($meta['chave_vida'] ?? ''),
                'nome' => $lanc->descricao,
                'tipodep' => $ehTitular ? 'Titular' : 'Dependente',
                'tipopag' => $meta['tipopag'] ?? '',
                'inclusao' => $this->formatarData($meta['data_inclusao'] ?? ($meta['inclusao'] ?? null)),
                'nascimento' => $this->formatarData($meta['data_nascimento'] ?? ($meta['nascimento'] ?? null)),
                'valor' => $valor,
            ];
            $total += $valor;
        } */

        usort($linhas, function ($a, $b) {
            return [$a['familia'], $a['depend']] <=> [$b['familia'], $b['depend']];
        });

        // Os lançamentos já pertencem à fatura da competência selecionada.
        // Mensalidades individuais são consolidadas em uma única cobrança.
        $lancamentos = collect();

        if ($mensalidadesTitulares->isNotEmpty()) {
            $lancamentos->push([
                'ordem' => $mensalidadesTitulares->min('ordem') ?: 1,
                'descricao' => 'COBRANCA DE MENSALIDADE',
                'valor' => (float) $mensalidadesTitulares->sum(fn ($lanc) => (float) $lanc->valor),
                'observacao' => 'REFERENCIA: '.$fatura->competencia,
            ]);
        }

        if ($mensalidadesDependentes->isNotEmpty()) {
            $lancamentos->push([
                'ordem' => $mensalidadesDependentes->min('ordem') ?: 1,
                'descricao' => 'COBRANCA DE MENSALIDADE(dependentes)',
                'valor' => (float) $mensalidadesDependentes->sum(fn ($lanc) => (float) $lanc->valor),
                'observacao' => 'REFERENCIA: '.$fatura->competencia,
            ]);
        }

        $lancamentos = $lancamentos
            ->merge($fatura->lancamentos
                ->reject(fn ($lanc) => $lanc->codigo === 'mensalidade')
                ->map(fn ($lanc, $indice) => [
                    'ordem' => $lanc->ordem ?: $indice + 1,
                    'descricao' => $lanc->descricao,
                    'valor' => (float) $lanc->valor,
                    'observacao' => 'REFERENCIA: '.$fatura->competencia,
                ]))
            ->sortBy('ordem')
            ->values();

        $html = view('faturas.demonstrativo', [
            'fatura' => $fatura,
            'empresa' => [
                'nome' => $conta['beneficiario_nome'] ?? $cliente->nome,
                'cnpj' => $conta['beneficiario_cnpj'] ?? '',
                'endereco' => 'R. SENADOR JOSE BERNARDO, 663 - CENTRO - CAICO - RN - CEP: 59300000',
            ],
            'sacado' => $fatura->contratante,
            'numero_fatura' => $this->pdfFatura->numeroFatura($fatura),
            'titulo' => $comDependentes
                ? 'Demonstrativo de Fatura (Titulares e Dependentes)'
                : 'Demonstrativo de Fatura (Somente Titulares)',
            'linhas' => $linhas,
            'total' => round($total, 2),
            'total_geral' => round($totalGeral, 2),
            'lancamentos' => $lancamentos,
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

    private function formatarData(mixed $data): string
    {
        if ($data instanceof \DateTimeInterface) {
            return $data->format('d/m/Y');
        }

        if (is_string($data) && trim($data) !== '') {
            try {
                return (new \DateTimeImmutable($data))->format('d/m/Y');
            } catch (\Throwable) {
                return $data;
            }
        }

        return '';
    }
}
