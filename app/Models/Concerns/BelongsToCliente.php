<?php

namespace App\Models\Concerns;

use App\Models\Cliente;
use App\Support\Tenant\ClienteContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCliente
{
    public static function bootBelongsToCliente(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->getAttribute('cliente_id') && ClienteContext::check()) {
                $model->setAttribute('cliente_id', ClienteContext::id());
            }
        });

        static::addGlobalScope('cliente', function (Builder $builder): void {
            if (ClienteContext::check()) {
                $builder->where(
                    $builder->getModel()->getTable().'.cliente_id',
                    ClienteContext::id()
                );
            }
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
