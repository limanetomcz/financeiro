<?php

namespace App\Services\Bancario;

use App\Bancario\DTO\OcorrenciaRetorno;
use App\Bancario\Sicredi\SicrediCnab240RetornoParser;
use App\Bancario\Sicredi\SicrediCodigosMovimentoRetorno;
use App\Enums\AcaoRetornoItem;
use App\Enums\OperacaoRemessa;
use App\Enums\StatusCobranca;
use App\Enums\StatusParcela;
use App\Enums\StatusRetornoBancario;
use App\Enums\StatusRetornoItem;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\RemessaItem;
use App\Models\RetornoBancario;
use App\Models\RetornoBancarioItem;
use App\Services\Cobranca\LiquidarCobrancaService;
use App\Support\Auth\OperadorAtual;
use App\Support\Tenant\ClienteContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessarRetornoBancarioService
{
    /** LOC_CODIGO legado Seridó — Sicredi. */
    private const LOCAL_SICREDI = '7';

    public function __construct(
        private readonly SicrediCnab240RetornoParser $parser,
        private readonly LiquidarCobrancaService $liquidar,
    ) {}

    /**
     * @param  array{operador?: array{login?: string, nome?: ?string}}  $opcoes
     */
    public function executar(string $conteudo, string $nomeArquivo, array $opcoes = []): RetornoBancario
    {
        $cliente = ClienteContext::get();
        $operador = OperadorAtual::resolver($opcoes['operador'] ?? null);
        $hash = hash('sha256', $conteudo);

        if (RetornoBancario::query()->where('hash_sha256', $hash)->exists()) {
            throw new DominioException('Este arquivo de retorno já foi processado.');
        }

        $ocorrencias = $this->parser->parse($conteudo);

        $retorno = RetornoBancario::query()->create([
            'cliente_id' => $cliente->id,
            'codigo_banco' => '748',
            'nome_arquivo' => $nomeArquivo,
            'hash_sha256' => $hash,
            'status' => StatusRetornoBancario::Processando,
            'quantidade_ocorrencias' => $ocorrencias->count(),
            'processado_por' => $operador['login'],
            'processado_por_nome' => $operador['nome'],
            'processamento_inicio' => now(),
        ]);

        $path = 'retornos/'.$cliente->id.'/'.$retorno->id.'_'.$nomeArquivo;
        Storage::disk('local')->put($path, $conteudo);
        $retorno->update(['file_path' => $path]);

        $contadores = [
            'liquidadas' => 0,
            'confirmadas' => 0,
            'excluidas' => 0,
            'rejeitadas' => 0,
            'ignoradas' => 0,
            'erros' => 0,
        ];

        try {
            foreach ($ocorrencias as $ocorrencia) {
                $resultado = $this->processarOcorrencia($retorno, $ocorrencia, $operador);
                $contadores[$resultado]++;
            }

            $temErro = $contadores['erros'] > 0;
            $temSucesso = (
                $contadores['liquidadas']
                + $contadores['confirmadas']
                + $contadores['excluidas']
                + $contadores['rejeitadas']
            ) > 0;

            $status = match (true) {
                $temErro && $temSucesso => StatusRetornoBancario::Parcial,
                $temErro && ! $temSucesso => StatusRetornoBancario::Falha,
                default => StatusRetornoBancario::Concluido,
            };

            $retorno->update([
                'status' => $status,
                'quantidade_liquidadas' => $contadores['liquidadas'],
                'quantidade_confirmadas' => $contadores['confirmadas'],
                'quantidade_excluidas' => $contadores['excluidas'],
                'quantidade_rejeitadas' => $contadores['rejeitadas'],
                'quantidade_ignoradas' => $contadores['ignoradas'],
                'quantidade_erros' => $contadores['erros'],
                'processamento_termino' => now(),
            ]);
        } catch (\Throwable $e) {
            $retorno->update([
                'status' => StatusRetornoBancario::Falha,
                'erro' => $e->getMessage(),
                'quantidade_liquidadas' => $contadores['liquidadas'],
                'quantidade_confirmadas' => $contadores['confirmadas'],
                'quantidade_excluidas' => $contadores['excluidas'],
                'quantidade_rejeitadas' => $contadores['rejeitadas'],
                'quantidade_ignoradas' => $contadores['ignoradas'],
                'quantidade_erros' => $contadores['erros'],
                'processamento_termino' => now(),
            ]);

            throw $e;
        }

        return $retorno->fresh('itens');
    }

    /**
     * @param  array{login: string, nome: ?string}  $operador
     * @return 'liquidadas'|'confirmadas'|'rejeitadas'|'ignoradas'|'erros'
     */
    private function processarOcorrencia(
        RetornoBancario $retorno,
        OcorrenciaRetorno $ocorrencia,
        array $operador
    ): string {
        $acao = SicrediCodigosMovimentoRetorno::acao($ocorrencia->codigoMovimento);

        $item = RetornoBancarioItem::query()->create([
            'cliente_id' => $retorno->cliente_id,
            'retorno_bancario_id' => $retorno->id,
            'linha' => $ocorrencia->linha,
            'codigo_movimento' => $ocorrencia->codigoMovimento,
            'acao' => $acao,
            'status' => StatusRetornoItem::Pendente,
            'nosso_numero' => $ocorrencia->nossoNumero !== '' ? $ocorrencia->nossoNumero : null,
            'numero_registro' => $ocorrencia->numeroRegistro !== '' ? $ocorrencia->numeroRegistro : null,
            'vencimento' => $ocorrencia->vencimento,
            'pago_em' => $ocorrencia->pagoEm,
            'valor_pago' => $ocorrencia->valorPago,
            'motivo_rejeicao' => $ocorrencia->motivoRejeicao,
            'linha_t' => $ocorrencia->linhaT,
            'linha_u' => $ocorrencia->linhaU,
        ]);

        try {
            return DB::transaction(function () use ($item, $acao, $ocorrencia, $operador) {
                $cobranca = $this->localizarCobranca($ocorrencia);

                if (! $cobranca) {
                    $item->update([
                        'status' => StatusRetornoItem::Erro,
                        'mensagem' => 'Cobrança não encontrada para o nosso número / registro.',
                    ]);

                    return 'erros';
                }

                $item->update(['cobranca_id' => $cobranca->id]);

                return match ($acao) {
                    AcaoRetornoItem::Liquidar => $this->aplicarLiquidacao($item, $cobranca, $ocorrencia, $operador),
                    AcaoRetornoItem::ExcluirTitulo => $this->aplicarExclusao($item, $cobranca),
                    AcaoRetornoItem::ConfirmarEntrada => $this->aplicarConfirmacao($item, $cobranca),
                    AcaoRetornoItem::Rejeitar => $this->aplicarRejeicao($item),
                    AcaoRetornoItem::Registrar => $this->aplicarRegistro($item),
                };
            });
        } catch (\Throwable $e) {
            $item->update([
                'status' => StatusRetornoItem::Erro,
                'mensagem' => $e->getMessage(),
            ]);

            return 'erros';
        }
    }

    private function localizarCobranca(OcorrenciaRetorno $ocorrencia): ?Cobranca
    {
        if ($ocorrencia->nossoNumero !== '') {
            $porNosso = Cobranca::query()
                ->where('nosso_numero', $ocorrencia->nossoNumero)
                ->first();
            if ($porNosso) {
                return $porNosso;
            }
        }

        if ($ocorrencia->numeroRegistro !== '') {
            return Cobranca::query()
                ->where('numero_registro', $ocorrencia->numeroRegistro)
                ->first();
        }

        return null;
    }

    /**
     * @param  array{login: string, nome: ?string}  $operador
     * @return 'liquidadas'|'ignoradas'|'erros'
     */
    private function aplicarLiquidacao(
        RetornoBancarioItem $item,
        Cobranca $cobranca,
        OcorrenciaRetorno $ocorrencia,
        array $operador
    ): string {
        if ($cobranca->status === StatusCobranca::Paga) {
            $item->update([
                'status' => StatusRetornoItem::Ignorado,
                'mensagem' => 'Cobrança já estava paga.',
            ]);

            return 'ignoradas';
        }

        if ($cobranca->status !== StatusCobranca::Aberta) {
            $item->update([
                'status' => StatusRetornoItem::Erro,
                'mensagem' => 'Cobrança não está aberta (status: '.$cobranca->status->value.').',
            ]);

            return 'erros';
        }

        $juros = round((float) ($ocorrencia->valorJuros ?? 0), 2);
        if ($juros > 0 || ($ocorrencia->valorPago !== null && abs($ocorrencia->valorPago - (float) $cobranca->valor) > 0.009)) {
            $principal = round((float) $cobranca->valor_principal, 2);
            $multa = round((float) $cobranca->valor_multa, 2);
            $pago = $ocorrencia->valorPago !== null
                ? round((float) $ocorrencia->valorPago, 2)
                : round($principal + $juros + $multa, 2);

            $cobranca->update([
                'valor_juros' => $juros,
                'valor' => $pago,
            ]);
            $cobranca->refresh();
        }

        $this->liquidar->executar($cobranca, $ocorrencia->pagoEm, [
            'codigo_legado' => self::LOCAL_SICREDI,
            'operador' => [
                'login' => $operador['login'],
                'nome' => $operador['nome'],
            ],
        ]);

        $this->marcarRemessaItensRegistrados($cobranca);

        $item->update([
            'status' => StatusRetornoItem::Processado,
            'mensagem' => 'Cobrança liquidada via retorno CNAB.',
        ]);

        return 'liquidadas';
    }

    /** @return 'excluidas'|'ignoradas'|'erros' */
    private function aplicarExclusao(RetornoBancarioItem $item, Cobranca $cobranca): string
    {
        if ($cobranca->status === StatusCobranca::Cancelada) {
            $item->update([
                'status' => StatusRetornoItem::Ignorado,
                'mensagem' => 'Cobrança já estava cancelada.',
            ]);

            return 'ignoradas';
        }

        if ($cobranca->status === StatusCobranca::Paga) {
            $item->update([
                'status' => StatusRetornoItem::Erro,
                'mensagem' => 'Não é possível excluir título de cobrança já paga.',
            ]);

            return 'erros';
        }

        foreach ($cobranca->parcelas()->lockForUpdate()->get() as $parcela) {
            if (in_array($parcela->status, [StatusParcela::EmCobranca, StatusParcela::Aberta], true)) {
                $parcela->update([
                    'status' => StatusParcela::Aberta,
                    'pago_em' => null,
                ]);
            }
        }

        $cobranca->parcelas()->detach();
        $cobranca->update(['status' => StatusCobranca::Cancelada]);

        $item->update([
            'status' => StatusRetornoItem::Processado,
            'mensagem' => 'Título excluído/baixado pelo banco (cobrança cancelada; parcelas liberadas).',
        ]);

        return 'excluidas';
    }

    /** @return 'confirmadas'|'ignoradas' */
    private function aplicarConfirmacao(RetornoBancarioItem $item, Cobranca $cobranca): string
    {
        $atualizados = $this->marcarRemessaItensRegistrados($cobranca);

        $item->update([
            'status' => StatusRetornoItem::Processado,
            'mensagem' => $atualizados > 0
                ? 'Entrada confirmada (enviado_remessa=2).'
                : 'Confirmação recebida; nenhum item de remessa pendente para atualizar.',
        ]);

        return 'confirmadas';
    }

    /** @return 'rejeitadas' */
    private function aplicarRejeicao(RetornoBancarioItem $item): string
    {
        $item->update([
            'status' => StatusRetornoItem::Processado,
            'mensagem' => 'Entrada rejeitada pelo banco'
                .($item->motivo_rejeicao ? ' (motivo '.$item->motivo_rejeicao.').' : '.'),
        ]);

        return 'rejeitadas';
    }

    /** @return 'ignoradas' */
    private function aplicarRegistro(RetornoBancarioItem $item): string
    {
        $item->update([
            'status' => StatusRetornoItem::Ignorado,
            'mensagem' => 'Código de movimento registrado sem ação automática.',
        ]);

        return 'ignoradas';
    }

    private function marcarRemessaItensRegistrados(Cobranca $cobranca): int
    {
        return RemessaItem::query()
            ->where('cobranca_id', $cobranca->id)
            ->where('operacao', OperacaoRemessa::Entrada)
            ->where('enviado_remessa', 1)
            ->update(['enviado_remessa' => 2]);
    }
}
