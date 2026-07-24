<?php

namespace App\Services\Fatura;

use App\Enums\NaturezaLancamento;
use App\Enums\StatusFatura;
use App\Enums\StatusParcela;
use App\Enums\TipoContratante;
use App\Exceptions\DominioException;
use App\Models\Contratante;
use App\Models\Fatura;
use App\Models\FaturaLancamento;
use App\Models\Parcela;
use App\Services\Empresa\UpsertEmpresaPjService;
use App\Services\Integracao\SigoLaravelClient;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GerarFaturaPjService
{
    public function __construct(
        private SigoLaravelClient $sigoLaravel,
        private CalcularImpostosFaturaPjService $calcularImpostos,
        private UpsertEmpresaPjService $upsertEmpresa,
        private AlocarNumeroFaturaService $alocarNumero,
    ) {
    }

    /**
     * Fluxo oficial: plano E + competência → composição Laravel → impostos → fatura.
     *
     * @param  array<string, mixed>|null  $composicao  Se informado, não chama o Laravel (lab/testes).
     */
    public function executarPorPlano(
        string $chavePlano,
        string $competencia,
        ?string $vencimento = null,
        ?array $composicao = null,
    ): Fatura {
        if (! preg_match('/^\d{4}-\d{2}$/', $competencia)) {
            throw new DominioException('Competência deve estar no formato YYYY-MM.');
        }

        $composicao ??= $this->sigoLaravel->composicaoFaturaPj($chavePlano, $competencia);

        return $this->executarComComposicao($composicao, $vencimento);
    }

    /**
     * @param  array<string, mixed>  $composicao
     */
    public function executarComComposicao(array $composicao, ?string $vencimento = null): Fatura
    {
        $plano = $composicao['plano'] ?? null;
        if (! is_array($plano) || empty($plano['chave_sigoweb'])) {
            throw new DominioException('Composição sem dados do plano.');
        }

        if (strtoupper((string) ($plano['tipo'] ?? '')) !== 'E') {
            throw new DominioException('Composição deve ser de plano empresarial (tipo E).');
        }

        $competencia = (string) ($composicao['competencia'] ?? '');
        if (! preg_match('/^\d{4}-\d{2}$/', $competencia)) {
            throw new DominioException('Competência inválida na composição.');
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

        $cliente = ClienteContext::get();

        if (Fatura::query()->where('contratante_id', $empresa->id)->where('competencia', $competencia)->exists()) {
            throw new DominioException("Já existe fatura para a competência {$competencia}.");
        }

        $maxAbertas = ClienteConfig::pjMaxFaturasAbertasParaGerar($cliente);
        $abertas = Fatura::query()
            ->where('contratante_id', $empresa->id)
            ->whereIn('status', [StatusFatura::Aberta, StatusFatura::EmCobranca, StatusFatura::Rascunho])
            ->count();

        if ($abertas >= $maxAbertas) {
            throw new DominioException(
                "Empresa já possui {$abertas} fatura(s) em aberto (limite para gerar: {$maxAbertas})."
            );
        }

        $base = $composicao['base'] ?? [];
        $baseCheia = round(
            (float) ($base['valor_mensalidades'] ?? 0) + (float) ($base['valor_custo'] ?? 0),
            2
        );
        $descontoConcedido = round((float) ($base['desconto_concedido_valor'] ?? 0), 2);
        $valorBrutoParaImposto = round((float) ($base['valor_bruto'] ?? ($baseCheia - $descontoConcedido)), 2);

        if ($baseCheia <= 0) {
            throw new DominioException(
                'Base da fatura zerada: não há mensalidades no Oracle para este plano/competência.'
            );
        }

        $diaVenctoPlano = isset($plano['dia_vencimento']) ? (int) $plano['dia_vencimento'] : null;
        $vencimentoData = $vencimento
            ? Carbon::parse($vencimento)
            : Carbon::createFromFormat('Y-m', $competencia)
                ->day($diaVenctoPlano > 0 ? min($diaVenctoPlano, 28) : ClienteConfig::pjDiaVencimentoPadrao($cliente));

        $impostosMeta = $composicao['impostos'] ?? [];
        $retencoesCalc = $this->calcularImpostos->executar([
            'valor_bruto' => $valorBrutoParaImposto,
            'flags' => $impostosMeta['flags'] ?? [],
            'aliquotas' => $impostosMeta['aliquotas'] ?? [],
            'regras' => $impostosMeta['regras'] ?? [],
            'chave_plano' => (string) $plano['chave_sigoweb'],
        ]);

        return DB::transaction(function () use (
            $empresa,
            $competencia,
            $vencimentoData,
            $base,
            $baseCheia,
            $descontoConcedido,
            $retencoesCalc,
            $impostosMeta,
            $composicao,
            $plano,
        ) {
            $fatura = Fatura::query()->create([
                'contratante_id' => $empresa->id,
                'competencia' => $competencia,
                'data_emissao' => now()->toDateString(),
                'vencimento' => $vencimentoData->toDateString(),
                'status' => StatusFatura::Aberta,
            ]);

            $this->alocarNumero->executar($fatura);

            $ordem = 1;

            FaturaLancamento::query()->create([
                'fatura_id' => $fatura->id,
                'codigo' => 'mensalidades',
                'descricao' => 'Mensalidades',
                'natureza' => NaturezaLancamento::Base,
                'origem' => 'composicao_plano',
                'valor' => $baseCheia,
                'ordem' => $ordem++,
                'meta' => [
                    'mensalidades_qtd' => $base['mensalidades_qtd'] ?? null,
                    'vidas_qtd' => $base['vidas_qtd'] ?? null,
                    'referencia' => $composicao['referencia'] ?? null,
                    'chave_plano' => $plano['chave_sigoweb'],
                ],
            ]);

            if ($descontoConcedido > 0) {
                FaturaLancamento::query()->create([
                    'fatura_id' => $fatura->id,
                    'codigo' => 'desconto_concedido',
                    'descricao' => 'Desconto concedido do plano',
                    'natureza' => NaturezaLancamento::Retencao,
                    'origem' => 'composicao_plano',
                    'valor' => $descontoConcedido,
                    'ordem' => $ordem++,
                    'meta' => [
                        'percentual' => $base['desconto_concedido_percentual'] ?? null,
                    ],
                ]);
            }

            foreach ($retencoesCalc as $ret) {
                FaturaLancamento::query()->create([
                    'fatura_id' => $fatura->id,
                    'codigo' => $ret['codigo'],
                    'descricao' => $ret['descricao'],
                    'natureza' => NaturezaLancamento::from($ret['natureza']),
                    'origem' => 'calculo_imposto',
                    'valor' => $ret['valor'],
                    'ordem' => $ordem++,
                    'meta' => [
                        'flags' => $impostosMeta['flags'] ?? null,
                        'aliquotas' => $impostosMeta['aliquotas'] ?? null,
                    ],
                ]);
            }

            $this->recalcularTotais($fatura);

            return $fatura->load(['lancamentos', 'contratante']);
        });
    }

    /**
     * Compatibilidade com testes/lab antigo (empresa + valores manuais).
     * Preferir executarPorPlano.
     *
     * @param  array<string, float|int|string>  $valoresManuais
     */
    public function executar(
        Contratante $empresa,
        string $competencia,
        ?string $vencimento = null,
        array $valoresManuais = []
    ): Fatura {
        if ($empresa->tipo !== TipoContratante::Pj) {
            throw new DominioException('Fatura PJ só pode ser gerada para contratante tipo pj.');
        }

        $beneficiarioIds = Contratante::query()
            ->where('empresa_id', $empresa->id)
            ->where('tipo', TipoContratante::Pf)
            ->pluck('id');

        $parcelasSoma = 0.0;
        if ($beneficiarioIds->isNotEmpty()) {
            $inicio = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth()->toDateString();
            $fim = Carbon::createFromFormat('Y-m', $competencia)->endOfMonth()->toDateString();
            $parcelasSoma = round((float) Parcela::query()
                ->whereIn('status', [StatusParcela::Aberta, StatusParcela::EmCobranca, StatusParcela::Paga])
                ->whereBetween('vencimento', [$inicio, $fim])
                ->whereHas('contrato', fn ($q) => $q->whereIn('contratante_id', $beneficiarioIds))
                ->sum('valor'), 2);
        }

        $composicao = [
            'plano' => [
                'chave_sigoweb' => $empresa->chave_sigoweb,
                'tipo' => 'E',
                'nome' => $empresa->nome,
                'razao_social' => $empresa->nome,
                'documento' => $empresa->documento,
                'endereco' => $empresa->endereco,
                'bairro' => $empresa->bairro,
                'cidade' => $empresa->cidade,
                'cep' => $empresa->cep,
                'uf' => $empresa->uf,
            ],
            'competencia' => $competencia,
            'referencia' => str_replace('-', '', $competencia),
            'base' => [
                'valor_mensalidades' => $parcelasSoma,
                'valor_custo' => 0,
                'desconto_concedido_percentual' => 0,
                'desconto_concedido_valor' => 0,
                'valor_bruto' => $parcelasSoma,
                'mensalidades_qtd' => null,
                'vidas_qtd' => $beneficiarioIds->count(),
            ],
            'impostos' => [
                'flags' => [
                    'irrf' => false,
                    'iss' => false,
                    'piscofins' => false,
                    'csll' => false,
                    'inss' => false,
                ],
                'aliquotas' => [
                    'irrf' => 0,
                    'iss' => 0,
                    'piscofins' => 0,
                    'csll' => 0,
                    'inss' => 0,
                ],
                'regras' => [
                    'irrf_minimo' => 0,
                    'piscofins_csll_bruto_minimo' => 5000,
                    'inss_base_percentual' => 60,
                ],
            ],
        ];

        $fatura = $this->executarComComposicao($composicao, $vencimento);

        $ordem = (int) $fatura->lancamentos()->max('ordem');
        foreach (['ir' => 'IR', 'iss' => 'ISS'] as $cod => $desc) {
            if (! array_key_exists($cod, $valoresManuais)) {
                continue;
            }
            $valor = round((float) $valoresManuais[$cod], 2);
            if ($valor <= 0) {
                continue;
            }
            FaturaLancamento::query()->create([
                'fatura_id' => $fatura->id,
                'codigo' => $cod,
                'descricao' => $desc,
                'natureza' => NaturezaLancamento::Retencao,
                'origem' => 'manual',
                'valor' => $valor,
                'ordem' => ++$ordem,
            ]);
        }

        $this->recalcularTotais($fatura);

        return $fatura->fresh(['lancamentos', 'contratante', 'parcelas']);
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
