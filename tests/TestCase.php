<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cinto de segurança: nunca rode RefreshDatabase contra o MySQL do lab.
        if (config('database.default') === 'mysql' || $this->app['db']->getDriverName() === 'mysql') {
            $this->fail(
                'Teste abortado: conexão MySQL detectada. Os testes devem usar SQLite :memory: '.
                '(veja tests/bootstrap.php). Rodar assim apaga o banco do lab.'
            );
        }
    }

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
