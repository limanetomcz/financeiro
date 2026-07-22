<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContratoBeneficiario extends Model
{
    use BelongsToCliente;
    use HasUuids;

    protected $table = 'contrato_beneficiarios';

    protected $fillable = [
        'cliente_id',
        'contrato_id',
        'chave_sigoweb',
        'chave_depend_sigoweb',
        'nome',
        'documento',
        'tipo_dependencia',
        'tipodep_sigoweb',
        'valor_mensal',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'valor_mensal' => 'decimal:2',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function parcelasComposicao(): HasMany
    {
        return $this->hasMany(ParcelaBeneficiario::class);
    }

    public function isTitular(): bool
    {
        return $this->tipo_dependencia === 'titular'
            || $this->tipodep_sigoweb === '3';
    }
}
