<?php

namespace App\Services\Cobranca;

use App\Enums\StatusCobranca;
use App\Enums\StatusParcela;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
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

            return $cobranca->fresh('parcelas');
        });
    }
}
