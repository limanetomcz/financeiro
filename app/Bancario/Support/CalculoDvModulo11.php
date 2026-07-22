<?php

namespace App\Bancario\Support;

/**
 * Espelho de fun_calculodvmodulo11 (Oracle).
 * Pesos 2..9 da direita para a esquerda; DV = 11 - (soma % 11); se >= 10 → 0.
 *
 * Se o fonte Oracle divergir (ex.: resto 0/1), ajustar só esta classe.
 */
final class CalculoDvModulo11
{
    public static function digito(string $base): string
    {
        $digitos = TextoCnab::apenasDigitos($base);

        if ($digitos === '') {
            throw new \InvalidArgumentException('Base vazia para DV módulo 11.');
        }

        $soma = 0;
        $peso = 2;

        for ($i = strlen($digitos) - 1; $i >= 0; $i--) {
            $soma += ((int) $digitos[$i]) * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }

        $resto = $soma % 11;
        $dv = 11 - $resto;

        return $dv >= 10 ? '0' : (string) $dv;
    }
}
