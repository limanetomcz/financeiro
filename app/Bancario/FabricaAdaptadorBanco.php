<?php

namespace App\Bancario;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\Sicredi\SicrediRemessaAdapter;
use App\Contracts\Bancario\BancoRemessaAdapterInterface;
use App\Exceptions\DominioException;
use App\Models\Cliente;
use Illuminate\Contracts\Container\Container;

/**
 * Open/Closed: novos bancos = registrar aqui (ou via config) sem mudar o orquestrador.
 */
class FabricaAdaptadorBanco
{
    /** @var array<string, class-string<BancoRemessaAdapterInterface>> */
    private array $mapa = [
        '748' => SicrediRemessaAdapter::class,
        'sicredi' => SicrediRemessaAdapter::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function paraCliente(Cliente $cliente): BancoRemessaAdapterInterface
    {
        $conta = ContaCobranca::fromClienteConfig($cliente->config ?? []);
        $chave = strtolower((string) data_get($cliente->config, 'bancario.banco', $conta->codigoBanco));

        return $this->porCodigo($chave !== '' ? $chave : $conta->codigoBanco);
    }

    public function porCodigo(string $codigoOuAlias): BancoRemessaAdapterInterface
    {
        $chave = strtolower(trim($codigoOuAlias));
        $classe = $this->mapa[$chave] ?? null;

        if ($classe === null) {
            throw new DominioException("Banco de remessa não suportado: {$codigoOuAlias}. Registre um adapter em FabricaAdaptadorBanco.");
        }

        return $this->container->make($classe);
    }

    /**
     * @param  class-string<BancoRemessaAdapterInterface>  $adapterClass
     */
    public function registrar(string $codigoOuAlias, string $adapterClass): void
    {
        $this->mapa[strtolower($codigoOuAlias)] = $adapterClass;
    }
}
