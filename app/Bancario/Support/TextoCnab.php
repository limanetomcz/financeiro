<?php

namespace App\Bancario\Support;

final class TextoCnab
{
    public static function apenasDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    public static function alfanumerico(string $valor, int $tamanho): string
    {
        $limpo = self::semAcentos(mb_strtoupper($valor, 'UTF-8'));
        $limpo = preg_replace('/[^A-Z0-9 \\/]/', ' ', $limpo) ?? '';
        $limpo = preg_replace('/\\s+/', ' ', trim($limpo)) ?? '';

        return self::rpad($limpo, $tamanho);
    }

    public static function semAcentos(string $valor): string
    {
        $map = [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
            'á' => 'A', 'à' => 'A', 'â' => 'A', 'ã' => 'A', 'ä' => 'A',
            'é' => 'E', 'è' => 'E', 'ê' => 'E', 'ë' => 'E',
            'í' => 'I', 'ì' => 'I', 'î' => 'I', 'ï' => 'I',
            'ó' => 'O', 'ò' => 'O', 'ô' => 'O', 'õ' => 'O', 'ö' => 'O',
            'ú' => 'U', 'ù' => 'U', 'û' => 'U', 'ü' => 'U',
            'ç' => 'C', 'ñ' => 'N',
            'ª' => ' ', '.' => ' ', '-' => ' ',
        ];

        return strtr($valor, $map);
    }

    public static function lpad(string|int $valor, int $tamanho, string $char = '0'): string
    {
        return str_pad((string) $valor, $tamanho, $char, STR_PAD_LEFT);
    }

    public static function rpad(string $valor, int $tamanho, string $char = ' '): string
    {
        $valor = mb_substr($valor, 0, $tamanho, 'UTF-8');

        return str_pad($valor, $tamanho, $char, STR_PAD_RIGHT);
    }

    public static function valorCentavos(float $valor, int $tamanho = 15): string
    {
        $centavos = (int) round($valor * 100);

        return self::lpad($centavos, $tamanho);
    }

    public static function data(string|\DateTimeInterface $data, string $formato = 'dmY'): string
    {
        if (is_string($data)) {
            $data = new \DateTimeImmutable($data);
        }

        return $data->format($formato);
    }
}
