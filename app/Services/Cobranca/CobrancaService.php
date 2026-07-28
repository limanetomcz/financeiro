<?php

namespace App\Services\Cobranca;

use App\Enums\StatusCobranca;
use App\Enums\StatusParcela;
use App\Enums\TipoCobranca;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Parcela;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CobrancaService
{
    public static function buscarHistoricoPagamentos($clienteId): Collection
    {
        return Cobranca::query()
            ->where('cliente_id', $clienteId)
            ->where('status', 'paga')
            ->whereNotNull('pago_em')
            ->orderBy('pago_em', 'desc')
            ->limit(10)
            ->get();
    }
    // Removed extraneous braces
}
