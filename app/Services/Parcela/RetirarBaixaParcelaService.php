<?php

namespace App\Services\Parcela;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Enums\StatusParcela;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Fatura;
use App\Models\Parcela;
use App\Services\Auditoria\AuditoriaFinanceira;
use App\Support\Auth\OperadorAtual;
use Illuminate\Support\Facades\DB;

/**
 * Retira baixa da parcela (e da cobrança paga vinculada).
 */
class RetirarBaixaParcelaService
{
    public function __construct(
        private readonly AuditoriaFinanceira $auditoria,
    ) {}

    /**
     * @param  array{operador?: array{login?: string, nome?: ?string}}  $opcoes
     */
    public function executar(string $parcelaId, array $opcoes = []): Cobranca
    {
        $operador = OperadorAtual::resolver($opcoes['operador'] ?? null);
        $agora = now();

        return DB::transaction(function () use ($parcelaId, $operador, $agora) {
            $parcela = Parcela::query()->whereKey($parcelaId)->lockForUpdate()->firstOrFail();

            if ($parcela->status !== StatusParcela::Paga) {
                throw new DominioException('Somente parcelas pagas podem ter a baixa retirada.');
            }

            $cobranca = $parcela->cobrancas()
                ->where('status', StatusCobranca::Paga->value)
                ->orderByDesc('pago_em')
                ->lockForUpdate()
                ->first();

            if (! $cobranca) {
                throw new DominioException('Não há cobrança paga vinculada a esta parcela.');
            }

            $cobranca = Cobranca::query()->whereKey($cobranca->id)->lockForUpdate()->firstOrFail();

            $auditoriaRetirada = [
                'baixa_retirada_por' => $operador['login'],
                'baixa_retirada_por_nome' => $operador['nome'],
                'baixa_retirada_em' => $agora,
            ];

            $cobranca->update(array_merge([
                'status' => StatusCobranca::Aberta,
                'pago_em' => null,
                'baixado_por' => null,
                'baixado_por_nome' => null,
                // limpa snapshot de pagamento; canal pode ser escolhido de novo na próxima baixa
                'local_pagamento_id' => null,
                'taxa_local_pagamento_id' => null,
                'local_pagamento' => null,
                'local_pagamento_descricao' => null,
                'taxa_percentual' => null,
                'valor_taxa' => null,
                'modalidade' => null,
                'bandeira' => null,
                'meio' => null,
            ], $auditoriaRetirada));

            foreach ($cobranca->parcelas()->lockForUpdate()->get() as $parcelaCobranca) {
                $parcelaCobranca->update(array_merge([
                    'status' => StatusParcela::EmCobranca,
                    'pago_em' => null,
                    'baixado_por' => null,
                    'baixado_por_nome' => null,
                ], $auditoriaRetirada));
            }

            $faturas = Fatura::query()
                ->where('cobranca_id', $cobranca->id)
                ->lockForUpdate()
                ->get();

            foreach ($faturas as $fatura) {
                if ($fatura->status === StatusFatura::Paga) {
                    $fatura->update(['status' => StatusFatura::Aberta]);
                }
            }

            $this->auditoria->registrar('parcela.baixa_retirada', [
                'parcela_id' => $parcelaId,
                'cobranca_id' => $cobranca->id,
                'operador' => $operador,
            ]);

            return $cobranca->fresh(['parcelas']);
        });
    }
}
