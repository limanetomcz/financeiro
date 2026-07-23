<?php

namespace App\Services\Empresa;

use App\Enums\TipoContratante;
use App\Exceptions\DominioException;
use App\Models\Contratante;

class VincularBeneficiarioEmpresaService
{
    /**
     * Vincula um contratante PF à empresa PJ (empresa_id).
     * Se o PF ainda não existir, cria stub mínimo (nome obrigatório nesse caso).
     *
     * @param  array{chave_sigoweb: string, nome?: string|null, documento?: string|null}  $beneficiario
     */
    public function executar(Contratante $empresa, array $beneficiario): Contratante
    {
        if ($empresa->tipo !== TipoContratante::Pj) {
            throw new DominioException('Só é possível vincular beneficiários a contratante PJ.');
        }

        $chave = trim((string) ($beneficiario['chave_sigoweb'] ?? ''));
        if ($chave === '') {
            throw new DominioException('chave_sigoweb do beneficiário é obrigatória.');
        }

        if ($chave === $empresa->chave_sigoweb) {
            throw new DominioException('Empresa não pode ser vinculada a si mesma.');
        }

        $pf = Contratante::query()->firstOrNew([
            'cliente_id' => $empresa->cliente_id,
            'chave_sigoweb' => $chave,
        ]);

        if ($pf->exists && $pf->tipo === TipoContratante::Pj) {
            throw new DominioException("Chave {$chave} é PJ — informe um beneficiário PF.");
        }

        if (! $pf->exists) {
            $nome = trim((string) ($beneficiario['nome'] ?? ''));
            if ($nome === '') {
                throw new DominioException(
                    'Beneficiário ainda não existe no Financeiro: informe nome para criar o vínculo.'
                );
            }
            $pf->tipo = TipoContratante::Pf;
            $pf->nome = $nome;
            if (! empty($beneficiario['documento'])) {
                $pf->documento = preg_replace('/\D/', '', (string) $beneficiario['documento']);
            }
        }

        $pf->tipo = TipoContratante::Pf;
        $pf->empresa_id = $empresa->id;

        if (! empty($beneficiario['nome'])) {
            $pf->nome = trim((string) $beneficiario['nome']);
        }

        $pf->save();

        return $pf->fresh(['empresa']);
    }
}
