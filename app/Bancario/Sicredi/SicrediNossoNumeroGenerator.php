<?php

namespace App\Bancario\Sicredi;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\Support\CalculoDvModulo11;
use App\Bancario\Support\TextoCnab;
use App\Contracts\Bancario\NossoNumeroGeneratorInterface;
use App\Exceptions\DominioException;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Support\Tenant\ClienteContext;
use Illuminate\Support\Facades\DB;

/**
 * Porta Fun_GerarNumRegistroUnicred:
 * registro = YY + contador + DV11(agencia + posto + conta + YY + contador)
 * incrementa clientes.contador_boletos_unicred (ex-par_contadorboletosunicred).
 *
 * Nosso número: reutiliza o da cobrança ou usa o próprio registro (a function Oracle
 * recebia NossoNumero mas não o usava na geração).
 */
class SicrediNossoNumeroGenerator implements NossoNumeroGeneratorInterface
{
    public function garantir(Cobranca $cobranca, ContaCobranca $conta): array
    {
        if ($cobranca->nosso_numero && $cobranca->numero_registro) {
            return [
                'nosso_numero' => $cobranca->nosso_numero,
                'numero_registro' => $cobranca->numero_registro,
            ];
        }

        $conta->validar();

        return DB::transaction(function () use ($cobranca, $conta) {
            $locked = Cobranca::query()->whereKey($cobranca->id)->lockForUpdate()->firstOrFail();

            if ($locked->nosso_numero && $locked->numero_registro) {
                return [
                    'nosso_numero' => $locked->nosso_numero,
                    'numero_registro' => $locked->numero_registro,
                ];
            }

            $registro = $this->gerarNumeroRegistro($conta);
            $nosso = $locked->nosso_numero ?: $registro;

            $locked->update([
                'nosso_numero' => $nosso,
                'numero_registro' => $registro,
                'data_emissao_boleto' => $locked->data_emissao_boleto ?? now()->toDateString(),
            ]);

            return [
                'nosso_numero' => $nosso,
                'numero_registro' => $registro,
            ];
        });
    }

    /**
     * @return string Número de registro com DV (ex.: 9 dígitos com contador 6).
     */
    public function gerarNumeroRegistro(ContaCobranca $conta, ?\DateTimeInterface $quando = null): string
    {
        $quando ??= now();
        $yy = $quando->format('y');

        $cliente = Cliente::query()
            ->whereKey(ClienteContext::id())
            ->lockForUpdate()
            ->first();

        if (! $cliente) {
            throw new DominioException('Cliente não encontrado para gerar número de registro.');
        }

        $contador = max(1, (int) $cliente->contador_boletos_unicred);
        $contadorFmt = TextoCnab::lpad($contador, $conta->contadorDigitos);

        $baseDv = TextoCnab::apenasDigitos($conta->agencia)
            .TextoCnab::apenasDigitos($conta->posto)
            .TextoCnab::apenasDigitos($conta->conta)
            .$yy
            .$contadorFmt;

        $dv = CalculoDvModulo11::digito($baseDv);
        $numero = $yy.$contadorFmt.$dv;

        $cliente->update([
            'contador_boletos_unicred' => $contador + 1,
        ]);

        return $numero;
    }
}
