<?php

namespace App\Models;

use App\Enums\StatusCobranca;
use App\Enums\TipoCobranca;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cobranca extends Model
{
    use BelongsToCliente;
    use HasUuids;

    protected $table = 'cobrancas';

    protected $fillable = [
        'cliente_id',
        'contratante_id',
        'tipo',
        'valor',
        'vencimento',
        'status',
        'meio',
        'referencia_externa',
        'pago_em',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vencimento' => 'date',
            'status' => StatusCobranca::class,
            'tipo' => TipoCobranca::class,
            'pago_em' => 'datetime',
        ];
    }

    public function contratante(): BelongsTo
    {
        return $this->belongsTo(Contratante::class);
    }

    public function parcelas(): BelongsToMany
    {
        return $this->belongsToMany(Parcela::class, 'cobranca_parcela')
            ->withPivot('valor_alocado');
    }
}
