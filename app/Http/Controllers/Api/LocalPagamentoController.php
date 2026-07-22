<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocalPagamento;
use App\Services\LocalPagamento\ResolverLocalPagamentoService;
use App\Exceptions\DominioException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalPagamentoController extends Controller
{
    /**
     * Catálogo do tenant (canais + taxas aninhadas).
     * GET /api/v1/locais-pagamento?com_taxas=1
     */
    public function index(Request $request): JsonResponse
    {
        $somenteAtivos = $request->boolean('ativos', true);
        $comTaxas = $request->boolean('com_taxas', true);

        $query = LocalPagamento::query()
            ->when($somenteAtivos, fn ($q) => $q->where('ativo', true))
            ->orderBy('ordem')
            ->orderBy('descricao');

        if ($comTaxas) {
            $query->with(['taxas' => function ($q) use ($somenteAtivos) {
                $q->when($somenteAtivos, fn ($qq) => $qq->where('ativo', true))
                    ->orderBy('ordem')
                    ->orderBy('descricao');
            }]);
        }

        $itens = $query->get()->map(function (LocalPagamento $local) use ($comTaxas) {
            $row = [
                'id' => $local->id,
                'codigo' => $local->codigo,
                'codigo_legado' => $local->codigo_legado,
                'descricao' => $local->descricao,
                'tipo' => $local->tipo->value,
                'exige_taxa' => $local->exigeTaxa(),
                'ativo' => $local->ativo,
                'ordem' => $local->ordem,
            ];

            if ($comTaxas) {
                $row['taxas'] = $local->taxas->map(fn ($t) => [
                    'id' => $t->id,
                    'codigo_legado' => $t->codigo_legado,
                    'descricao' => $t->descricao,
                    'modalidade' => $t->modalidade->value,
                    'bandeira' => $t->bandeira->value,
                    'taxa_percentual' => (float) $t->taxa_percentual,
                    'dias_credito' => $t->dias_credito,
                    'ativo' => $t->ativo,
                    'ordem' => $t->ordem,
                ])->values();
            }

            return $row;
        });

        return response()->json(['data' => $itens]);
    }

    /**
     * Resolve LOC_CODIGO legado → local + taxa.
     * GET /api/v1/locais-pagamento/resolver?codigo_legado=61
     */
    public function resolver(Request $request, ResolverLocalPagamentoService $service): JsonResponse
    {
        $dados = $request->validate([
            'codigo_legado' => ['required', 'string', 'max:10'],
            'na_data' => ['nullable', 'date'],
        ]);

        try {
            $resolvido = $service->porCodigoLegado(
                $dados['codigo_legado'],
                $dados['na_data'] ?? null
            );
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $local = $resolvido['local'];
        $taxa = $resolvido['taxa'];

        return response()->json([
            'local' => [
                'id' => $local->id,
                'codigo' => $local->codigo,
                'descricao' => $local->descricao,
                'tipo' => $local->tipo->value,
            ],
            'taxa' => $taxa ? [
                'id' => $taxa->id,
                'codigo_legado' => $taxa->codigo_legado,
                'descricao' => $taxa->descricao,
                'modalidade' => $taxa->modalidade->value,
                'bandeira' => $taxa->bandeira->value,
                'taxa_percentual' => (float) $taxa->taxa_percentual,
            ] : null,
        ]);
    }

    /**
     * Alta rápida de canal (lab).
     * POST /api/v1/locais-pagamento
     */
    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'codigo' => ['required', 'string', 'max:10'],
            'codigo_legado' => ['nullable', 'string', 'max:10'],
            'descricao' => ['required', 'string', 'max:80'],
            'tipo' => ['required', 'in:caixa,banco,pix,cartao'],
            'ativo' => ['sometimes', 'boolean'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
        ]);

        $local = LocalPagamento::query()->updateOrCreate(
            ['codigo' => $dados['codigo']],
            [
                'codigo_legado' => $dados['codigo_legado'] ?? null,
                'descricao' => $dados['descricao'],
                'tipo' => $dados['tipo'],
                'ativo' => $dados['ativo'] ?? true,
                'ordem' => $dados['ordem'] ?? 100,
            ]
        );

        return response()->json($local, 201);
    }
}
