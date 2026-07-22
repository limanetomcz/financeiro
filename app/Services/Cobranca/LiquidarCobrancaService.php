<?php

namespace App\Services\Cobranca;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Enums\StatusParcela;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Fatura;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LiquidarCobrancaService
{
    public function executar(Cobranca $cobranca, ?string $pagoEm = null): Cobranca
    {
        return DB::transaction(function () use ($cobranca, $pagoEm) {
            $cobranca = Cobranca::query()->whereKey($cobranca->id)->lockForUpdate()->firstOrFail();

            if ($cobranca->status !== StatusCobranca::Aberta) {
                throw new DominioException('Somente cobranças abertas podem ser liquidadas.');
            }

            $quando = $pagoEm ? Carbon::parse($pagoEm) : now();

            $cobranca->update([
                'status' => StatusCobranca::Paga,
                'pago_em' => $quando,
            ]);

            foreach ($cobranca->parcelas()->lockForUpdate()->get() as $parcela) {
                $parcela->update([
                    'status' => StatusParcela::Paga,
                    'pago_em' => $quando,
                ]);
            }

            $faturas = Fatura::query()
                ->where('cobranca_id', $cobranca->id)
                ->lockForUpdate()
                ->get();

            foreach ($faturas as $fatura) {
                $fatura->update(['status' => StatusFatura::Paga]);

                // Pagar a fatura PJ liquida as parcelas dos beneficiários que a compuseram
                foreach ($fatura->parcelas()->lockForUpdate()->get() as $parcela) {
                    if ($parcela->status !== StatusParcela::Paga) {
                        $parcela->update([
                            'status' => StatusParcela::Paga,
                            'pago_em' => $quando,
                        ]);
                    }
                }
            }

            return $cobranca->fresh('parcelas');
        });
    }
}
