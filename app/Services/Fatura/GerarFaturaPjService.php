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
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GerarFaturaPjService
{
    /**
     * @param  array<string, float|int|string>  $valoresManuais  ex.: ['ir' => 333.33, 'iss' => 222.22]
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

        if (! preg_match('/^\d{4}-\d{2}$/', $competencia)) {
            throw new DominioException('Competência deve estar no formato YYYY-MM.');
        }

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

        $vencimentoData = $vencimento
            ? Carbon::parse($vencimento)
            : Carbon::createFromFormat('Y-m', $competencia)
                ->day(ClienteConfig::pjDiaVencimentoPadrao($cliente));

        return DB::transaction(function () use ($empresa, $competencia, $vencimentoData, $valoresManuais, $cliente) {
            [$parcelas, $somaParcelas] = $this->coletarParcelasDaCompetencia($empresa, $competencia);

            $fatura = Fatura::query()->create([
                'contratante_id' => $empresa->id,
                'competencia' => $competencia,
                'vencimento' => $vencimentoData->toDateString(),
                'status' => StatusFatura::Aberta,
            ]);

            $bruto = 0.0;
            $retencoes = 0.0;
            $acrescimos = 0.0;

            foreach (ClienteConfig::pjLancamentos($cliente) as $def) {
                $natureza = NaturezaLancamento::from($def['natureza']);
                $origem = $def['origem'] ?? 'manual';
                $codigo = $def['codigo'];

                $valor = match ($origem) {
                    'soma_parcelas' => $somaParcelas,
                    default => round((float) ($valoresManuais[$codigo] ?? 0), 2),
                };

                if ($origem !== 'soma_parcelas' && $valor <= 0 && ! array_key_exists($codigo, $valoresManuais)) {
                    // lançamento manual ativo sem valor informado → linha 0 (operador pode ajustar depois)
                    $valor = 0.0;
                }

                FaturaLancamento::query()->create([
                    'fatura_id' => $fatura->id,
                    'codigo' => $codigo,
                    'descricao' => $def['descricao'] ?? $codigo,
                    'natureza' => $natureza,
                    'origem' => $origem,
                    'valor' => $valor,
                    'ordem' => (int) ($def['ordem'] ?? 1),
                    'meta' => [
                        'parcelas_qtd' => $origem === 'soma_parcelas' ? $parcelas->count() : null,
                    ],
                ]);

                match ($natureza) {
                    NaturezaLancamento::Base => $bruto += $valor,
                    NaturezaLancamento::Retencao => $retencoes += $valor,
                    NaturezaLancamento::Acrescimo => $acrescimos += $valor,
                    NaturezaLancamento::Informativo => null,
                };
            }

            $liquido = round($bruto - $retencoes + $acrescimos, 2);

            $fatura->update([
                'valor_bruto' => round($bruto, 2),
                'valor_retencoes' => round($retencoes, 2),
                'valor_acrescimos' => round($acrescimos, 2),
                'valor_liquido' => $liquido,
            ]);

            if ($parcelas->isNotEmpty()) {
                $fatura->parcelas()->attach($parcelas->pluck('id')->all());
            }

            return $fatura->load(['lancamentos', 'parcelas', 'contratante']);
        });
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, Parcela>, 1: float}
     */
    private function coletarParcelasDaCompetencia(Contratante $empresa, string $competencia): array
    {
        $beneficiarioIds = Contratante::query()
            ->where('empresa_id', $empresa->id)
            ->where('tipo', TipoContratante::Pf)
            ->pluck('id');

        if ($beneficiarioIds->isEmpty()) {
            return [collect(), 0.0];
        }

        $inicio = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth()->toDateString();
        $fim = Carbon::createFromFormat('Y-m', $competencia)->endOfMonth()->toDateString();

        // Parcelas já usadas em outra fatura não-cancelada não entram de novo
        $parcelas = Parcela::query()
            ->whereIn('status', [StatusParcela::Aberta, StatusParcela::EmCobranca, StatusParcela::Paga])
            ->whereBetween('vencimento', [$inicio, $fim])
            ->whereHas('contrato', fn ($q) => $q->whereIn('contratante_id', $beneficiarioIds))
            ->whereDoesntHave('faturas', fn ($q) => $q->where('status', '!=', StatusFatura::Cancelada->value))
            ->get();

        $soma = round((float) $parcelas->sum('valor'), 2);

        return [$parcelas, $soma];
    }
}
