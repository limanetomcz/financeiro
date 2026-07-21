<?php

namespace App\Services\Contrato;

use App\Enums\StatusContrato;
use App\Enums\StatusParcela;
use App\Enums\TipoContratante;
use App\Models\Contratante;
use App\Models\Contrato;
use App\Models\Parcela;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CriarContratoService
{
    /**
     * @param  array{
     *   contratante: array{chave_sigoweb: string, tipo: string, nome: string, documento?: ?string},
     *   vigencia_inicio: string,
     *   vigencia_fim: string,
     *   valor_total: float|string,
     *   quantidade_parcelas: int,
     *   chave_plano_sigoweb?: ?string,
     *   codigo?: ?string,
     *   renovado_de_contrato_id?: ?string,
     *   primeiro_vencimento?: ?string,
     * }  $dados
     */
    public function executar(array $dados): Contrato
    {
        $qtd = (int) $dados['quantidade_parcelas'];
        if ($qtd < 1) {
            throw new InvalidArgumentException('quantidade_parcelas deve ser >= 1.');
        }

        $valorTotal = round((float) $dados['valor_total'], 2);
        if ($valorTotal <= 0) {
            throw new InvalidArgumentException('valor_total deve ser > 0.');
        }

        return DB::transaction(function () use ($dados, $qtd, $valorTotal) {
            $contratanteDados = $dados['contratante'];
            $contratante = Contratante::query()->firstOrCreate(
                [
                    'cliente_id' => ClienteContext::id(),
                    'chave_sigoweb' => $contratanteDados['chave_sigoweb'],
                ],
                [
                    'tipo' => TipoContratante::from($contratanteDados['tipo']),
                    'nome' => $contratanteDados['nome'],
                    'documento' => $contratanteDados['documento'] ?? null,
                ]
            );

            $contrato = Contrato::query()->create([
                'contratante_id' => $contratante->id,
                'renovado_de_contrato_id' => $dados['renovado_de_contrato_id'] ?? null,
                'chave_plano_sigoweb' => $dados['chave_plano_sigoweb'] ?? null,
                'codigo' => $dados['codigo'] ?? null,
                'vigencia_inicio' => $dados['vigencia_inicio'],
                'vigencia_fim' => $dados['vigencia_fim'],
                'valor_total' => $valorTotal,
                'quantidade_parcelas' => $qtd,
                'status' => StatusContrato::Ativo,
            ]);

            $valores = $this->ratearValor($valorTotal, $qtd);
            $primeiroVencimento = Carbon::parse($dados['primeiro_vencimento'] ?? $dados['vigencia_inicio']);

            foreach ($valores as $i => $valorParcela) {
                Parcela::query()->create([
                    'contrato_id' => $contrato->id,
                    'numero' => $i + 1,
                    'vencimento' => $primeiroVencimento->copy()->addMonthsNoOverflow($i)->toDateString(),
                    'valor' => $valorParcela,
                    'status' => StatusParcela::Aberta,
                ]);
            }

            return $contrato->load(['contratante', 'parcelas']);
        });
    }

    /**
     * @return list<float>
     */
    private function ratearValor(float $total, int $qtd): array
    {
        $centavos = (int) round($total * 100);
        $base = intdiv($centavos, $qtd);
        $resto = $centavos % $qtd;
        $valores = [];

        for ($i = 0; $i < $qtd; $i++) {
            $parte = $base + ($i < $resto ? 1 : 0);
            $valores[] = round($parte / 100, 2);
        }

        return $valores;
    }
}
