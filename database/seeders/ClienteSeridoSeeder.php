<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeridoSeeder extends Seeder
{
    public function run(): void
    {
        Cliente::query()->updateOrCreate(
            ['codigo_cooperativa' => '112'],
            [
                'nome' => 'Uniodonto Seridó',
                'chave_sigoweb' => '112',
                'ativo' => true,
                'usa_financeiro_novo' => false,
                'timezone' => 'America/Recife',
                'config' => [],
            ]
        );
    }
}
