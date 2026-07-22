<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Services\Boleto\GerarPdfBoletoService;
use Symfony\Component\HttpFoundation\Response;

class BoletoController extends Controller
{
    public function pdf(string $id, GerarPdfBoletoService $service): Response
    {
        $cobranca = Cobranca::query()->findOrFail($id);

        try {
            $pdf = $service->executar($cobranca);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $nome = 'boleto_'.($cobranca->nosso_numero ?: $cobranca->id).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
        ]);
    }
}
