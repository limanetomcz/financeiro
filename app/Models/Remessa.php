<?php

namespace App\Models;

use App\Enums\StatusRemessa;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Remessa extends Model
{
    use BelongsToCliente;
    use HasUuids;

    protected $table = 'remessas';

    protected $fillable = [
        'cliente_id',
        'lote',
        'codigo_banco',
        'status',
        'vencimento_inicial',
        'vencimento_final',
        'quantidade_titulos',
        'valor_total',
        'file_name',
        'file_path',
        'erro',
        'geracao_inicio',
        'geracao_termino',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusRemessa::class,
            'vencimento_inicial' => 'date',
            'vencimento_final' => 'date',
            'valor_total' => 'decimal:2',
            'geracao_inicio' => 'datetime',
            'geracao_termino' => 'datetime',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(RemessaItem::class);
    }

    public static function proximoLote(string $clienteId): int
    {
        $max = (int) static::query()
            ->withoutGlobalScopes()
            ->where('cliente_id', $clienteId)
            ->max('lote');

        return $max + 1;
    }
}
