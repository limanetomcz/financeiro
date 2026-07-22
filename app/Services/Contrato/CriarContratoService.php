<?php

namespace App\Services\Contrato;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusContrato;
use App\Enums\StatusParcela;
use App\Enums\TipoContratante;
use App\Exceptions\DominioException;
use App\Models\Contratante;
use App\Models\Contrato;
use App\Models\Parcela;
use App\Support\Cliente\ClienteConfig;
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
     *   quantidade_parcelas?: int,
     *   chave_plano_sigoweb: string,
     *   codigo?: ?string,
     *   renovado_de_contrato_id?: ?string,
     *   primeiro_vencimento?: ?string,
     *   perfil_pagamento?: string,
     *   modo_emissao?: string,
     *   modo_geracao?: string,
     *   ja_pago?: bool,
     * }  $dados
     */
    public function executar(array $dados): Contrato
    {
        $plano = trim((string) ($dados['chave_plano_sigoweb'] ?? ''));
        if ($plano === '') {
            throw new DominioException('Informe o plano (chave_plano_sigoweb).');
        }

        $perfil = PerfilPagamento::from(
            $dados['perfil_pagamento'] ?? PerfilPagamento::BoletoParcelado->value
        );

        $modoEmissao = $this->resolverModoEmissao($dados, $perfil);

        $qtd = $perfil === PerfilPagamento::AVista
            ? 1
            : (int) ($dados['quantidade_parcelas'] ?? 0);

        if ($qtd < 1) {
            throw new InvalidArgumentException('quantidade_parcelas deve ser >= 1.');
        }

        $valorTotal = round((float) $dados['valor_total'], 2);
        if ($valorTotal <= 0) {
            throw new InvalidArgumentException('valor_total deve ser > 0.');
        }

        $jaPago = (bool) ($dados['ja_pago'] ?? false);
        $inicio = Carbon::parse($dados['vigencia_inicio'])->toDateString();
        $fim = Carbon::parse($dados['vigencia_fim'])->toDateString();

        return DB::transaction(function () use ($dados, $qtd, $valorTotal, $perfil, $modoEmissao, $jaPago, $plano, $inicio, $fim) {
            $cliente = ClienteContext::get();
            $contratanteDados = $dados['contratante'];
            $tipo = TipoContratante::from($contratanteDados['tipo']);

            $contratante = Contratante::query()->firstOrCreate(
                [
                    'cliente_id' => $cliente->id,
                    'chave_sigoweb' => $contratanteDados['chave_sigoweb'],
                ],
                [
                    'tipo' => $tipo,
                    'nome' => $contratanteDados['nome'],
                    'documento' => $contratanteDados['documento'] ?? null,
                ]
            );

            $this->garantirSemSobreposicaoMesmoPlano(
                $contratante->id,
                $plano,
                $inicio,
                $fim
            );

            $contrato = Contrato::query()->create([
                'contratante_id' => $contratante->id,
                'tipo' => $tipo->value,
                'perfil_pagamento' => $perfil,
                'modo_emissao' => $modoEmissao,
                'renovado_de_contrato_id' => $dados['renovado_de_contrato_id'] ?? null,
                'chave_plano_sigoweb' => $plano,
                'codigo' => $dados['codigo'] ?? null,
                'vigencia_inicio' => $inicio,
                'vigencia_fim' => $fim,
                'valor_total' => $valorTotal,
                'quantidade_parcelas' => $qtd,
                'status' => StatusContrato::Ativo,
            ]);

            $valores = $this->ratearValor($valorTotal, $qtd);
            $primeiroVencimento = Carbon::parse(
                $dados['primeiro_vencimento']
                    ?? ($perfil === PerfilPagamento::AVista ? $fim : $inicio)
            );
            $hoje = Carbon::today();

            foreach ($valores as $i => $valorParcela) {
                $vencimento = $primeiroVencimento->copy()->addMonthsNoOverflow($i);
                [$status, $emitidaEm, $pagoEm] = $this->definirParcelaInicial(
                    $perfil,
                    $modoEmissao,
                    $vencimento,
                    $hoje,
                    $jaPago && $perfil === PerfilPagamento::AVista
                );

                Parcela::query()->create([
                    'contrato_id' => $contrato->id,
                    'numero' => $i + 1,
                    'vencimento' => $vencimento->toDateString(),
                    'emitida_em' => $emitidaEm,
                    'valor' => $valorParcela,
                    'status' => $status,
                    'pago_em' => $pagoEm,
                ]);
            }

            return $contrato->load(['contratante', 'parcelas']);
        });
    }

    /**
     * Mesma pessoa (contratante) + mesmo plano + vigência sobreposta = bloqueado.
     * Planos diferentes no mesmo período são permitidos (CPF com mais de um contrato).
     */
    private function garantirSemSobreposicaoMesmoPlano(
        string $contratanteId,
        string $plano,
        string $inicio,
        string $fim
    ): void {
        $conflito = Contrato::query()
            ->where('contratante_id', $contratanteId)
            ->where('chave_plano_sigoweb', $plano)
            ->whereIn('status', [
                StatusContrato::Ativo->value,
                StatusContrato::Suspenso->value,
                StatusContrato::Rascunho->value,
            ])
            ->whereDate('vigencia_inicio', '<=', $fim)
            ->whereDate('vigencia_fim', '>=', $inicio)
            ->orderByDesc('vigencia_inicio')
            ->first();

        if (! $conflito) {
            return;
        }

        throw new DominioException(
            sprintf(
                'Já existe financeiro ativo para este contratante no plano %s no período %s a %s (contrato %s).',
                $plano,
                $conflito->vigencia_inicio->format('d/m/Y'),
                $conflito->vigencia_fim->format('d/m/Y'),
                $conflito->id
            )
        );
    }

    /**
     * @return array{0: StatusParcela, 1: ?string, 2: ?\Carbon\CarbonInterface}
     */
    private function definirParcelaInicial(
        PerfilPagamento $perfil,
        ModoEmissao $modo,
        Carbon $vencimento,
        Carbon $hoje,
        bool $jaPagoAVista
    ): array {
        if ($jaPagoAVista) {
            return [StatusParcela::Paga, $hoje->toDateString(), $hoje->copy()];
        }

        if ($modo === ModoEmissao::Imediata) {
            return [StatusParcela::Aberta, $hoje->toDateString(), null];
        }

        // Escalonada: só entra no CR no mês do vencimento (emissão alinhada ao mês da parcela)
        if ($vencimento->format('Y-m') <= $hoje->format('Y-m')) {
            return [StatusParcela::Aberta, $vencimento->copy()->startOfMonth()->toDateString(), null];
        }

        return [StatusParcela::Prevista, null, null];
    }

    private function resolverModoEmissao(array $dados, PerfilPagamento $perfil): ModoEmissao
    {
        if (! empty($dados['modo_emissao'])) {
            return ModoEmissao::from($dados['modo_emissao']);
        }

        // Compat: modo_geracao antigo
        if (! empty($dados['modo_geracao'])) {
            return match ($dados['modo_geracao']) {
                ClienteConfig::MODO_TODAS_ABERTAS => ModoEmissao::Imediata,
                default => ModoEmissao::Escalonada,
            };
        }

        if ($perfil === PerfilPagamento::AVista) {
            return ModoEmissao::Imediata;
        }

        $cliente = ClienteContext::get();
        $padrao = ClienteConfig::modoGeracaoParcelas($cliente);

        return $padrao === ClienteConfig::MODO_TODAS_ABERTAS
            ? ModoEmissao::Imediata
            : ModoEmissao::Escalonada;
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
