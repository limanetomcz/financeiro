<?php

namespace App\Models;

use App\Enums\OperacaoRemessa;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemessaItem extends Model
{
    use BelongsToCliente;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'remessa_itens';

    protected $fillable = [
        'cliente_id',
        'remessa_id',
        'cobranca_id',
        'nosso_numero',
        'numero_registro',
        'operacao',
        'tipo_boleto',
        'valor',
        'valor_juros_dia',
        'valor_multa',
        'vencimento',
        'data_emissao',
        'dias_devolucao',
        'codigo_multa',
        'enviado_remessa',
        'pagador',
    ];

    protected function casts(): array
    {
        return [
            'operacao' => OperacaoRemessa::class,
            'valor' => 'decimal:2',
            'valor_juros_dia' => 'decimal:4',
            'valor_multa' => 'decimal:2',
            'vencimento' => 'date',
            'data_emissao' => 'date',
            'pagador' => 'array',
        ];
    }

    public function remessa(): BelongsTo
    {
        return $this->belongsTo(Remessa::class);
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
