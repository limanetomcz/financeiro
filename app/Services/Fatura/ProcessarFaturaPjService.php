<?php

namespace App\Services\Fatura;

use App\Enums\NaturezaLancamento;
use App\Enums\StatusFatura;
use App\Exceptions\DominioException;
use App\Models\Fatura;
use App\Models\FaturaLancamento;
use App\Services\Empresa\UpsertEmpresaPjService;
use App\Services\Integracao\SigoLaravelClient;
use Illuminate\Support\Facades\DB;

class ProcessarFaturaPjService
{
    public function __construct(
        private SigoLaravelClient $sigoLaravel,
        private CalcularValorVidaSeridoService $calcularVida,
        private CalcularImpostosFaturaPjService $calcularImpostos,
        private UpsertEmpresaPjService $upsertEmpresa,
        private AlocarNumeroFaturaService $alocarNumero,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $dadosOverride
     */
    public function executar(string $faturaId, ?string $bearerToken = null, ?array $dadosOverride = null): Fatura
    {
        $fatura = Fatura::query()->findOrFail($faturaId);

        if ($fatura->status !== StatusFatura::Processando) {
            return $fatura;
        }

        try {
            $dados = $dadosOverride
                ?? $this->sigoLaravel->dadosFaturaPj(
                    (string) $fatura->chave_plano_sigoweb,
                    $fatura->competencia,
                    $bearerToken,
                );

            return $this->montarFatura($fatura, $dados);
        } catch (\Throwable $e) {
            $fatura->update([
                'status' => StatusFatura::Erro,
                'mensagem_erro' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw $e instanceof DominioException
                ? $e
                : new DominioException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function montarFatura(Fatura $fatura, array $dados): Fatura
    {
        $plano = $dados['plano'] ?? null;
        if (! is_array($plano) || empty($plano['chave_sigoweb'])) {
            throw new DominioException('Dados do plano ausentes na resposta do Laravel.');
        }

        $vidas = $dados['vidas'] ?? [];
        if (! is_array($vidas) || $vidas === []) {
            throw new DominioException('Nenhuma vida ativa retornada para o plano/competência.');
        }

        $empresa = $this->upsertEmpresa->executar([
            'chave_sigoweb' => (string) $plano['chave_sigoweb'],
            'nome' => (string) ($plano['razao_social'] ?: $plano['nome'] ?: $plano['chave_sigoweb']),
            'documento' => $plano['documento'] ?? null,
            'endereco' => $plano['endereco'] ?? null,
            'bairro' => $plano['bairro'] ?? null,
            'cidade' => $plano['cidade'] ?? null,
            'cep' => $plano['cep'] ?? null,
            'uf' => $plano['uf'] ?? null,
        ]);

        $valoresAnteriores = $this->mapaValoresAnteriores(
            (string) $plano['chave_sigoweb'],
            $fatura->competencia,
            $fatura->id,
        );

        $percentual = (float) data_get($fatura->meta, 'percentual_reajuste', 0);
        $mesReajuste = isset($plano['mes_reajuste']) ? (int) $plano['mes_reajuste'] : null;
        $dtInclPlano = $plano['dt_incl_plano'] ?? null;

        $lancamentosVidas = [];
        $soma = 0.0;
        $ordem = 1;

        foreach ($vidas as $vida) {
            if (! is_array($vida)) {
                continue;
            }
            $chave = $this->calcularVida->chaveVida($vida);
            $anterior = $valoresAnteriores[$chave] ?? null;
            $valor = $this->calcularVida->executar(
                $vida,
                $anterior,
                $fatura->competencia,
                $mesReajuste,
                $dtInclPlano,
                $percentual,
            );

            $lancamentosVidas[] = [
                'codigo' => 'mensalidade',
                'descricao' => trim((string) ($vida['nome'] ?: 'Beneficiário '.$chave)),
                'natureza' => NaturezaLancamento::Base,
                'origem' => 'calculo_vida',
                'valor' => $valor,
                'ordem' => $ordem++,
                'meta' => [
                    'chave_vida' => $chave,
                    'familia' => $vida['familia'] ?? null,
                    'depend' => $vida['depend'] ?? null,
                    'pessoa' => $vida['pessoa'] ?? null,
                    'tipodep' => $vida['tipodep'] ?? null,
                    'tipopag' => $vida['tipopag_historico'] ?? $vida['tipopag'] ?? null,
                    'preco_tabela' => data_get($vida, 'preco.valor'),
                    'valor_anterior' => $anterior,
                ],
            ];
            $soma += $valor;
        }

        $soma = round($soma, 2);
        if ($soma <= 0) {
            throw new DominioException('Soma das vidas zerada — verifique TP/preços no Oracle (leitura).');
        }

        $descontoPerc = (float) ($plano['desconto_concedido_percentual'] ?? 0);
        $descontoValor = $descontoPerc > 0 ? round($soma * $descontoPerc / 100, 2) : 0.0;
        $baseImposto = round($soma - $descontoValor, 2);

        $impostosMeta = $dados['impostos'] ?? [];
        $retencoes = $this->calcularImpostos->executar([
            'valor_bruto' => $baseImposto,
            'flags' => $impostosMeta['flags'] ?? [],
            'aliquotas' => $impostosMeta['aliquotas'] ?? [],
            'regras' => $impostosMeta['regras'] ?? [],
            'chave_plano' => (string) $plano['chave_sigoweb'],
        ]);

        if (! empty($plano['dia_vencimento'])) {
            $dia = min((int) $plano['dia_vencimento'], 28);
            $vencto = $fatura->vencimento->copy()->day($dia);
        } else {
            $vencto = $fatura->vencimento;
        }

        return DB::transaction(function () use (
            $fatura,
            $empresa,
            $lancamentosVidas,
            $descontoValor,
            $descontoPerc,
            $retencoes,
            $vencto,
            $dados,
            $plano,
            $soma,
        ) {
            $fatura = Fatura::query()->whereKey($fatura->id)->lockForUpdate()->firstOrFail();

            FaturaLancamento::withTrashed()->where('fatura_id', $fatura->id)->forceDelete();

            foreach ($lancamentosVidas as $lanc) {
                FaturaLancamento::query()->create([
                    'fatura_id' => $fatura->id,
                    'codigo' => $lanc['codigo'],
                    'descricao' => $lanc['descricao'],
                    'natureza' => $lanc['natureza'],
                    'origem' => $lanc['origem'],
                    'valor' => $lanc['valor'],
                    'ordem' => $lanc['ordem'],
                    'meta' => $lanc['meta'],
                ]);
            }

            $ordem = count($lancamentosVidas) + 1;

            if ($descontoValor > 0) {
                FaturaLancamento::query()->create([
                    'fatura_id' => $fatura->id,
                    'codigo' => 'desconto_concedido',
                    'descricao' => 'Desconto concedido do plano',
                    'natureza' => NaturezaLancamento::Retencao,
                    'origem' => 'composicao_plano',
                    'valor' => $descontoValor,
                    'ordem' => $ordem++,
                    'meta' => ['percentual' => $descontoPerc],
                ]);
            }

            foreach ($retencoes as $ret) {
                FaturaLancamento::query()->create([
                    'fatura_id' => $fatura->id,
                    'codigo' => $ret['codigo'],
                    'descricao' => $ret['descricao'],
                    'natureza' => NaturezaLancamento::from($ret['natureza']),
                    'origem' => 'calculo_imposto',
                    'valor' => $ret['valor'],
                    'ordem' => $ordem++,
                    'meta' => [
                        'flags' => data_get($dados, 'impostos.flags'),
                        'aliquotas' => data_get($dados, 'impostos.aliquotas'),
                    ],
                ]);
            }

            $this->recalcularTotais($fatura);

            $numero = $this->alocarNumero->executar($fatura);

            $fatura->update([
                'contratante_id' => $empresa->id,
                'vencimento' => $vencto->toDateString(),
                'status' => StatusFatura::Aberta,
                'numero' => $numero,
                'mensagem_erro' => null,
                'meta' => array_merge($fatura->meta ?? [], [
                    'vidas_qtd' => count($lancamentosVidas),
                    'referencia' => $dados['referencia'] ?? null,
                    'soma_vidas' => $soma,
                    'processado_em' => now()->toIso8601String(),
                    'chave_plano' => $plano['chave_sigoweb'],
                ]),
            ]);

            return $fatura->fresh(['lancamentos', 'contratante']);
        });
    }

    /**
     * @return array<string, float>
     */
    private function mapaValoresAnteriores(string $chavePlano, string $competencia, string $faturaAtualId): array
    {
        $anterior = Fatura::query()
            ->where('chave_plano_sigoweb', $chavePlano)
            ->where('competencia', '<', $competencia)
            ->whereIn('status', [StatusFatura::Aberta, StatusFatura::EmCobranca, StatusFatura::Paga])
            ->whereKeyNot($faturaAtualId)
            ->orderByDesc('competencia')
            ->first();

        if (! $anterior) {
            return [];
        }

        $mapa = [];
        foreach ($anterior->lancamentos()->where('codigo', 'mensalidade')->get() as $lanc) {
            $chave = data_get($lanc->meta, 'chave_vida');
            if ($chave) {
                $mapa[$chave] = (float) $lanc->valor;
            }
        }

        return $mapa;
    }

    private function recalcularTotais(Fatura $fatura): void
    {
        $bruto = 0.0;
        $retencoes = 0.0;
        $acrescimos = 0.0;

        foreach ($fatura->lancamentos()->get() as $lanc) {
            $valor = (float) $lanc->valor;
            match ($lanc->natureza) {
                NaturezaLancamento::Base => $bruto += $valor,
                NaturezaLancamento::Retencao => $retencoes += $valor,
                NaturezaLancamento::Acrescimo => $acrescimos += $valor,
                NaturezaLancamento::Informativo => null,
            };
        }

        $fatura->update([
            'valor_bruto' => round($bruto, 2),
            'valor_retencoes' => round($retencoes, 2),
            'valor_acrescimos' => round($acrescimos, 2),
            'valor_liquido' => round($bruto - $retencoes + $acrescimos, 2),
        ]);
    }
}
