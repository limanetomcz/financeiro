<?php

namespace App\Services\Fatura;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Enums\TipoCobranca;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Fatura;
use App\Services\Empresa\SincronizarEmpresaDoPlanoService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Illuminate\Support\Facades\DB;

class EmitirCobrancaFaturaPjService
{
    public function __construct(
        private SincronizarEmpresaDoPlanoService $sincronizarEmpresa,
    ) {
    }

    public function executar(Fatura $fatura, ?string $meio = 'boleto', ?string $bearerToken = null): Cobranca
    {
        return DB::transaction(function () use ($fatura, $meio, $bearerToken) {
            $fatura = Fatura::query()->whereKey($fatura->id)->lockForUpdate()->firstOrFail();

            if (! in_array($fatura->status, [StatusFatura::Aberta, StatusFatura::Rascunho], true)) {
                throw new DominioException('Só é possível emitir cobrança de fatura aberta ou rascunho.');
            }

            if ($fatura->cobranca_id) {
                throw new DominioException('Fatura já possui cobrança vinculada.');
            }

            $cliente = ClienteContext::get();
            $usa = ClienteConfig::pjBoletoUsaValor($cliente);
            $valor = $usa === 'bruto'
                ? (float) $fatura->valor_bruto
                : (float) $fatura->valor_liquido;

            if ($valor <= 0) {
                throw new DominioException('Valor da cobrança da fatura deve ser maior que zero.');
            }

            $empresa = $fatura->contratante;
            if (! $empresa || ! $empresa->temEnderecoCompleto()) {
                try {
                    $empresa = $this->sincronizarEmpresa->executar($fatura, null, $bearerToken);
                    $fatura->update(['contratante_id' => $empresa->id]);
                    $fatura->setRelation('contratante', $empresa);
                } catch (DominioException $e) {
                    throw new DominioException(
                        'Não é possível gerar cobrança: contratante sem endereço completo e falha ao buscar o plano ('.$e->getMessage().').'
                    );
                }
            }

            if (! $empresa->temEnderecoCompleto()) {
                $faltando = $this->sincronizarEmpresa->camposEnderecoFaltando($empresa);
                throw new DominioException(
                    'Não é possível gerar cobrança: plano/contratante sem endereço completo (faltam: '
                    .implode(', ', $faltando)
                    .'). Cadastre endereço no plano no Sigoweb/Oracle ou complete o contratante.'
                );
            }

            $cobranca = Cobranca::query()->create([
                'contratante_id' => $empresa->id,
                'tipo' => TipoCobranca::Simples,
                'valor_principal' => $valor,
                'valor_juros' => 0,
                'valor_multa' => 0,
                'valor' => $valor,
                'vencimento' => $fatura->vencimento,
                'status' => StatusCobranca::Aberta,
                'meio' => $meio,
            ]);

            $fatura->update([
                'cobranca_id' => $cobranca->id,
                'status' => StatusFatura::EmCobranca,
            ]);

            return $cobranca;
        });
    }
}
