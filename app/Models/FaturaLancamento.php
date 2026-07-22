<?php

namespace App\Models;

use App\Enums\NaturezaLancamento;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaLancamento extends Model
{
    use BelongsToCliente;
    use HasUuids;

    protected $table = 'fatura_lancamentos';

    protected $fillable = [
        'cliente_id',
        'fatura_id',
        'codigo',
        'descricao',
        'natureza',
        'origem',
        'valor',
        'ordem',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'natureza' => NaturezaLancamento::class,
            'valor' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }
}
