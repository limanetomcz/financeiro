<?php

namespace App\Models;

use App\Enums\StatusRetornoBancario;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetornoBancario extends Model
{
    use BelongsToCliente;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'retornos_bancarios';

    protected $fillable = [
        'cliente_id',
        'codigo_banco',
        'nome_arquivo',
        'file_path',
        'hash_sha256',
        'status',
        'quantidade_ocorrencias',
        'quantidade_liquidadas',
        'quantidade_confirmadas',
        'quantidade_excluidas',
        'quantidade_rejeitadas',
        'quantidade_ignoradas',
        'quantidade_erros',
        'erro',
        'processado_por',
        'processado_por_nome',
        'processamento_inicio',
        'processamento_termino',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusRetornoBancario::class,
            'processamento_inicio' => 'datetime',
            'processamento_termino' => 'datetime',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(RetornoBancarioItem::class);
    }
}
