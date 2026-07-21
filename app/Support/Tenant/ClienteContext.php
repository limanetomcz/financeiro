<?php

namespace App\Support\Tenant;

use App\Models\Cliente;
use RuntimeException;

class ClienteContext
{
    private static ?Cliente $cliente = null;

    private static ?array $usuarioSigoweb = null;

    public static function set(Cliente $cliente, ?array $usuarioSigoweb = null): void
    {
        self::$cliente = $cliente;
        self::$usuarioSigoweb = $usuarioSigoweb;
    }

    public static function clear(): void
    {
        self::$cliente = null;
        self::$usuarioSigoweb = null;
    }

    public static function check(): bool
    {
        return self::$cliente !== null;
    }

    public static function get(): Cliente
    {
        if (! self::$cliente) {
            throw new RuntimeException('Nenhum cliente (tenant) resolvido neste request.');
        }

        return self::$cliente;
    }

    public static function id(): string
    {
        return self::get()->id;
    }

    public static function usuario(): ?array
    {
        return self::$usuarioSigoweb;
    }
}
