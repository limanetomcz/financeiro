<?php

namespace App\Services\Integracao;

use App\Exceptions\DominioException;
use App\Support\Tenant\ClienteContext;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP para o sigo-laravel (dados brutos fatura PJ — só leitura).
 */
class SigoLaravelClient
{
    /**
     * @return array<string, mixed>
     */
    public function dadosFaturaPj(string $chavePlano, string $competencia, ?string $bearerToken = null): array
    {
        $base = $this->baseUrl();
        $token = $bearerToken ?: $this->tokenDaRequisicaoAtual();

        if (! $token) {
            throw new DominioException('Token Sigoweb ausente para consultar dados da fatura no Laravel.');
        }

        $url = rtrim($base, '/') . '/api/v1/financeiro/contasReceber/fatura/dadosFaturaFinanceiroNovo/' . rawurlencode($chavePlano);

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(120)
                ->get($url, ['competencia' => $competencia]);
        } catch (\Throwable $e) {
            throw new DominioException('Falha ao contatar sigo-laravel: ' . $e->getMessage());
        }

        if ($response->status() === 422) {
            throw new DominioException((string) ($response->json('message') ?? 'Dados de fatura rejeitados.'));
        }

        if (! $response->successful()) {
            throw new DominioException(
                'sigo-laravel retornou HTTP ' . $response->status() . ' ao buscar dados da fatura.'
            );
        }

        $dados = $response->json();
        if (! is_array($dados) || empty($dados['plano']) || ! isset($dados['vidas'])) {
            throw new DominioException('Dados de fatura inválidos retornados pelo sigo-laravel.');
        }

        return $dados;
    }

    /** @deprecated use dadosFaturaPj */
    public function composicaoFaturaPj(string $chavePlano, string $competencia, ?string $bearerToken = null): array
    {
        return $this->dadosFaturaPj($chavePlano, $competencia, $bearerToken);
    }

    private function baseUrl(): string
    {
        $cliente = ClienteContext::get();
        $fromCliente = data_get($cliente?->config, 'integracao.sigo_laravel_url');
        $url = $fromCliente
            ?: config('financeiro.sigo_laravel_url')
            ?: env('SIGO_LARAVEL_URL');

        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            throw new DominioException(
                'URL do sigo-laravel não configurada (SIGO_LARAVEL_URL ou clientes.config.integracao.sigo_laravel_url).'
            );
        }

        return rtrim($url, '/');
    }

    private function tokenDaRequisicaoAtual(): ?string
    {
        $header = request()->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
