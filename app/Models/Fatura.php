<?php

namespace App\Models;

use App\Enums\StatusFatura;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fatura extends Model
{
    use BelongsToCliente;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'faturas';

    protected $fillable = [
        'cliente_id',
        'contratante_id',
        'chave_plano_sigoweb',
        'numero',
        'competencia',
        'data_emissao',
        'vencimento',
        'valor_bruto',
        'valor_retencoes',
        'valor_acrescimos',
        'valor_liquido',
        'status',
        'mensagem_erro',
        'meta',
        'cobranca_id',
    ];

    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'vencimento' => 'date',
            'valor_bruto' => 'decimal:2',
            'valor_retencoes' => 'decimal:2',
            'valor_acrescimos' => 'decimal:2',
            'valor_liquido' => 'decimal:2',
            'status' => StatusFatura::class,
            'meta' => 'array',
        ];
    }

    public function contratante(): BelongsTo
    {
        return $this->belongsTo(Contratante::class);
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(FaturaLancamento::class)->orderBy('ordem');
    }

    public function parcelas(): BelongsToMany
    {
        return $this->belongsToMany(Parcela::class, 'fatura_parcela');
    }
}
