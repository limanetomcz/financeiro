<?php

namespace App\Services\Auditoria;

/**
 * Ponto único para auditoria financeira.
 *
 * Fusca: integrar depois. Em lab/teste NÃO envia nada para o Fusca
 * (evita sujar o log com massa de teste).
 */
class AuditoriaFinanceira
{
    /**
     * @param  array<string, mixed>  $contexto
     */
    public function registrar(string $acao, array $contexto = []): void
    {
        if (! config('financeiro.auditoria_fusca_habilitada', false)) {
            return;
        }

        // TODO: publicar no Fusca quando FINANCEIRO_AUDITORIA_FUSCA=true
    }
}
