<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaNumeroSequencia extends Model
{
    use BelongsToCliente;
    use HasUuids;

    protected $table = 'fatura_numero_sequencias';

    protected $fillable = [
        'cliente_id',
        'referencia',
        'ultimo',
    ];

    protected function casts(): array
    {
        return [
            'ultimo' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
