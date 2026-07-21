<?php

namespace App\Models;

use App\Enums\TipoContratante;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contratante extends Model
{
    use BelongsToCliente;
    use HasUuids;

    protected $table = 'contratantes';

    protected $fillable = [
        'cliente_id',
        'chave_sigoweb',
        'tipo',
        'nome',
        'documento',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoContratante::class,
        ];
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }
}
