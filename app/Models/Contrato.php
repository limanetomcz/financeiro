<?php

namespace App\Models;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusContrato;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends Model
{
    use BelongsToCliente;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'contratos';

    protected $fillable = [
        'cliente_id',
        'contratante_id',
        'tipo',
        'perfil_pagamento',
        'modo_emissao',
        'renovado_de_contrato_id',
        'chave_plano_sigoweb',
        'chave_familia_sigoweb',
        'codigo',
        'vigencia_inicio',
        'vigencia_fim',
        'valor_total',
        'valor_mensal_familia',
        'quantidade_parcelas',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'valor_total' => 'decimal:2',
            'valor_mensal_familia' => 'decimal:2',
            'status' => StatusContrato::class,
            'perfil_pagamento' => PerfilPagamento::class,
            'modo_emissao' => ModoEmissao::class,
        ];
    }

    public function contratante(): BelongsTo
    {
        return $this->belongsTo(Contratante::class);
    }

    public function renovadoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renovado_de_contrato_id');
    }

    public function beneficiarios(): HasMany
    {
        return $this->hasMany(ContratoBeneficiario::class)->orderBy('ordem');
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(Parcela::class)->orderBy('numero');
    }
}
