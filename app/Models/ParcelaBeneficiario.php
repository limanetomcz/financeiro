<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelaBeneficiario extends Model
{
    use BelongsToCliente;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'parcela_beneficiarios';

    protected $fillable = [
        'cliente_id',
        'parcela_id',
        'contrato_beneficiario_id',
        'chave_sigoweb',
        'nome',
        'documento',
        'tipo_dependencia',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }

    public function contratoBeneficiario(): BelongsTo
    {
        return $this->belongsTo(ContratoBeneficiario::class);
    }
}
