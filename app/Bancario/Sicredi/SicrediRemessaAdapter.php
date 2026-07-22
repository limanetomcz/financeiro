<?php

namespace App\Bancario\Sicredi;

use App\Contracts\Bancario\BancoRemessaAdapterInterface;
use App\Contracts\Bancario\NossoNumeroGeneratorInterface;
use App\Contracts\Bancario\RemessaLayoutGeneratorInterface;
use App\Contracts\Bancario\RemessaNomeArquivoInterface;
use App\Contracts\Bancario\TitulosRemessaSelectorInterface;

class SicrediRemessaAdapter implements BancoRemessaAdapterInterface
{
    public function __construct(
        private readonly SicrediTitulosRemessaSelector $seletor,
        private readonly SicrediNossoNumeroGenerator $nossoNumero,
        private readonly SicrediCnab240Layout $layout,
        private readonly SicrediNomeArquivo $nomeArquivo,
    ) {}

    public function codigoBanco(): string
    {
        return '748';
    }

    public function seletorTitulos(): TitulosRemessaSelectorInterface
    {
        return $this->seletor;
    }

    public function geradorNossoNumero(): NossoNumeroGeneratorInterface
    {
        return $this->nossoNumero;
    }

    public function layout(): RemessaLayoutGeneratorInterface
    {
        return $this->layout;
    }

    public function nomeArquivo(): RemessaNomeArquivoInterface
    {
        return $this->nomeArquivo;
    }
}
