<?php

namespace App\Models;

use App\Enums\StatusParcela;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Parcela extends Model
{
    use BelongsToCliente;
    use HasUuids;

    protected $table = 'parcelas';

    protected $fillable = [
        'cliente_id',
        'contrato_id',
        'numero',
        'vencimento',
        'valor',
        'status',
        'pago_em',
    ];

    protected function casts(): array
    {
        return [
            'vencimento' => 'date',
            'valor' => 'decimal:2',
            'status' => StatusParcela::class,
            'pago_em' => 'datetime',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function cobrancas(): BelongsToMany
    {
        return $this->belongsToMany(Cobranca::class, 'cobranca_parcela')
            ->withPivot('valor_alocado');
    }
}
