<?php

namespace App\Models;

use App\Enums\TipoLocalPagamento;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalPagamento extends Model
{
    use BelongsToCliente;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'locais_pagamento';

    protected $fillable = [
        'cliente_id',
        'codigo',
        'codigo_legado',
        'descricao',
        'tipo',
        'ativo',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoLocalPagamento::class,
            'ativo' => 'boolean',
        ];
    }

    public function taxas(): HasMany
    {
        return $this->hasMany(TaxaLocalPagamento::class);
    }

    public function exigeTaxa(): bool
    {
        return $this->tipo === TipoLocalPagamento::Cartao;
    }
}
