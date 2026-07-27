<?php

namespace App\Services\Fatura;

use App\Exceptions\DominioException;
use App\Services\Integracao\SigoLaravelClient;

/**
 * Gera faturas PJ para todos os planos E (protótipo / lab).
 * Espelha o “gerar todas” do Sigoweb: competência + data base.
 */
class SolicitarFaturasPjLoteService
{
    public function __construct(
        private SigoLaravelClient $sigoLaravel,
        private SolicitarFaturaPjService $solicitar,
    ) {
    }

    /**
     * @param  list<string>|null  $planosOverride  códigos de plano (teste/lab sem Laravel)
     * @return array{
     *   competencia: string,
     *   data_base: ?string,
     *   total_planos: int,
     *   solicitadas: list<array{plano: string, fatura_id: string, status: string, numero: ?string}>,
     *   erros: list<array{plano: string, message: string}>,
     *   puladas: list<array{plano: string, message: string}>
     * }
     */
    public function executar(
        string $competencia,
        ?string $dataBase = null,
        ?string $vencimento = null,
        bool $sincrono = false,
        ?string $bearerToken = null,
        float $percentualReajuste = 0.0,
        ?array $planosOverride = null,
    ): array {
        if (! preg_match('/^\d{4}-\d{2}$/', $competencia)) {
            throw new DominioException('Competência deve estar no formato YYYY-MM.');
        }

        if ($planosOverride !== null) {
            $planos = [];
            foreach ($planosOverride as $codigo) {
                $codigo = trim((string) $codigo);
                if ($codigo !== '') {
                    $planos[] = ['pla_codigo' => $codigo, 'pla_nome' => ''];
                }
            }
        } else {
            $planos = $this->sigoLaravel->listarPlanosEmpresa($bearerToken);
        }

        if ($planos === []) {
            throw new DominioException('Nenhum plano empresarial (tipo E) encontrado para gerar faturas.');
        }

        $solicitadas = [];
        $erros = [];
        $puladas = [];

        foreach ($planos as $plano) {
            $codigo = $plano['pla_codigo'];
            try {
                $fatura = $this->solicitar->executar(
                    $codigo,
                    $competencia,
                    $vencimento,
                    $sincrono,
                    $bearerToken,
                    null,
                    $percentualReajuste,
                    $dataBase,
                );

                $solicitadas[] = [
                    'plano' => $codigo,
                    'nome' => $plano['pla_nome'] ?? '',
                    'fatura_id' => $fatura->id,
                    'status' => $fatura->status->value,
                    'numero' => $fatura->numero,
                    'mensagem_erro' => $fatura->mensagem_erro,
                ];
            } catch (DominioException $e) {
                $msg = $e->getMessage();
                // Já existe na competência → não é falha dura do lote.
                if (str_contains(mb_strtolower($msg), 'já existe fatura')) {
                    $puladas[] = ['plano' => $codigo, 'message' => $msg];
                } else {
                    $erros[] = ['plano' => $codigo, 'message' => $msg];
                }
            } catch (\Throwable $e) {
                $erros[] = [
                    'plano' => $codigo,
                    'message' => mb_substr($e->getMessage(), 0, 500),
                ];
            }
        }

        return [
            'competencia' => $competencia,
            'data_base' => $dataBase,
            'total_planos' => count($planos),
            'solicitadas' => $solicitadas,
            'erros' => $erros,
            'puladas' => $puladas,
        ];
    }
}
