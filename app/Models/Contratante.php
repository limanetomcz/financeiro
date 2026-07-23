<?php

namespace App\Models;

use App\Enums\TipoContratante;
use App\Models\Concerns\BelongsToCliente;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contratante extends Model
{
    use BelongsToCliente;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'contratantes';

    protected $fillable = [
        'cliente_id',
        'empresa_id',
        'chave_sigoweb',
        'tipo',
        'nome',
        'documento',
        'endereco',
        'bairro',
        'cidade',
        'cep',
        'uf',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoContratante::class,
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(self::class, 'empresa_id');
    }

    public function beneficiarios(): HasMany
    {
        return $this->hasMany(self::class, 'empresa_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    public function faturas(): HasMany
    {
        return $this->hasMany(Fatura::class);
    }

    /** Endereço mínimo para emitir cobrança/boleto (CNAB/PDF). */
    public function temEnderecoCompleto(): bool
    {
        $campos = [
            trim((string) $this->endereco),
            trim((string) $this->bairro),
            trim((string) $this->cidade),
            preg_replace('/\D/', '', (string) $this->cep) ?: '',
            mb_strtoupper(trim((string) $this->uf)),
        ];

        foreach ($campos as $valor) {
            if ($valor === '') {
                return false;
            }
        }

        return mb_strlen($campos[4]) === 2;
    }
}
