<?php

namespace App\Services\Bancario;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\DTO\FiltroRemessa;
use App\Bancario\DTO\TituloRemessa;
use App\Bancario\FabricaAdaptadorBanco;
use App\Enums\OperacaoRemessa;
use App\Enums\StatusRemessa;
use App\Exceptions\DominioException;
use App\Models\Cliente;
use App\Models\Remessa;
use App\Models\RemessaItem;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GerarRemessaService
{
    public function __construct(
        private readonly FabricaAdaptadorBanco $fabrica,
    ) {}

    /**
     * Cria remessa pendente e deixa pronta para o job processar.
     *
     * @return Remessa
     */
    public function enfileirar(string $vencimentoInicial, string $vencimentoFinal): Remessa
    {
        $cliente = ClienteContext::get();
        $conta = ContaCobranca::fromClienteConfig($cliente->config ?? []);
        $conta->validar();

        $ini = Carbon::parse($vencimentoInicial)->startOfDay();
        $fim = Carbon::parse($vencimentoFinal)->startOfDay();

        if ($fim->lt($ini)) {
            throw new DominioException('Vencimento final deve ser >= inicial.');
        }

        $adapter = $this->fabrica->paraCliente($cliente);

        return Remessa::query()->create([
            'lote' => Remessa::proximoLote($cliente->id),
            'codigo_banco' => $adapter->codigoBanco(),
            'status' => StatusRemessa::Pendente,
            'vencimento_inicial' => $ini->toDateString(),
            'vencimento_final' => $fim->toDateString(),
            'geracao_inicio' => now(),
        ]);
    }

    public function processar(Remessa $remessa): Remessa
    {
        $cliente = Cliente::query()->findOrFail($remessa->cliente_id);
        ClienteContext::set($cliente, ClienteContext::usuario());

        $remessa->update([
            'status' => StatusRemessa::Processando,
            'geracao_inicio' => $remessa->geracao_inicio ?? now(),
            'erro' => null,
        ]);

        try {
            $conta = ContaCobranca::fromClienteConfig($cliente->config ?? []);
            $conta->validar();

            $adapter = $this->fabrica->porCodigo($remessa->codigo_banco);

            $filtro = new FiltroRemessa(
                vencimentoInicial: $remessa->vencimento_inicial,
                vencimentoFinal: $remessa->vencimento_final,
                conta: $conta,
            );

            $titulos = $adapter->seletorTitulos()->selecionar($filtro);

            if ($titulos->isEmpty()) {
                $remessa->update([
                    'status' => StatusRemessa::Vazia,
                    'quantidade_titulos' => 0,
                    'valor_total' => 0,
                    'geracao_termino' => now(),
                ]);

                return $remessa->fresh('itens');
            }

            return DB::transaction(function () use ($remessa, $adapter, $conta, $titulos) {
                $remessa->itens()->delete();

                /** @var TituloRemessa $titulo */
                foreach ($titulos as $titulo) {
                    RemessaItem::query()->create([
                        'remessa_id' => $remessa->id,
                        'cobranca_id' => $titulo->cobrancaId,
                        'nosso_numero' => $titulo->nossoNumero,
                        'numero_registro' => $titulo->numeroRegistro,
                        'operacao' => $titulo->operacao,
                        'tipo_boleto' => $titulo->tipoBoleto,
                        'valor' => $titulo->valor,
                        'valor_juros_dia' => $titulo->valorJurosDia,
                        'valor_multa' => $titulo->valorMulta,
                        'vencimento' => $titulo->vencimento->toDateString(),
                        'data_emissao' => $titulo->dataEmissao->toDateString(),
                        'dias_devolucao' => $titulo->diasDevolucao,
                        'codigo_multa' => $titulo->codigoMulta,
                        'enviado_remessa' => $titulo->operacao === OperacaoRemessa::Entrada ? 1 : 3,
                        'pagador' => $titulo->pagador->toArray(),
                    ]);
                }

                $itens = $remessa->itens()->get();
                $conteudo = $adapter->layout()->gerar($remessa, $conta, $itens);
                $nome = $adapter->nomeArquivo()->nomear($remessa, $conta, now());
                $path = 'remessas/'.$remessa->cliente_id.'/'.$nome;
                Storage::disk('local')->put($path, $conteudo);

                $remessa->update([
                    'status' => StatusRemessa::Concluida,
                    'quantidade_titulos' => $itens->count(),
                    'valor_total' => round((float) $itens->sum('valor'), 2),
                    'file_name' => $nome,
                    'file_path' => $path,
                    'geracao_termino' => now(),
                ]);

                return $remessa->fresh('itens');
            });
        } catch (\Throwable $e) {
            $remessa->update([
                'status' => StatusRemessa::Falha,
                'erro' => mb_substr($e->getMessage(), 0, 2000),
                'geracao_termino' => now(),
            ]);

            throw $e;
        }
    }
}
