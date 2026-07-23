<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Endereço mínimo Seridó para testes que emitem cobrança.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function enderecoPagadorTeste(array $extra = []): array
    {
        return array_merge([
            'endereco' => 'RUA TESTE, 100',
            'bairro' => 'CENTRO',
            'cidade' => 'CAICO',
            'cep' => '59300000',
            'uf' => 'RN',
        ], $extra);
    }
}
