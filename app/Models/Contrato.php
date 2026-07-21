<?php

namespace App\Models;

use App\Enums\StatusContrato;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends Model
{
    use BelongsToCliente;
    use HasUuids;

    protected $table = 'contratos';

    protected $fillable = [
        'cliente_id',
        'contratante_id',
        'tipo',
        'renovado_de_contrato_id',
        'chave_plano_sigoweb',
        'codigo',
        'vigencia_inicio',
        'vigencia_fim',
        'valor_total',
        'quantidade_parcelas',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'valor_total' => 'decimal:2',
            'status' => StatusContrato::class,
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

    public function parcelas(): HasMany
    {
        return $this->hasMany(Parcela::class)->orderBy('numero');
    }
}
