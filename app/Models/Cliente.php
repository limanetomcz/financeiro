<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasUuids;

    protected $table = 'clientes';

    protected $fillable = [
        'nome',
        'codigo_cooperativa',
        'chave_sigoweb',
        'ativo',
        'usa_financeiro_novo',
        'timezone',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'usa_financeiro_novo' => 'boolean',
            'config' => 'array',
        ];
    }

    public static function findBySigowebKey(string $chave): ?self
    {
        return static::query()
            ->where('ativo', true)
            ->where(function ($q) use ($chave) {
                $q->where('chave_sigoweb', $chave)
                    ->orWhere('codigo_cooperativa', $chave);
            })
            ->first();
    }
}
