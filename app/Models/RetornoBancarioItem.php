<?php

namespace App\Models;

use App\Enums\AcaoRetornoItem;
use App\Enums\StatusRetornoItem;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetornoBancarioItem extends Model
{
    use BelongsToCliente;
    use HasUuids;

    protected $table = 'retorno_bancario_itens';

    protected $fillable = [
        'cliente_id',
        'retorno_bancario_id',
        'cobranca_id',
        'linha',
        'codigo_movimento',
        'acao',
        'status',
        'nosso_numero',
        'numero_registro',
        'vencimento',
        'pago_em',
        'valor_pago',
        'motivo_rejeicao',
        'mensagem',
        'linha_t',
        'linha_u',
    ];

    protected function casts(): array
    {
        return [
            'acao' => AcaoRetornoItem::class,
            'status' => StatusRetornoItem::class,
            'vencimento' => 'date',
            'pago_em' => 'date',
            'valor_pago' => 'decimal:2',
        ];
    }

    public function retorno(): BelongsTo
    {
        return $this->belongsTo(RetornoBancario::class, 'retorno_bancario_id');
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
