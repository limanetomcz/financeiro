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
use App\Models\ContratoBeneficiario;
use App\Models\Parcela;
use App\Models\ParcelaBeneficiario;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CriarContratoService
{
    /**
     * @param  array{
     *   contratante: array{
     *     chave_sigoweb: string,
     *     tipo: string,
     *     nome: string,
     *     documento?: ?string,
     *     endereco?: ?string,
     *     bairro?: ?string,
     *     cidade?: ?string,
     *     cep?: ?string,
     *     uf?: ?string
     *   },
     *   vigencia_inicio: string,
     *   vigencia_fim: string,
     *   valor_total?: float|string,
     *   quantidade_parcelas?: int,
     *   chave_plano_sigoweb: string,
     *   chave_familia_sigoweb?: ?string,
     *   beneficiarios?: list<array{
     *     chave_sigoweb: string,
     *     nome: string,
     *     valor_mensal: float|string,
     *     documento?: ?string,
     *     tipo_dependencia?: string,
     *     tipodep_sigoweb?: ?string,
     *     chave_depend_sigoweb?: ?string
     *   }>,
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

        $beneficiarios = $this->normalizarBeneficiarios($dados['beneficiarios'] ?? []);
        $valorMensalFamilia = $this->somaValorMensal($beneficiarios);

        if ($beneficiarios !== []) {
            $valorTotal = round($valorMensalFamilia * $qtd, 2);
            if (isset($dados['valor_total'])) {
                $informado = round((float) $dados['valor_total'], 2);
                if (abs($informado - $valorTotal) > 0.02) {
                    throw new DominioException(
                        "valor_total ({$informado}) diverge da soma da família × parcelas ({$valorTotal})."
                    );
                }
            }
        } else {
            $valorTotal = round((float) ($dados['valor_total'] ?? 0), 2);
            $valorMensalFamilia = $qtd > 0 ? round($valorTotal / $qtd, 2) : $valorTotal;
        }

        if ($valorTotal <= 0) {
            throw new InvalidArgumentException('valor_total deve ser > 0.');
        }

        $jaPago = (bool) ($dados['ja_pago'] ?? false);
        $inicio = Carbon::parse($dados['vigencia_inicio'])->toDateString();
        $fim = Carbon::parse($dados['vigencia_fim'])->toDateString();

        return DB::transaction(function () use (
            $dados,
            $qtd,
            $valorTotal,
            $valorMensalFamilia,
            $beneficiarios,
            $perfil,
            $modoEmissao,
            $jaPago,
            $plano,
            $inicio,
            $fim
        ) {
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

            $this->sincronizarDadosContratante($contratante, $contratanteDados, $tipo);

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
                'chave_familia_sigoweb' => $dados['chave_familia_sigoweb'] ?? null,
                'codigo' => $dados['codigo'] ?? null,
                'vigencia_inicio' => $inicio,
                'vigencia_fim' => $fim,
                'valor_total' => $valorTotal,
                'valor_mensal_familia' => $valorMensalFamilia,
                'quantidade_parcelas' => $qtd,
                'status' => StatusContrato::Ativo,
            ]);

            $membros = [];
            foreach ($beneficiarios as $i => $ben) {
                $membros[] = ContratoBeneficiario::query()->create([
                    'contrato_id' => $contrato->id,
                    'chave_sigoweb' => $ben['chave_sigoweb'],
                    'chave_depend_sigoweb' => $ben['chave_depend_sigoweb'] ?? null,
                    'nome' => $ben['nome'],
                    'documento' => $ben['documento'] ?? null,
                    'tipo_dependencia' => $ben['tipo_dependencia'],
                    'tipodep_sigoweb' => $ben['tipodep_sigoweb'] ?? null,
                    'valor_mensal' => $ben['valor_mensal'],
                    'ordem' => $i + 1,
                ]);
            }

            $valoresParcela = $this->ratearValor($valorTotal, $qtd);
            $composicaoPorParcela = $this->ratearComposicaoPorParcela($membros, $qtd, $valoresParcela);

            $primeiroVencimento = Carbon::parse(
                $dados['primeiro_vencimento']
                    ?? ($perfil === PerfilPagamento::AVista ? $fim : $inicio)
            );
            $hoje = Carbon::today();

            foreach ($valoresParcela as $i => $valorParcela) {
                $vencimento = $primeiroVencimento->copy()->addMonthsNoOverflow($i);
                [$status, $emitidaEm, $pagoEm] = $this->definirParcelaInicial(
                    $perfil,
                    $modoEmissao,
                    $vencimento,
                    $hoje,
                    $jaPago && $perfil === PerfilPagamento::AVista
                );

                $parcela = Parcela::query()->create([
                    'contrato_id' => $contrato->id,
                    'numero' => $i + 1,
                    'vencimento' => $vencimento->toDateString(),
                    'emitida_em' => $emitidaEm,
                    'valor' => $valorParcela,
                    'status' => $status,
                    'pago_em' => $pagoEm,
                ]);

                foreach ($composicaoPorParcela[$i] as $comp) {
                    ParcelaBeneficiario::query()->create([
                        'parcela_id' => $parcela->id,
                        'contrato_beneficiario_id' => $comp['contrato_beneficiario_id'],
                        'chave_sigoweb' => $comp['chave_sigoweb'],
                        'nome' => $comp['nome'],
                        'documento' => $comp['documento'],
                        'tipo_dependencia' => $comp['tipo_dependencia'],
                        'valor' => $comp['valor'],
                    ]);
                }
            }

            return $contrato->load(['contratante', 'beneficiarios', 'parcelas.beneficiarios']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return list<array{
     *   chave_sigoweb: string,
     *   nome: string,
     *   valor_mensal: float,
     *   documento: ?string,
     *   tipo_dependencia: string,
     *   tipodep_sigoweb: ?string,
     *   chave_depend_sigoweb: ?string
     * }>
     */
    private function normalizarBeneficiarios(array $itens): array
    {
        if ($itens === []) {
            return [];
        }

        $normalizados = [];
        $chaves = [];

        foreach ($itens as $item) {
            $chave = trim((string) ($item['chave_sigoweb'] ?? ''));
            $nome = trim((string) ($item['nome'] ?? ''));
            $valorMensal = round((float) ($item['valor_mensal'] ?? 0), 2);

            if ($chave === '' || $nome === '') {
                throw new DominioException('Cada beneficiário precisa de chave_sigoweb e nome.');
            }
            if ($valorMensal < 0) {
                throw new DominioException("Valor mensal inválido para {$nome}.");
            }
            if (isset($chaves[$chave])) {
                throw new DominioException("Beneficiário duplicado na composição: {$chave}.");
            }
            $chaves[$chave] = true;

            $tipodep = isset($item['tipodep_sigoweb']) ? (string) $item['tipodep_sigoweb'] : null;
            $tipo = $item['tipo_dependencia'] ?? null;
            if (! in_array($tipo, ['titular', 'dependente'], true)) {
                $tipo = $tipodep === '3' ? 'titular' : 'dependente';
            }

            $normalizados[] = [
                'chave_sigoweb' => $chave,
                'nome' => $nome,
                'valor_mensal' => $valorMensal,
                'documento' => isset($item['documento']) ? (string) $item['documento'] : null,
                'tipo_dependencia' => $tipo,
                'tipodep_sigoweb' => $tipodep,
                'chave_depend_sigoweb' => isset($item['chave_depend_sigoweb'])
                    ? (string) $item['chave_depend_sigoweb']
                    : null,
            ];
        }

        usort($normalizados, function ($a, $b) {
            if ($a['tipo_dependencia'] === $b['tipo_dependencia']) {
                return strcmp($a['nome'], $b['nome']);
            }

            return $a['tipo_dependencia'] === 'titular' ? -1 : 1;
        });

        return $normalizados;
    }

    /**
     * @param  list<array{valor_mensal: float}>  $beneficiarios
     */
    private function somaValorMensal(array $beneficiarios): float
    {
        return round(array_sum(array_column($beneficiarios, 'valor_mensal')), 2);
    }

    /**
     * Rateia o valor de cada integrante nas N parcelas (centavos no último mês).
     *
     * @param  list<ContratoBeneficiario>  $membros
     * @param  list<float>  $valoresParcela
     * @return list<list<array<string, mixed>>>
     */
    private function ratearComposicaoPorParcela(array $membros, int $qtd, array $valoresParcela): array
    {
        if ($membros === []) {
            return array_fill(0, $qtd, []);
        }

        /** @var list<list<float>> $porMembro */
        $porMembro = [];
        foreach ($membros as $membro) {
            $totalMembro = round((float) $membro->valor_mensal * $qtd, 2);
            $porMembro[] = $this->ratearValor($totalMembro, $qtd);
        }

        $porParcela = [];
        for ($i = 0; $i < $qtd; $i++) {
            $linha = [];
            $soma = 0.0;
            foreach ($membros as $mi => $membro) {
                $valor = $porMembro[$mi][$i];
                $soma = round($soma + $valor, 2);
                $linha[] = [
                    'contrato_beneficiario_id' => $membro->id,
                    'chave_sigoweb' => $membro->chave_sigoweb,
                    'nome' => $membro->nome,
                    'documento' => $membro->documento,
                    'tipo_dependencia' => $membro->tipo_dependencia,
                    'valor' => $valor,
                ];
            }

            // Ajuste fino: composição deve bater com valor da parcela
            $diff = round($valoresParcela[$i] - $soma, 2);
            if (abs($diff) >= 0.01 && $linha !== []) {
                $ultimo = count($linha) - 1;
                $linha[$ultimo]['valor'] = round($linha[$ultimo]['valor'] + $diff, 2);
            }

            $porParcela[] = $linha;
        }

        return $porParcela;
    }

    /**
     * Atualiza nome/documento/endereço quando o Sigoweb envia dados mais recentes.
     *
     * @param  array<string, mixed>  $dados
     */
    private function sincronizarDadosContratante(Contratante $contratante, array $dados, TipoContratante $tipo): void
    {
        $updates = [
            'tipo' => $tipo,
            'nome' => $dados['nome'] ?? $contratante->nome,
            'documento' => array_key_exists('documento', $dados)
                ? ($dados['documento'] !== null && $dados['documento'] !== ''
                    ? preg_replace('/\D/', '', (string) $dados['documento'])
                    : $contratante->documento)
                : $contratante->documento,
        ];

        foreach (['endereco', 'bairro', 'cidade', 'cep', 'uf'] as $campo) {
            if (! array_key_exists($campo, $dados)) {
                continue;
            }
            $valor = $dados[$campo];
            if ($valor === null) {
                continue;
            }
            $valor = is_string($valor) ? trim($valor) : $valor;
            if ($valor === '') {
                continue;
            }
            if ($campo === 'cep') {
                $valor = preg_replace('/\D/', '', (string) $valor) ?: $valor;
            }
            if ($campo === 'uf') {
                $valor = mb_strtoupper(mb_substr((string) $valor, 0, 2));
            }
            $updates[$campo] = $valor;
        }

        if (array_key_exists('empresa_id', $dados) && $dados['empresa_id']) {
            $empresa = Contratante::query()->find($dados['empresa_id']);
            if (! $empresa || $empresa->tipo !== TipoContratante::Pj) {
                throw new DominioException('empresa_id deve apontar para um contratante PJ.');
            }
            if ($tipo === TipoContratante::Pj) {
                throw new DominioException('Contratante PJ não pode ter empresa_id.');
            }
            $updates['empresa_id'] = $empresa->id;
        }

        $contratante->fill($updates);
        if ($contratante->isDirty()) {
            $contratante->save();
        }
    }

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
