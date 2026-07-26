<?php

namespace App\Services\Empresa;

use App\Exceptions\DominioException;
use App\Models\Contratante;
use App\Models\Fatura;
use App\Services\Integracao\SigoLaravelClient;
use App\Support\Tenant\ClienteContext;

/**
 * Atualiza o contratante PJ com nome/CNPJ/endereço do plano (leitura Laravel/Oracle).
 */
class SincronizarEmpresaDoPlanoService
{
    public function __construct(
        private UpsertEmpresaPjService $upsertEmpresa,
        private SigoLaravelClient $sigoLaravel,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $plano  bloco `plano` já carregado; senão busca no Laravel
     */
    public function executar(
        Fatura $fatura,
        ?array $plano = null,
        ?string $bearerToken = null,
    ): Contratante {
        if ($plano === null) {
            $dataBase = data_get($fatura->meta, 'data_base');
            $dataBase = is_string($dataBase) && $dataBase !== '' ? $dataBase : null;
            $dados = $this->sigoLaravel->dadosFaturaPj(
                (string) $fatura->chave_plano_sigoweb,
                $fatura->competencia,
                $bearerToken,
                $dataBase,
            );
            $plano = $dados['plano'] ?? null;
        }

        if (! is_array($plano) || empty($plano['chave_sigoweb'])) {
            throw new DominioException('Dados do plano ausentes para sincronizar o contratante.');
        }

        // Muitos planos Seridó vêm sem pla_bairro; CNAB/boleto usam fallback do tenant.
        $endereco = $this->completarEnderecoComPadraoTenant([
            'endereco' => $plano['endereco'] ?? null,
            'bairro' => $plano['bairro'] ?? null,
            'cidade' => $plano['cidade'] ?? null,
            'cep' => $plano['cep'] ?? null,
            'uf' => $plano['uf'] ?? null,
        ]);

        return $this->upsertEmpresa->executar([
            'chave_sigoweb' => (string) $plano['chave_sigoweb'],
            'nome' => (string) ($plano['razao_social'] ?: $plano['nome'] ?: $plano['chave_sigoweb']),
            'documento' => $plano['documento'] ?? null,
            'endereco' => $endereco['endereco'],
            'bairro' => $endereco['bairro'],
            'cidade' => $endereco['cidade'],
            'cep' => $endereco['cep'],
            'uf' => $endereco['uf'],
        ]);
    }

    /**
     * Preenche só lacunas com pagador_padrao do tenant (não sobrescreve o que veio do plano).
     *
     * @param  array{endereco?: ?string, bairro?: ?string, cidade?: ?string, cep?: ?string, uf?: ?string}  $endereco
     * @return array{endereco: ?string, bairro: ?string, cidade: ?string, cep: ?string, uf: ?string}
     */
    private function completarEnderecoComPadraoTenant(array $endereco): array
    {
        $padrao = (array) data_get(
            ClienteContext::get()?->config,
            'cobranca.bancario.pagador_padrao',
            [
                'bairro' => 'CENTRO',
            ]
        );

        foreach (['endereco', 'bairro', 'cidade', 'cep', 'uf'] as $campo) {
            $valor = isset($endereco[$campo]) ? trim((string) $endereco[$campo]) : '';
            if ($valor !== '') {
                $endereco[$campo] = $valor;
                continue;
            }
            // Só bairro recebe fallback automático (plano frequentemente sem pla_bairro).
            // Demais campos exigem cadastro real no plano.
            if ($campo === 'bairro') {
                $fallback = trim((string) ($padrao['bairro'] ?? 'CENTRO'));
                $endereco[$campo] = $fallback !== '' ? $fallback : 'CENTRO';
            } else {
                $endereco[$campo] = $valor !== '' ? $valor : null;
            }
        }

        return $endereco;
    }

    /** @return list<string> */
    public function camposEnderecoFaltando(Contratante $empresa): array
    {
        $faltando = [];
        if (trim((string) $empresa->endereco) === '') {
            $faltando[] = 'endereço';
        }
        if (trim((string) $empresa->bairro) === '') {
            $faltando[] = 'bairro';
        }
        if (trim((string) $empresa->cidade) === '') {
            $faltando[] = 'cidade';
        }
        if ((preg_replace('/\D/', '', (string) $empresa->cep) ?: '') === '') {
            $faltando[] = 'CEP';
        }
        $uf = mb_strtoupper(trim((string) $empresa->uf));
        if ($uf === '' || mb_strlen($uf) !== 2) {
            $faltando[] = 'UF';
        }

        return $faltando;
    }
}
