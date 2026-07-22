<?php

namespace App\Services\Fatura;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Enums\TipoCobranca;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Fatura;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Illuminate\Support\Facades\DB;

class EmitirCobrancaFaturaPjService
{
    public function executar(Fatura $fatura, ?string $meio = 'boleto'): Cobranca
    {
        return DB::transaction(function () use ($fatura, $meio) {
            $fatura = Fatura::query()->whereKey($fatura->id)->lockForUpdate()->firstOrFail();

            if (! in_array($fatura->status, [StatusFatura::Aberta, StatusFatura::Rascunho], true)) {
                throw new DominioException('Só é possível emitir cobrança de fatura aberta ou rascunho.');
            }

            if ($fatura->cobranca_id) {
                throw new DominioException('Fatura já possui cobrança vinculada.');
            }

            $cliente = ClienteContext::get();
            $usa = ClienteConfig::pjBoletoUsaValor($cliente);
            $valor = $usa === 'bruto'
                ? (float) $fatura->valor_bruto
                : (float) $fatura->valor_liquido;

            if ($valor <= 0) {
                throw new DominioException('Valor da cobrança da fatura deve ser maior que zero.');
            }

            $cobranca = Cobranca::query()->create([
                'contratante_id' => $fatura->contratante_id,
                'tipo' => TipoCobranca::Simples,
                'valor_principal' => $valor,
                'valor_juros' => 0,
                'valor_multa' => 0,
                'valor' => $valor,
                'vencimento' => $fatura->vencimento,
                'status' => StatusCobranca::Aberta,
                'meio' => $meio,
            ]);

            $fatura->update([
                'cobranca_id' => $cobranca->id,
                'status' => StatusFatura::EmCobranca,
            ]);

            return $cobranca;
        });
    }
}
