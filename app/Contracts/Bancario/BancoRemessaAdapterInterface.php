<?php

namespace App\Contracts\Bancario;

/**
 * Adaptador de banco (OCP): Sicredi, Bradesco, BB…
 * Cada Uniodonto escolhe o adapter via cliente.config.bancario.
 */
interface BancoRemessaAdapterInterface
{
    public function codigoBanco(): string;

    public function seletorTitulos(): TitulosRemessaSelectorInterface;

    public function geradorNossoNumero(): NossoNumeroGeneratorInterface;

    public function layout(): RemessaLayoutGeneratorInterface;

    public function nomeArquivo(): RemessaNomeArquivoInterface;
}
