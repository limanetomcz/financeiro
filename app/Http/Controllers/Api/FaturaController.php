<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoContratante;
use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Models\Contratante;
use App\Models\Fatura;
use App\Models\Cobranca;
use App\Services\Boleto\GerarPdfBoletoService;
use App\Services\Fatura\AdicionarLancamentoFaturaService;
use App\Services\Fatura\AlterarEmissaoFaturaService;
use App\Services\Fatura\AlterarVencimentoFaturaService;
use App\Services\Fatura\EmitirCobrancaFaturaPjService;
use App\Services\Fatura\GerarFaturaPjService;
use App\Services\Fatura\GerarPdfDemonstrativoFaturaService;
use App\Services\Fatura\GerarPdfFaturaPjService;
use App\Services\Fatura\ListarFaturasService;
use App\Services\Fatura\RemoverFaturaService;
use App\Services\Fatura\SolicitarFaturaPjService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class FaturaController extends Controller
{
    public function index(Request $request, ListarFaturasService $service): JsonResponse
    {
        $statusValues = array_column(\App\Enums\StatusFatura::cases(), 'value');

        $filtros = $request->validate([
            'numero' => ['nullable', 'string', 'max:40'],
            'chave_plano_sigoweb' => ['nullable', 'string', 'max:64'],
            'contratante_id' => ['nullable', 'uuid'],
            'contratante_nome' => ['nullable', 'string', 'max:180'],
            'contratante_documento' => ['nullable', 'string', 'max:32'],
            'competencia' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'status' => ['nullable'],
            'apenas_abertas' => ['sometimes', 'boolean'],
            'data_emissao_de' => ['nullable', 'date'],
            'data_emissao_ate' => ['nullable', 'date'],
            'vencimento_de' => ['nullable', 'date'],
            'vencimento_ate' => ['nullable', 'date'],
            'com_cobranca' => ['nullable'],
            'valor_liquido_min' => ['nullable', 'numeric'],
            'valor_liquido_max' => ['nullable', 'numeric'],
            'incluir_excluidas' => ['sometimes', 'boolean'],
            'somente_excluidas' => ['sometimes', 'boolean'],
            'ordenar' => ['nullable', Rule::in(['recentes', 'vencimento', 'emissao', 'numero'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (isset($filtros['status'])) {
            $status = $filtros['status'];
            if (is_string($status) && str_contains($status, ',')) {
                $lista = array_filter(array_map('trim', explode(',', $status)));
                foreach ($lista as $st) {
                    if (! in_array($st, $statusValues, true)) {
                        return response()->json(['message' => "Status inválido: {$st}"], 422);
                    }
                }
            } elseif (is_string($status) && $status !== '' && ! in_array($status, $statusValues, true)) {
                return response()->json(['message' => "Status inválido: {$status}"], 422);
            }
        }

        return response()->json($service->executar($filtros, (int) ($filtros['per_page'] ?? 20)));
    }

    public function show(string $id): JsonResponse
    {
        $fatura = Fatura::query()
            ->with(['contratante', 'lancamentos', 'parcelas', 'cobranca'])
            ->findOrFail($id);

        return response()->json([
            ...$fatura->toArray(),
            'status_label' => $fatura->status?->label(),
            'status_descricao' => $fatura->status?->descricao(),
        ]);
    }

    public function destroy(string $id, RemoverFaturaService $service): JsonResponse
    {
        $fatura = Fatura::query()->findOrFail($id);

        try {
            $resultado = $service->executar($fatura);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($resultado);
    }

    public function alterarEmissao(string $id, Request $request, AlterarEmissaoFaturaService $service): JsonResponse
    {
        $dados = $request->validate([
            'data_emissao' => ['required', 'date'],
        ]);

        $fatura = Fatura::query()->findOrFail($id);

        try {
            $fatura = $service->executar($fatura, $dados['data_emissao']);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Data de emissão atualizada.',
            'fatura' => $fatura,
        ]);
    }

    public function alterarVencimento(string $id, Request $request, AlterarVencimentoFaturaService $service): JsonResponse
    {
        $dados = $request->validate([
            'vencimento' => ['required', 'date'],
        ]);

        $fatura = Fatura::query()->findOrFail($id);

        try {
            $fatura = $service->executar($fatura, $dados['vencimento']);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Data de vencimento atualizada.',
            'fatura' => $fatura,
        ]);
    }

    /**
     * Fluxo oficial (protótipo): chave_plano + competência → 202 processando.
     * sincrono=1 processa na hora (lab sem worker).
     * dados= {...} pula Laravel (teste).
     */
    public function store(Request $request, SolicitarFaturaPjService $solicitar, GerarFaturaPjService $legado): JsonResponse
    {
        $dados = $request->validate([
            'chave_plano_sigoweb' => ['nullable', 'string', 'max:64'],
            'contratante_id' => ['nullable', 'uuid', 'exists:contratantes,id'],
            'competencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'vencimento' => ['nullable', 'date'],
            'sincrono' => ['sometimes', 'boolean'],
            'percentual_reajuste' => ['nullable', 'numeric'],
            'dados' => ['nullable', 'array'],
            'composicao' => ['nullable', 'array'],
            'lancamentos' => ['nullable', 'array'],
            'lancamentos.*' => ['numeric', 'min:0'],
        ]);

        $token = null;
        if (preg_match('/^Bearer\s+(.+)$/i', (string) $request->header('Authorization', ''), $m)) {
            $token = trim($m[1]);
        }

        try {
            if (! empty($dados['chave_plano_sigoweb'])) {
                $override = $dados['dados'] ?? $dados['composicao'] ?? null;
                $fatura = $solicitar->executar(
                    (string) $dados['chave_plano_sigoweb'],
                    $dados['competencia'],
                    $dados['vencimento'] ?? null,
                    $request->boolean('sincrono'),
                    $token,
                    $override,
                    (float) ($dados['percentual_reajuste'] ?? 0),
                );

                $status = $request->boolean('sincrono') ? 201 : 202;

                return response()->json([
                    'message' => $request->boolean('sincrono')
                        ? 'Fatura processada.'
                        : 'Fatura em processamento.',
                    'fatura' => $fatura->fresh(['lancamentos', 'contratante']),
                ], $status);
            }

            if (! empty($dados['contratante_id'])) {
                $empresa = Contratante::query()->findOrFail($dados['contratante_id']);
                if ($empresa->tipo !== TipoContratante::Pj) {
                    return response()->json(['message' => 'Contratante precisa ser PJ.'], 422);
                }
                $fatura = $legado->executar(
                    $empresa,
                    $dados['competencia'],
                    $dados['vencimento'] ?? null,
                    $dados['lancamentos'] ?? []
                );

                return response()->json($fatura, 201);
            }
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Informe chave_plano_sigoweb (fluxo oficial) ou contratante_id (legado).',
        ], 422);
    }

    public function emitirCobranca(string $id, Request $request, EmitirCobrancaFaturaPjService $service): JsonResponse
    {
        $dados = $request->validate([
            'meio' => ['nullable', 'string', 'max:20'],
        ]);

        $fatura = Fatura::query()->findOrFail($id);

        try {
            $cobranca = $service->executar($fatura, $dados['meio'] ?? 'boleto');
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($cobranca, 201);
    }

    public function adicionarLancamento(
        string $id,
        Request $request,
        AdicionarLancamentoFaturaService $service
    ): JsonResponse {
        $dados = $request->validate([
            'codigo' => ['required', 'string', 'max:40'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'natureza' => ['required', 'in:base,retencao,acrescimo,informativo'],
            'valor' => ['required', 'numeric', 'min:0'],
            'ordem' => ['nullable', 'integer', 'min:1'],
        ]);

        $fatura = Fatura::query()->findOrFail($id);

        try {
            $fatura = $service->executar($fatura, $dados);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($fatura, 201);
    }

    public function pdfFatura(string $id, GerarPdfFaturaPjService $service): Response
    {
        $fatura = Fatura::query()->findOrFail($id);

        try {
            $pdf = $service->executar($fatura);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $nome = 'fatura_'.$fatura->competencia.'_'.substr($fatura->id, 0, 8).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
        ]);
    }

    public function pdfDemonstrativoTitulares(string $id, GerarPdfDemonstrativoFaturaService $service): Response
    {
        return $this->pdfDemonstrativo($id, $service, false);
    }

    public function pdfDemonstrativoCompleto(string $id, GerarPdfDemonstrativoFaturaService $service): Response
    {
        return $this->pdfDemonstrativo($id, $service, true);
    }

    public function pdfBoleto(string $id, GerarPdfBoletoService $service): Response
    {
        $fatura = Fatura::query()->findOrFail($id);

        if (! $fatura->cobranca_id) {
            return response()->json(['message' => 'Fatura ainda sem cobrança/boleto. Emita a cobrança antes.'], 422);
        }

        $cobranca = Cobranca::query()->findOrFail($fatura->cobranca_id);

        try {
            $pdf = $service->executar($cobranca);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $nome = 'boleto_fatura_'.$fatura->competencia.'_'.($cobranca->nosso_numero ?: substr($cobranca->id, 0, 8)).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
        ]);
    }

    private function pdfDemonstrativo(
        string $id,
        GerarPdfDemonstrativoFaturaService $service,
        bool $comDependentes
    ): Response {
        $fatura = Fatura::query()->findOrFail($id);

        try {
            $pdf = $service->executar($fatura, $comDependentes);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $sufixo = $comDependentes ? 'completo' : 'titulares';
        $nome = 'demonstrativo_'.$sufixo.'_'.$fatura->competencia.'_'.substr($fatura->id, 0, 8).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
        ]);
    }
}
