<?php

namespace App\Models;

use App\Enums\BandeiraTaxaLocal;
use App\Enums\ModalidadeTaxaLocal;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxaLocalPagamento extends Model
{
    use BelongsToCliente;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'taxas_local_pagamento';

    protected $fillable = [
        'cliente_id',
        'local_pagamento_id',
        'codigo_legado',
        'descricao',
        'modalidade',
        'bandeira',
        'taxa_percentual',
        'dias_credito',
        'vigencia_inicio',
        'vigencia_fim',
        'ativo',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'modalidade' => ModalidadeTaxaLocal::class,
            'bandeira' => BandeiraTaxaLocal::class,
            'taxa_percentual' => 'decimal:4',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function localPagamento(): BelongsTo
    {
        return $this->belongsTo(LocalPagamento::class);
    }

    public function vigenteEm(?string $data = null): bool
    {
        $dia = $data ? \Carbon\Carbon::parse($data)->toDateString() : now()->toDateString();

        if ($this->vigencia_inicio && $this->vigencia_inicio->toDateString() > $dia) {
            return false;
        }

        if ($this->vigencia_fim && $this->vigencia_fim->toDateString() < $dia) {
            return false;
        }

        return true;
    }
}
