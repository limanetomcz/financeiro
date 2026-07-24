<?php

namespace App\Services\Fatura;

use App\Enums\StatusFatura;
use App\Models\Fatura;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListarFaturasService
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function executar(array $filtros, int $perPage = 20): LengthAwarePaginator
    {
        $query = Fatura::query()->with(['contratante']);

        if (! empty($filtros['incluir_excluidas'])) {
            $query->withTrashed();
        }

        if (! empty($filtros['somente_excluidas'])) {
            $query->onlyTrashed();
        }

        $this->aplicarFiltros($query, $filtros);

        $ordenar = (string) ($filtros['ordenar'] ?? 'recentes');
        match ($ordenar) {
            'vencimento' => $query->orderByDesc('vencimento')->orderByDesc('created_at'),
            'emissao' => $query->orderByDesc('data_emissao')->orderByDesc('created_at'),
            'numero' => $query->orderByDesc('numero'),
            default => $query->latest(),
        };

        $perPage = max(1, min(100, $perPage));

        return $query->paginate($perPage)->through(function (Fatura $fatura) {
            $arr = $fatura->toArray();
            $arr['status_label'] = $fatura->status?->label();
            $arr['excluida'] = $fatura->trashed();

            return $arr;
        });
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (! empty($filtros['numero'])) {
            $numero = trim((string) $filtros['numero']);
            $query->where(function (Builder $q) use ($numero) {
                $q->where('numero', $numero)
                    ->orWhere('numero', 'like', '%'.$numero.'%');
            });
        }

        if (! empty($filtros['chave_plano_sigoweb'])) {
            $query->where('chave_plano_sigoweb', trim((string) $filtros['chave_plano_sigoweb']));
        }

        if (! empty($filtros['contratante_id'])) {
            $query->where('contratante_id', $filtros['contratante_id']);
        }

        if (! empty($filtros['competencia'])) {
            $query->where('competencia', $filtros['competencia']);
        }

        if (! empty($filtros['status'])) {
            $status = $filtros['status'];
            if (is_string($status) && str_contains($status, ',')) {
                $status = array_filter(array_map('trim', explode(',', $status)));
            }
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        // Espelha "Apenas abertas" do Sigoweb: não pagas / não canceladas.
        if (! empty($filtros['apenas_abertas'])) {
            $query->whereIn('status', [
                StatusFatura::Aberta,
                StatusFatura::EmCobranca,
                StatusFatura::Rascunho,
                StatusFatura::Processando,
            ]);
        }

        if (! empty($filtros['data_emissao_de'])) {
            $query->whereDate('data_emissao', '>=', $filtros['data_emissao_de']);
        }
        if (! empty($filtros['data_emissao_ate'])) {
            $query->whereDate('data_emissao', '<=', $filtros['data_emissao_ate']);
        }

        if (! empty($filtros['vencimento_de'])) {
            $query->whereDate('vencimento', '>=', $filtros['vencimento_de']);
        }
        if (! empty($filtros['vencimento_ate'])) {
            $query->whereDate('vencimento', '<=', $filtros['vencimento_ate']);
        }

        if (array_key_exists('com_cobranca', $filtros) && $filtros['com_cobranca'] !== null && $filtros['com_cobranca'] !== '') {
            if (filter_var($filtros['com_cobranca'], FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('cobranca_id');
            } else {
                $query->whereNull('cobranca_id');
            }
        }

        if (isset($filtros['valor_liquido_min']) && $filtros['valor_liquido_min'] !== '' && $filtros['valor_liquido_min'] !== null) {
            $query->where('valor_liquido', '>=', (float) $filtros['valor_liquido_min']);
        }
        if (isset($filtros['valor_liquido_max']) && $filtros['valor_liquido_max'] !== '' && $filtros['valor_liquido_max'] !== null) {
            $query->where('valor_liquido', '<=', (float) $filtros['valor_liquido_max']);
        }

        $nome = trim((string) ($filtros['contratante_nome'] ?? ''));
        $documento = preg_replace('/\D/', '', (string) ($filtros['contratante_documento'] ?? '')) ?: '';

        if ($nome !== '' || $documento !== '') {
            $query->whereHas('contratante', function (Builder $q) use ($nome, $documento) {
                if ($nome !== '') {
                    $q->where('nome', 'like', '%'.$nome.'%');
                }
                if ($documento !== '') {
                    $q->where('documento', 'like', '%'.$documento.'%');
                }
            });
        }
    }
}
