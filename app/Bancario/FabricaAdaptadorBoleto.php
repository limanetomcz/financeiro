<?php

namespace App\Bancario;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\Sicredi\SicrediBoletoAdapter;
use App\Contracts\Bancario\BancoBoletoAdapterInterface;
use App\Exceptions\DominioException;
use App\Models\Cliente;
use Illuminate\Contracts\Container\Container;

/**
 * Open/Closed: novo banco de boleto = registrar aqui sem mudar GerarPdfBoletoService.
 */
class FabricaAdaptadorBoleto
{
    /** @var array<string, class-string<BancoBoletoAdapterInterface>> */
    private array $mapa = [
        '748' => SicrediBoletoAdapter::class,
        'sicredi' => SicrediBoletoAdapter::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function paraCliente(Cliente $cliente): BancoBoletoAdapterInterface
    {
        $conta = ContaCobranca::fromClienteConfig($cliente->config ?? []);
        $chave = strtolower((string) data_get($cliente->config, 'bancario.banco', $conta->codigoBanco));

        return $this->porCodigo($chave !== '' ? $chave : $conta->codigoBanco);
    }

    public function porCodigo(string $codigoOuAlias): BancoBoletoAdapterInterface
    {
        $chave = strtolower(trim($codigoOuAlias));
        $classe = $this->mapa[$chave] ?? null;

        if ($classe === null) {
            throw new DominioException(
                "Banco de boleto PDF não suportado: {$codigoOuAlias}. Registre um adapter em FabricaAdaptadorBoleto."
            );
        }

        return $this->container->make($classe);
    }

    /**
     * @param  class-string<BancoBoletoAdapterInterface>  $adapterClass
     */
    public function registrar(string $codigoOuAlias, string $adapterClass): void
    {
        $this->mapa[strtolower($codigoOuAlias)] = $adapterClass;
    }
}
