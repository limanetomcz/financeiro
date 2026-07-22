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
        'valor_principal',
        'valor_juros',
        'valor_multa',
        'valor',
        'vencimento',
        'status',
        'meio',
        'local_pagamento_id',
        'taxa_local_pagamento_id',
        'local_pagamento',
        'local_pagamento_descricao',
        'taxa_percentual',
        'valor_taxa',
        'modalidade',
        'bandeira',
        'referencia_externa',
        'nosso_numero',
        'numero_registro',
        'data_emissao_boleto',
        'pago_em',
        'baixado_por',
        'baixado_por_nome',
        'baixa_retirada_por',
        'baixa_retirada_por_nome',
        'baixa_retirada_em',
    ];

    protected function casts(): array
    {
        return [
            'valor_principal' => 'decimal:2',
            'valor_juros' => 'decimal:2',
            'valor_multa' => 'decimal:2',
            'valor' => 'decimal:2',
            'taxa_percentual' => 'decimal:4',
            'valor_taxa' => 'decimal:2',
            'vencimento' => 'date',
            'data_emissao_boleto' => 'date',
            'status' => StatusCobranca::class,
            'tipo' => TipoCobranca::class,
            'pago_em' => 'datetime',
            'baixa_retirada_em' => 'datetime',
        ];
    }

    public function contratante(): BelongsTo
    {
        return $this->belongsTo(Contratante::class);
    }

    public function localPagamento(): BelongsTo
    {
        return $this->belongsTo(LocalPagamento::class);
    }

    public function taxaLocalPagamento(): BelongsTo
    {
        return $this->belongsTo(TaxaLocalPagamento::class);
    }

    public function parcelas(): BelongsToMany
    {
        return $this->belongsToMany(Parcela::class, 'cobranca_parcela')
            ->withPivot('valor_alocado');
    }
}
