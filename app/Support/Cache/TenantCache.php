<?php

namespace App\Support\Cache;

use App\Support\Tenant\ClienteContext;
use Closure;
use Illuminate\Support\Facades\Cache;

class TenantCache
{
    public static function remember(string $key, mixed $ttl, Closure $callback): mixed
    {
        return Cache::remember(self::key($key), $ttl, $callback);
    }

    public static function forget(string $key): bool
    {
        return Cache::forget(self::key($key));
    }

    public static function key(string $key): string
    {
        $clienteId = ClienteContext::check() ? ClienteContext::id() : 'global';

        return "cliente:{$clienteId}:{$key}";
    }
}
