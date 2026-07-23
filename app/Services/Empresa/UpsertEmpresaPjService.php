<?php

namespace App\Services\Empresa;

use App\Enums\TipoContratante;
use App\Exceptions\DominioException;
use App\Models\Contratante;
use App\Support\Tenant\ClienteContext;

class UpsertEmpresaPjService
{
    /**
     * Cria ou atualiza contratante PJ (empresa).
     *
     * @param  array{
     *   chave_sigoweb: string,
     *   nome: string,
     *   documento?: string|null,
     *   endereco?: string|null,
     *   bairro?: string|null,
     *   cidade?: string|null,
     *   cep?: string|null,
     *   uf?: string|null
     * }  $dados
     */
    public function executar(array $dados): Contratante
    {
        $cliente = ClienteContext::get();
        $chave = trim((string) ($dados['chave_sigoweb'] ?? ''));

        if ($chave === '') {
            throw new DominioException('chave_sigoweb da empresa é obrigatória.');
        }

        $empresa = Contratante::query()->firstOrNew([
            'cliente_id' => $cliente->id,
            'chave_sigoweb' => $chave,
        ]);

        if ($empresa->exists && $empresa->tipo === TipoContratante::Pf) {
            throw new DominioException(
                "Chave {$chave} já está cadastrada como PF — use outra chave para a empresa."
            );
        }

        $updates = [
            'tipo' => TipoContratante::Pj,
            'nome' => trim((string) ($dados['nome'] ?? $empresa->nome ?? '')),
        ];

        if ($updates['nome'] === '') {
            throw new DominioException('Nome da empresa é obrigatório.');
        }

        if (array_key_exists('documento', $dados)) {
            $doc = $dados['documento'];
            $updates['documento'] = $doc !== null && $doc !== ''
                ? preg_replace('/\D/', '', (string) $doc)
                : $empresa->documento;
        }

        foreach (['endereco', 'bairro', 'cidade', 'cep', 'uf'] as $campo) {
            if (! array_key_exists($campo, $dados)) {
                continue;
            }
            $valor = $dados[$campo];
            if ($valor === null) {
                continue;
            }
            $valor = is_string($valor) ? trim($valor) : $valor;
            if ($valor === '') {
                continue;
            }
            if ($campo === 'cep') {
                $valor = preg_replace('/\D/', '', (string) $valor) ?: $valor;
            }
            if ($campo === 'uf') {
                $valor = mb_strtoupper(mb_substr((string) $valor, 0, 2));
            }
            $updates[$campo] = $valor;
        }

        $empresa->fill($updates);
        $empresa->save();

        return $empresa->fresh();
    }
}
